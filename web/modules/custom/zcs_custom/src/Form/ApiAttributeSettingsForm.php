<?php

declare(strict_types=1);

namespace Drupal\zcs_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure API settings for this site.
 */
final class ApiAttributeSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'api_attribute_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['zcs_custom.api_attribute_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['network_authentication_pricing_over_time'] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $this->t('Network Authentication Pricing Over Time'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#description' => t('Add the configuration for Network Authentication Pricing Over time.'),
        '#tree' => TRUE,
      ];
    $form['network_authentication_pricing_over_time']['effective_date_1'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Effective Date 1'),
        '#default_value' => $this->config('zcs_custom.api_attribute_settings')->get('effective_date_1'),
    ];
    $form['network_authentication_pricing_over_time']['effective_date_2'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Effective Date 2'),
        '#default_value' => $this->config('zcs_custom.api_attribute_settings')->get('effective_date_2'),
    ];
    $form['network_authentication_pricing_over_time']['effective_date_3'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Effective Date 3'),
        '#default_value' => $this->config('zcs_custom.api_attribute_settings')->get('effective_date_3'),
    ];

    $form['change_network_authentication_pricing'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Change Network Authentication Pricing'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('Add the configuration for Network Authentication Pricing Over time.'),
      '#tree' => TRUE,
    ];
    $form['change_network_authentication_pricing']['currency'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currency'),
      '#default_value' => $this->config('zcs_custom.api_attribute_settings')->get('currency'),
    ];
    $form['change_network_authentication_pricing']['effective_date'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Effective Date'),
      '#default_value' => $this->config('zcs_custom.api_attribute_settings')->get('effective_date'),
    ];

    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if ($form_state->getValue('example') === 'wrong') {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('The value is not correct.'),
    //     );
    //   }
    // @endcode
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
   $config = $this->config('zcs_custom.api_attribute_settings');
   $config->set('effective_date_1', $form_state->getValue('network_authentication_pricing_over_time')['effective_date_1']);
   $config->set('effective_date_2', $form_state->getValue('network_authentication_pricing_over_time')['effective_date_2']);
   $config->set('effective_date_3', $form_state->getValue('network_authentication_pricing_over_time')['effective_date_3']);
   $config->set('effective_date', $form_state->getValue('change_network_authentication_pricing')['effective_date']);
   $config->set('currency', $form_state->getValue('change_network_authentication_pricing')['currency']);
   $config->save();
   parent::submitForm($form, $form_state);
  }

}
