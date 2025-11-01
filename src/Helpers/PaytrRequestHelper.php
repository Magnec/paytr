<?php

namespace Drupal\paytr_payment\Helpers;

/**
 * Class PaytrRequestHelper
 * @package Drupal\paytr_payment\Helpers
 */
class PaytrRequestHelper
{

  private $variables;

  /**
   * PaytrRequestHelper constructor.
   * @param $variables
   */
  public function __construct($variables)
  {
    $this->variables = $variables;
  }

  /**
   * @return array
   */
  public function getPaytrToken(): array
  {
    $ch=curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.paytr.com/odeme/api/get-token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1) ;
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->variables);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $result = json_decode(curl_exec($ch));
    if($result->status === 'success'){
      return [
        'token' => $result->token
      ];
    }
    return [
      'error' => $result->reason
    ];
  }
}
