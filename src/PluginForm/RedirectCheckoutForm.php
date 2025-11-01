<?php

namespace Drupal\paytr_payment\PluginForm;

use Drupal\commerce_payment\Annotation\CommercePaymentGateway;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\paytr_payment\Helpers\PaytrHelper;
use Drupal\paytr_payment\Helpers\PaytrRequestHelper;

/**
 * Provides the PayTR offsite Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paytr_payment_redirect_checkout",
 *   label = @Translation("PayTR Virtual Pos iFrame API"),
 *   display_label = @Translation("Credit Card / Bank Transfer"),
 *    forms = {
 *     "offsite-payment" = "Drupal\paytr_payment\PluginForm\RedirectCheckoutForm",
 *   },
 * )
 */
class RedirectCheckoutForm extends BasePaymentOffsiteForm {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array
  {
    $form = parent::buildConfigurationForm($form, $form_state);
    /** @var PaymentInterface $payment */

    $paytrHelper        = new PaytrHelper($this->entity, $form);
    $paytrRequestHelper = new PaytrRequestHelper($paytrHelper->makePostVariables());
    $paytrToken         = $paytrRequestHelper->getPaytrToken();
    $form['paytr_payment_checkout'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => Markup::create('<iframe src="https://www.paytr.com/odeme/guvenli/'.$paytrToken['token'].'" id="paytriframe" frameBorder="0" scrolling="no" style="width: 100%; height: 600px;"></iframe>'),
      '#attributes' => ['id' => 'paytr-payment-checkout'],
    ];
    $form['#attached']['library'][] = 'paytr_payment/checkout';
    $form['#attached']['library'][] = 'paytr_payment/iframe_resizer';
    $form['#attached']['drupalSettings']['paytr_payment_iframe']
      = json_encode($paytrRequestHelper->getPaytrToken());
    return $form;
  }
}
