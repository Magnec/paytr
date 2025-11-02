<?php

namespace Drupal\paytr_payment\Access;

use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Access\AccessResult;
use Psr\Log\LoggerInterface;

class PaytrPaymentCallbackAccessCheck implements AccessInterface {

  protected LoggerInterface $logger;

  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  public function access(Request $request): AccessResult
  {
    // PayTr can send data as JSON or form-data, so we need to handle both
    $data = json_decode($request->getContent());
    if (!$data) {
      // If JSON decode fails, try to get from POST parameters
      $merchant_oid = $request->request->get('merchant_oid');
    } else {
      $merchant_oid = $data->merchant_oid ?? null;
    }

    // Check if merchant_oid exists
    if (empty($merchant_oid)) {
      $this->logger->error("PayTr callback: merchant_oid parametresi bulunamadı.");
      return AccessResult::forbidden();
    }

    $order_id = $this->resolveOrderId($merchant_oid);
    if ($order_id && Order::load($order_id) !== null) {
      return AccessResult::allowed();
    }

    $this->logger->error("PayTr callback: Böyle bir sipariş bulunamadı. Order ID: @order_id", ['@order_id' => $order_id]);
    return AccessResult::forbidden();
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
}
