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
    $this->config('zcs_custom.settings')
      ->set('kong_endpoint', $form_state->getValue('kong_details')['kong_endpoint'])
      ->set('aws_access_key', $form_state->getValue('aws_details')['aws_access_key'])
      ->set('aws_secret_key', $form_state->getValue('aws_details')['aws_secret_key'])
      ->set('aws_region', $form_state->getValue('aws_details')['aws_region'])
      ->set('user_pool_id', $form_state->getValue('aws_details')['user_pool_id'])
      ->set('supported_identity_providers', $form_state->getValue('aws_details')['supported_identity_providers'])
      ->set('allowed_oauth_flows', $form_state->getValue('aws_details')['allowed_oauth_flows'])
      ->set('allowed_oauth_scopes', $form_state->getValue('aws_details')['allowed_oauth_scopes'])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
