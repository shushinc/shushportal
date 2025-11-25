<?php

declare(strict_types=1);

namespace Drupal\zcs_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure zcs_custom settings for this site.
 */
final class PortalSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_custom_portal_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['zcs_custom.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    if (\Drupal::moduleHandler()->moduleExists('zcs_kong')) {
      $form['kong_details'] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $this->t('Kong Details'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#description' => t('Add the configuration for kong details.'),
        '#tree' => TRUE,
      ];
      $form['kong_details']['kong_endpoint'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#title' => $this->t('Kong Endpoint'),
        '#default_value' => $this->config('zcs_custom.settings')->get('kong_endpoint'),
      ];
    }
    else {
      $form['aws_details'] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $this->t('Aws Details'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#description' => t('Add the configuration for AWS details.'),
        '#tree' => TRUE,
      ];
      $form['aws_details']['aws_access_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('AWS Access key'),
        '#default_value' => $this->config('zcs_custom.settings')->get('aws_access_key'),
      ];

      $form['aws_details']['aws_secret_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('AWS Secret key'),
        '#default_value' => $this->config('zcs_custom.settings')->get('aws_secret_key'),
      ];

      $form['aws_details']['aws_region'] = [
        '#type' => 'textfield',
        '#title' => $this->t('AWS Region'),
        '#default_value' => $this->config('zcs_custom.settings')->get('aws_region'),
      ];
      $form['aws_details']['user_pool_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('User Pool ID'),
        '#default_value' => $this->config('zcs_custom.settings')->get('user_pool_id'),
      ];
      $form['aws_details']['access_token_validity'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Access Token Validity'),
        '#default_value' => $this->config('zcs_custom.settings')->get('access_token_validity'),
      ];
      $form['aws_details']['supported_identity_providers'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Identity Providers'),
        '#default_value' => $this->config('zcs_custom.settings')->get('supported_identity_providers'),
      ];
      $form['aws_details']['allowed_oauth_flows'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Oauth Flows'),
        '#default_value' => $this->config('zcs_custom.settings')->get('allowed_oauth_flows'),
        '#description' => t('Provide values comma seperated eg: client_credentials, ouath.'),
      ];
      $form['aws_details']['allowed_oauth_scopes'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Oauth Scopes'),
        '#default_value' => $this->config('zcs_custom.settings')->get('allowed_oauth_scopes'),
        '#description' => t('Provide values comma seperated eg: resourcetype/read, resourcetype/write.'),
      ];
    }
    //$defaultCurrency = 'en_US';
    $lists = require __DIR__ . '/../../resources/currencies.php';
    // to fetch currencies.
    $currencies = [];
    foreach ($lists as $list) {
      if (!empty($list['locale'])) {
        $currencies[$list['locale']] = $list['currency'] .' ('. $list['alphabeticCode'] .')';
      }
    }
    $form['currency_settings'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Currency'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('Add the currency configuration'),
      '#tree' => TRUE,
    ];
    $form['currency_settings']['currency'] = [
      '#type' => 'select',
      '#options' => $currencies,
      '#default_value' => $this->config('zcs_custom.settings')->get('currency') ?? 'en_US',
      '#description' => t('Provide a currency value.'),
    ];

    $form['retail_markup_limit_data'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('RMP Limit'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('Set the limit for Analytics records to calculate retail markup percentage (eg: -6 months or 12 months)'),
      '#tree' => TRUE,
    ];
    $form['retail_markup_limit_data']['rmp_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Limit'),
      '#default_value' => $this->config('zcs_custom.settings')->get('rmp_limit'),
    ];
    $form['pricing_api_endpoint'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Proposed API Pricing Endpoint'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('Configure Proposed API Pricing Endpoint Details'),
      '#tree' => TRUE,
    ];
    $form['pricing_api_endpoint']['proposed_api_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Proposed API Endpoint'),
      '#default_value' => $this->config('zcs_custom.settings')->get('proposed_api_endpoint'),
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
   $config = $this->config('zcs_custom.settings');
   if (!empty($form_state->getValue('kong_details')['kong_endpoint'])) {
     $config->set('kong_endpoint', $form_state->getValue('kong_details')['kong_endpoint']);
   }
   else {
     $config->set('aws_access_key', $form_state->getValue('aws_details')['aws_access_key']);
     $config->set('aws_secret_key', $form_state->getValue('aws_details')['aws_secret_key']);
     $config->set('aws_region', $form_state->getValue('aws_details')['aws_region']);
     $config->set('user_pool_id', $form_state->getValue('aws_details')['user_pool_id']);
     $config->set('access_token_validity', $form_state->getValue('aws_details')['access_token_validity']);
     $config->set('supported_identity_providers', $form_state->getValue('aws_details')['supported_identity_providers']);
     $config->set('allowed_oauth_flows', $form_state->getValue('aws_details')['allowed_oauth_flows']);
     $config->set('allowed_oauth_scopes', $form_state->getValue('aws_details')['allowed_oauth_scopes']);
   }
   $config->set('currency', $form_state->getValue('currency_settings')['currency']);
   $config->set('rmp_limit', $form_state->getValue('retail_markup_limit_data')['rmp_limit']);
   $config->set('proposed_api_endpoint', $form_state->getValue('pricing_api_endpoint')['proposed_api_endpoint']);
   $config->save();
   parent::submitForm($form, $form_state);
  }

}
