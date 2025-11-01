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
    if(Order::load($this->resolveOrderId(json_decode($request->getContent()))) !== null)
    {
      return AccessResult::allowed();
    }
    $this->logger->error("Böyle bir sipariş bulunamadı.");
    return AccessResult::forbidden();
  }

  private function resolveOrderId($request): int
  {
    $merchant_oid = $request->merchant_oid;
    $merchant_oid = explode('DR', $merchant_oid);
    $merchant_oid = str_replace('SP', '', $merchant_oid[0]);
    return (int) $merchant_oid;
  }
}
