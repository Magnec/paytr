<?php

namespace Drupal\paytr_payment\Form;

use Drupal;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
/**
 * Class PaytrPaymentSettingsForm
 * @package Drupal\paytr_payment\Form
 */
class PaytrPaymentSettingsForm extends ConfigFormBase
{
  public function getFormId(): string
  {
    return 'paytr_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('paytr_payment.settings');
    $form['information'] = array(
      '#title' => $this->t('Installment Settings'),
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-publication',
    );
    foreach ($this->fetchCategories() as $term) {
      $form['cat_'.$term->tid] = array(
        '#type' => 'details',
        '#title' => $this->t($term->name),
        '#group' => 'information',
      );
      $form['cat_'.$term->tid]['installment_'.$term->tid] = [
        '#type' => 'radios',
        '#title' => $this->t('Current Installment Setting'),
        '#default_value' => $config->get('paytr_payment.installment_'.$term->tid),
        '#options' => [
          0 => $this->t('All Installment Options'),
          1 => $this->t('Single Payment'),
          2 => $this->t('Up to 2 Installments'),
          3 => $this->t('Up to 3 Installments'),
          4 => $this->t('Up to 4 Installments'),
          5 => $this->t('Up to 5 Installments'),
          6 => $this->t('Up to 6 Installments'),
          7 => $this->t('Up to 7 Installments'),
          8 => $this->t('Up to 8 Installments'),
          9 => $this->t('Up to 9 Installments'),
          10 => $this->t('Up to 10 Installments'),
          11 => $this->t('Up to 11 Installments'),
          12 => $this->t('Up to 12 Installments'),
        ],
      ];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $config = $this->config('paytr_payment.settings');
    foreach ($this->fetchCategories() as $term) {
      $config->set('paytr_payment.installment_'.$term->tid, $form_state->getValue('installment_'.$term->tid) ?? "0");
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

  protected function getEditableConfigNames(): array
  {
    return [
      'paytr_payment.settings',
    ];
  }

  private function fetchCategories()
  {
    return Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('product_collections');
  }
}
