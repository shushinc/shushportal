<?php

namespace Drupal\metalite\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a configuration form for Metalite settings.
 */
class MetaliteSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['metalite.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'metalite_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('metalite.settings');

    $form['remote_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Remote Base URL'),
      '#description' => $this->t('Enter the base URL for the Metalite API.'),
      '#default_value' => $config->get('remote_base_url'),
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('Enter your Metalite API key.'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
    ];

    $form['dashboard_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dashboard ID'),
      '#description' => $this->t('Enter the default Dashboard ID.'),
      '#default_value' => $config->get('dashboard_id'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $remote_base_url = $form_state->getValue('remote_base_url');
    if (!empty($remote_base_url) && !filter_var($remote_base_url, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('remote_base_url', $this->t('The remote base URL must be a valid URL.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('metalite.settings')
      ->set('remote_base_url', $form_state->getValue('remote_base_url'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('dashboard_id', $form_state->getValue('dashboard_id'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
