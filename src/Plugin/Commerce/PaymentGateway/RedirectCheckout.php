<?php

namespace Drupal\paytr_payment\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
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
class RedirectCheckout extends OffsitePaymentGatewayBase
{
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array
  {
    return [
        'merchant_id' => '',
        'merchant_key' => '',
        'merchant_salt' => '',
        'iframe_v2' => 1,
        'iframe_v2_dark' => 0,
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array
  {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['notification_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Callback URL'),
      '#default_value' => $_SERVER['SERVER_NAME'].'/paytr-payment/callback',
      '#disabled' => TRUE,
      '#description' => $this->t('Copy the address written in this field and paste it into the relevant field on the <b><a target="_blank"  href="https://www.paytr.com/magaza/ayarlar">Settings</a></b> page.'),
    ];
    $form['merchant_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant ID'),
      '#default_value' => $this->configuration['merchant_id'],
      '#required' => TRUE,
    ];
    $form['merchant_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant Key'),
      '#default_value' => $this->configuration['merchant_key'],
      '#required' => TRUE,
    ];
    $form['merchant_salt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Merchant Salt'),
      '#default_value' => $this->configuration['merchant_salt'],
      '#required' => TRUE,
    ];
    $form['iframe_v2'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable iFrame V2'),
      '#default_value' => $this->configuration['iframe_v2'],
    ];

    $form['iframe_v2_dark'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Dark Theme for iFrame V2'),
      '#default_value' => $this->configuration['iframe_v2_dark'],
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void
  {
    parent::submitConfigurationForm($form, $form_state);
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['merchant_id'] = $values['merchant_id'];
      $this->configuration['merchant_key'] = $values['merchant_key'];
      $this->configuration['merchant_salt'] = $values['merchant_salt'];
      $this->configuration['iframe_v2'] = $values['iframe_v2'];
      $this->configuration['iframe_v2_dark'] = $values['iframe_v2_dark'];
     
    }
  }

  /**
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function onReturn(OrderInterface $order, Request $request): void
  {

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $order = Order::load($order->id());
    $payment = $payment_storage->create([
      'state' => 'authorization',
      'amount' => $order->getTotalPrice(),
      'payment_gateway' => $this->parentEntity->id(),
      'order_id' => $order->id(),
      'remote_id' => $order->id(),
      'remote_state' => 'pending'
    ]);
    $payment->save();
  }
}
