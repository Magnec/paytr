<?php

namespace Drupal\paytr_payment\Controller;

use Drupal;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\OrderLockInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paytr_payment\Helpers\PaytrHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class CallbackController
 * @package Drupal\paytr_payment\Controller
 */
class CallbackController extends ControllerBase {

  protected $entityTypeManager;
  protected $orderLock;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, OrderLockInterface $orderLock) {
    $this->entityTypeManager = $entityTypeManager;
    $this->orderLock = $orderLock;
  }

  public static function create(ContainerInterface $container): CallbackController
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('commerce_order.order_lock')
    );
  }

  public function callback(Request $request): Response
  {
    $logger = Drupal::logger('paytr_payment');

    // PayTr can send data as JSON or form-data, handle both
    $data = json_decode($request->getContent());
    if (!$data) {
      // If JSON decode fails, get from POST parameters
      $merchant_oid = $request->request->get('merchant_oid');
      $status = $request->request->get('status');
      $hash = $request->request->get('hash');
      $total_amount = $request->request->get('total_amount');
    } else {
      $merchant_oid = $data->merchant_oid ?? null;
      $status = $data->status ?? null;
      $hash = $data->hash ?? null;
      $total_amount = $data->total_amount ?? null;
    }

    // Validate required parameters
    if (empty($merchant_oid) || empty($status) || empty($hash)) {
      $logger->error('PayTr callback: Gerekli parametreler eksik. merchant_oid: @merchant_oid, status: @status', [
        '@merchant_oid' => $merchant_oid ?? 'null',
        '@status' => $status ?? 'null',
      ]);
      return new Response('Missing required parameters', 400);
    }

    $order_id = $this->resolveOrderId($merchant_oid);
    if (!$order_id) {
      $logger->error('PayTr callback: Order ID çözümlenemedi. merchant_oid: @merchant_oid', [
        '@merchant_oid' => $merchant_oid,
      ]);
      return new Response('Invalid order ID', 400);
    }

    $order = Order::load($order_id);
    if (!$order) {
      $logger->error('PayTr callback: Sipariş bulunamadı. Order ID: @order_id', [
        '@order_id' => $order_id,
      ]);
      return new Response('Order not found', 404);
    }

    // Load or create payment (callback can arrive before onReturn due to race condition)
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payments = $payment_storage->loadByProperties([
      'order_id' => $order_id,
    ]);
    $payment = !empty($payments) ? reset($payments) : null;

    // If payment doesn't exist, create it (callback arrived before onReturn)
    if (!$payment) {
      $logger->info('PayTr callback: Ödeme bulunamadı, oluşturuluyor. Order ID: @order_id', [
        '@order_id' => $order_id,
      ]);

      // Get payment gateway from order
      $payment_gateway_id = $order->get('payment_gateway')->target_id;
      if (!$payment_gateway_id) {
        $logger->error('PayTr callback: Payment gateway bulunamadı. Order ID: @order_id', [
          '@order_id' => $order_id,
        ]);
        return new Response('Payment gateway not found', 404);
      }

      $payment = $payment_storage->create([
        'state' => 'authorization',
        'amount' => $order->getTotalPrice(),
        'payment_gateway' => $payment_gateway_id,
        'order_id' => $order->id(),
        'remote_id' => $order_id,
        'remote_state' => 'pending'
      ]);
      $payment->save();
    }

    // Verify hash
    $calculated_hash = $this->makeHash($merchant_oid, $status, $total_amount, $payment);
    $is_hash_valid = $calculated_hash === $hash;

    // Debug logging for hash validation
    if (!$is_hash_valid) {
      $logger->warning('PayTr callback: Hash doğrulaması başarısız. Order ID: @order_id, Calculated: @calc, Received: @recv', [
        '@order_id' => $order_id,
        '@calc' => $calculated_hash,
        '@recv' => $hash,
      ]);
    }

    // Determine payment state based on PayTr status and hash validation
    $payment_state = ($status === 'success' && $is_hash_valid) ? 'completed' : 'authorization';

    // Handle failed payment or hash validation
    if ($payment_state !== 'completed') {
      $logger->warning('PayTr callback: Ödeme başarısız veya hash doğrulaması başarısız. Order ID: @order_id, Status: @status, Hash Valid: @valid', [
        '@order_id' => $order_id,
        '@status' => $status,
        '@valid' => $is_hash_valid ? 'Yes' : 'No',
      ]);
      // Update payment to authorization state (pending)
      $payment->set('state', $payment_state);
      $payment->set('remote_id', $merchant_oid);
      $payment->save();
      // Do NOT change order state - let Commerce Checkout workflow handle it
      return new Response('OK');
    }

    // Update payment with completed state
    $payment->set('state', $payment_state);
    $payment->set('remote_id', $merchant_oid);
    $payment->save();

    // Place the order using Commerce workflow
    // This properly transitions the order through the correct states
    if ($order->getState()->getId() !== 'completed') {
      $transition = $order->getState()->getWorkflow()->getTransition('place');
      if ($transition) {
        $order->getState()->applyTransition($transition);
        $order->save();
      }
    }

    // Unlock the order - it was locked during checkout
    // This allows the order to be edited/viewed normally
    if ($this->orderLock->isLocked($order)) {
      $this->orderLock->unlock($order);
      $logger->info('PayTr callback: Sipariş kilidi kaldırıldı. Order ID: @order_id', [
        '@order_id' => $order_id,
      ]);
    }

    $logger->info('PayTr callback: Ödeme başarıyla kaydedildi. Transaction reference: @merchant_oid, Order ID: @order_id', [
      '@merchant_oid' => $merchant_oid,
      '@order_id' => $order_id,
    ]);

    return new Response('OK');
  }

  private function resolveOrderId(?string $merchant_oid): ?int
  {
    // Check if merchant_oid is null or empty
    if (empty($merchant_oid)) {
      return null;
    }

    // Split by 'DR' if it exists
    $parts = explode('DR', $merchant_oid);
    $merchant_oid = str_replace('SP', '', $parts[0]);

    return (int) $merchant_oid;
  }

  private function makeHash(string $merchant_oid, string $status, string $total_amount, $payment): string
  {
    $paytrHelper = new PaytrHelper($payment);
    return base64_encode(hash_hmac('sha256', $merchant_oid . $paytrHelper->getMerchantSalt() . $status . $total_amount, $paytrHelper->getMerchantKey(), true));
  }
}
