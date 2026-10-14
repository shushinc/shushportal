<?php

namespace Drupal\metabase\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * End customer selector shown above the embedded dashboards.
 *
 * Submits as a plain GET (?end_customer=<tid>) so the controller can re-sign
 * the Metabase JWT. Options are escaped by Twig, so term names such as
 * "<script>..." are rendered as text.
 */
class EndCustomerFilterForm extends FormBase {

  public function getFormId() {
    return 'metabase_end_customer_filter';
  }

  public function buildForm(array $form, FormStateInterface $form_state, array $options = [], ?int $selected = NULL) {
    $form['#method'] = 'get';
    $form['#token'] = FALSE;
    $form['#after_build'][] = '::removeFormMeta';
    $form['#attributes']['class'][] = 'metabase-end-customer-filter';

    $form['end_customer'] = [
      '#type' => 'select',
      '#title' => $this->t('End Customers'),
      '#options' => ['' => $this->t('- All End Customers')] + $options,
      '#default_value' => $selected ?? '',
      '#attributes' => ['onchange' => 'this.form.submit()'],
    ];
    $form['apply'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#name' => '',
    ];
    return $form;
  }

  /**
   * Keeps the GET URL clean (no form_id / form_build_id / op).
   */
  public function removeFormMeta(array $form) {
    unset($form['form_token'], $form['form_build_id'], $form['form_id']);
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
