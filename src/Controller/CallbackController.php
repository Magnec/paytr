<?php

namespace Drupal\paytr_payment\Controller;

use Drupal;
use Drupal\commerce_order\Entity\Order;
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

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container): CallbackController
  {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function callback(Request $request): Response
  {
    $request = json_decode($request->getContent());
    $payment = $this->entityTypeManager->getStorage('commerce_payment')->loadByRemoteId($this->resolveOrderId($request));
    $state   =
      $request->status === 'success' &&
      $this->makeHash($request, $payment) === $request->hash ? 'completed' : 'canceled';
    $order = Order::load($this->resolveOrderId($request));
    if($state==='canceled')
    {
      $order->set('state', $state);
      $order->save();
      return new Response('OK');
    }
    $payment->set('state', $state);
    $payment->set('remote_id', $request->merchant_oid);
    $payment->save();
    $order->set('state', $state);
    $order->save();
    $logger = Drupal::logger('paytr_payment');
    $logger->info('Saving Payment information. Transaction reference: '.$request->merchant_oid);
    return new Response('OK');
  }

  private function resolveOrderId($request): int
  {
    $merchant_oid = $request->merchant_oid;
    $merchant_oid = explode('DR', $merchant_oid);
    $merchant_oid = str_replace('SP', '', $merchant_oid[0]);
    return (int) $merchant_oid;
  }

  private function makeHash($request, $payment): string
  {
    $paytrHelper  = new PaytrHelper($payment);
    return base64_encode( hash_hmac('sha256', $request->merchant_oid.$paytrHelper->getMerchantSalt().$request->status.$request->total_amount, $paytrHelper->getMerchantKey(), true) );
  }
}
