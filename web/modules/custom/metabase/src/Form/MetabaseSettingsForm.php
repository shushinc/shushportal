<?php

namespace Drupal\metabase\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Metabase settings for this site.
 */
class MetabaseSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'metabase.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'metabase_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('metabase.settings');

    $form['api_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Metabase API Settings'),
      '#open' => TRUE,
    ];

    $form['api_settings']['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('The base URL of the Metabase API (e.g., https://metabase.example.com).'),
      '#default_value' => $config->get('api.base_url'),
      '#required' => TRUE,
    ];

    $form['api_settings']['api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Token'),
      '#description' => $this->t('The API authentication token for Metabase.'),
      '#default_value' => $config->get('api.api_token'),
      '#required' => TRUE,
    ];

    $form['embeding_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Embedding Settings'),
      '#open' => TRUE,
    ];

    $form['embeding_settings']['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Metabase Secret Key'),
      '#description' => $this->t('The secret key from your Metabase embedding settings.'),
      '#default_value' => $config->get('embeding.api_token'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $base_url = $form_state->getValue('base_url');

    // Ensure the base URL does not end with a trailing slash.
    if (substr($base_url, -1) === '/') {
      $form_state->setValueForElement($form['api_settings']['base_url'], rtrim($base_url, '/'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('metabase.settings')
      ->set('api.base_url', $form_state->getValue('base_url'))
      ->set('api.api_token', $form_state->getValue('api_token'))
      ->set('embeding.api_token', $form_state->getValue('secret_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
