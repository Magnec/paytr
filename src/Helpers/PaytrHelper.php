<?php
namespace Drupal\paytr_payment\Helpers;

use Drupal;
use Drupal\commerce_product\Entity\Product;

/**
 * Class PaytrHelper
 * @package Drupal\paytr_payment\Helpers
 */
class PaytrHelper
{

  protected $payment;
  protected $form;

  public function __construct($payment, $form = null)
  {
    $this->payment = $payment;
    $this->form    = $form;
  }

  private function installmentCalculate($installments): array
  {
    if(in_array('1', $installments))
    {
      return [
        'no_installment'    => "1",
        'max_installment'   => "0",
      ];
    }
    elseif (($key = array_search('0', $installments)) !== false && count($installments) > 1)
    {
      unset($installments[$key]);
      return [
        'no_installment'    => "0",
        'max_installment'   => min($installments),
      ];
    }
    return [
      'no_installment'    => "0",
      'max_installment'   => count($installments) ? min($installments) : 0,
    ];
  }

  private function getConfigration($value)
  {
    return $this->payment->getPaymentGateway()->getPlugin()->getConfiguration()[$value];
  }

  private function getOrder()
  {
    return $this->payment->getOrder();
  }

  private function getBillingProfile()
  {
    return $this->getOrder()->getBillingProfile()->get('address')->first();
  }

  public function getMerchantID()
  {
    return $this->getConfigration('merchant_id');
  }

  public function getMerchantKey()
  {
    return $this->getConfigration('merchant_key');
  }

  public function getMerchantSalt()
  {
    return $this->getConfigration('merchant_salt');
  }

  public function getCurrency(): string
  {
    if($this->payment->getAmount()->getCurrencyCode() === 'TRY')
    {
      return 'TL';
    }
    return $this->payment->getAmount()->getCurrencyCode();
  }

  public function getTotalAmount(): float|int
  {
    return $this->payment->getAmount()->getNumber() * 100;
  }

  public function getOrderID()
  {
    return $this->payment->getOrderId();
  }

  public function getCustomerEmail()
  {
    return $this->getOrder()->getEmail();
  }

  public function getNameSurname(): string
  {
    return $this->getBillingProfile()->given_name.' '.$this->getBillingProfile()->family_name;
  }

  public function getAddress(): string
  {
    return join(', ', [
      'company' => $this->getBillingProfile()->getOrganization(),
      'streetAddress' => $this->getBillingProfile()->getAddressLine1(),
      'extendedAddress' => $this->getBillingProfile()->getAddressLine2(),
      'locality' => $this->getBillingProfile()->getLocality(),
      'region' => $this->getBillingProfile()->getAdministrativeArea(),
      'postalCode' => $this->getBillingProfile()->getPostalCode(),
      'countryCodeAlpha2' => $this->getBillingProfile()->getCountryCode(),
    ]);
  }

  public function getUserBasket(): string
  {
    $items = [];
    foreach ($this->getOrder()->getItems() as $key => $item)
    {
      $items[] = array(
        $item->getTitle(),
        number_format($item->getUnitPrice()->getNumber(), 2),
        round($item->getQuantity())
      );
    }
    return base64_encode(json_encode($items));
  }

  public function getOrderCategories(): array
  {

    $items = [];
    foreach ($this->getOrder()->getItems() as $key => $item)
    {
      $product = $item->getPurchasedEntity()->getProductId();
      $items[] = Product::load($product)->get('product_collections')->getValue();
    }
    return $items[0];
  }

  public function getPhone()
  {
    return $this->getOrder()->getBillingProfile()->get('field_phone')->first()->value;
  }

  public function getMerchantOkUrl(): string
  {
    return $this->form['#return_url'];
  }

  public function getMerchantFailUrl(): string
  {
    return $this->form['#return_url'];
  }

  public function getUserIpAddress()
  {
    if( isset( $_SERVER["HTTP_CLIENT_IP"] ) ) {
      $ip = $_SERVER["HTTP_CLIENT_IP"];
    } elseif( isset( $_SERVER["HTTP_X_FORWARDED_FOR"] ) ) {
      $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    } else {
      $ip = $_SERVER["REMOTE_ADDR"];
    }
    return $ip;
  }

  public function getInstallments(): array
  {
    return $this->getCategoryBasedInstallments();
  }

  public function getMerchantOid(): string
  {
    return 'SP'.$this->getOrderID().'DR'.time();
  }

  public function getTestMode(): int
  {
    return $this->getConfigration('mode') === 'test' ? 1 : 0;
  }

  public function getLang(): string
  {
    if(Drupal::languageManager()->getCurrentLanguage()->getId() === 'en-gb'){
      return 'en';
    }
    return 'tr';
  }
  private function getIframeV2(): int
  {
  return $this->getConfigration('iframe_v2') ? 1 : 0;
  }

  private function getIframeV2Dark(): int
  {
  return $this->getConfigration('iframe_v2_dark') ? 1 : 0;
  }

  private function getCategoryBasedInstallments(): array
  {
    $current_installments = [];
    $category_installments = [];
    $config = Drupal::config('paytr_payment.settings');
    if($config->get('paytr_payment'))
    {
      foreach ($config->get('paytr_payment') as $key => $installment)
      {
        $current_installments[str_replace('installment_', '', $key)] = $installment;
      }
      foreach ($this->getOrderCategories() as $category)
      {
        if(array_key_exists($category['target_id'], $current_installments))
        {
          $category_installments[$category['target_id']] = $current_installments[$category['target_id']];
        }
      }
    }
    return $this->installmentCalculate($category_installments);
  }

  public function makeHashStr(): string
  {
    return
      $this->getMerchantId()
      . $this->getUserIpAddress()
      . $this->getMerchantOid()
      . $this->getCustomerEmail()
      . $this->getTotalAmount()
      . $this->getUserBasket()
      . $this->getInstallments()['no_installment']
      . $this->getInstallments()['max_installment']
      . $this->getCurrency()
      . $this->getTestMode();
  }

  public function getToken(): string
  {
    return base64_encode(hash_hmac('sha256', $this->makeHashStr().$this->getMerchantSalt(), $this->getMerchantKey(), true));
  }

  public function makePostVariables(): array
  {
    return array(
      'merchant_id'       =>  $this->getMerchantId(),
      'user_ip'           =>  $this->getUserIpAddress(),
      'merchant_oid'      =>  $this->getMerchantOid(),
      'email'             =>  $this->getCustomerEmail(),
      'payment_amount'    =>  $this->getTotalAmount(),
      'paytr_token'       =>  $this->getToken(),
      'user_basket'       =>  $this->getUserBasket(),
      'debug_on'          =>  1,
      'no_installment'    =>  $this->getInstallments()['no_installment'],
      'max_installment'   =>  $this->getInstallments()['max_installment'],
      'user_name'         =>  $this->getNameSurname(),
      'user_address'      =>  $this->getAddress(),
      'user_phone'        =>  $this->getPhone(),
      'merchant_ok_url'   =>  $this->getMerchantOkUrl(),
      'merchant_fail_url' =>  $this->getMerchantFailUrl(),
      'timeout_limit'     =>  30,
      'currency'          =>  $this->getCurrency(),
      'test_mode'         =>  $this->getTestMode(),
      'lang'              =>  $this->getLang(),
      'iframe_v2'         => $this->getIframeV2(),         
      'iframe_v2_dark'    => $this->getIframeV2Dark()
    );
  }
}
