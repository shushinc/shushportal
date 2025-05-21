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

    $form['metabase_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Metabase Settings'),
      '#open' => TRUE,
    ];

    $form['metabase_details']['metabase_internal_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Internal Base URL'),
      '#description' => $this->t('The base URL of the Metabase API (e.g., https://metabase.example.com).'),
      '#default_value' => $config->get('metabase.internal.base_url'),
      '#required' => TRUE,
    ];

    $form['metabase_details']['metabase_external_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('External Base URL'),
      '#description' => $this->t('The base URL of the Metabase API (e.g., https://metabase.example.com).'),
      '#default_value' => $config->get('metabase.external.base_url'),
      '#required' => TRUE,
    ];

    $form['embeding_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Embedding Settings'),
      '#open' => TRUE,
    ];

    $form['embeding_settings']['embed_base_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#description' => $this->t('The base URL of the Metabase API (e.g., https://metabase.example.com).'),
      '#default_value' => $config->get('embeding.base_url'),
      '#required' => TRUE,
    ];

    $form['embeding_settings']['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Metabase Secret Key'),
      '#description' => $this->t('The secret key from your Metabase embedding settings.'),
      '#default_value' => $config->get('embeding.api_token'),
      '#required' => TRUE,
    ];

    $form['embeding_settings']['top_dashboard_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dashboard ID (Top - Carrier Admin)'),
      '#description' => $this->t('Enter the default Dashboard ID.'),
      '#default_value' => $config->get('embeding.dashboard.top'),
      '#required' => TRUE,
    ];

    $form['embeding_settings']['main_dashboard_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dashboard ID (Main - Carrier Admin)'),
      '#description' => $this->t('Enter the default Dashboard ID.'),
      '#default_value' => $config->get('embeding.dashboard.main'),
      '#required' => TRUE,
    ];

    $form['embeding_settings']['other_dashboard_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dashboard ID (Main - Other)'),
      '#description' => $this->t('Enter the default Dashboard ID.'),
      '#default_value' => $config->get('embeding.dashboard.other'),
      '#required' => TRUE,
    ];

    $form['overwrite'] = [
      '#type' => 'details',
      '#title' => $this->t('Overwrite'),
      '#open' => TRUE,
    ];

    $form['overwrite']['css'] = [
      '#type' => 'textarea',
      '#title' => $this->t('CSS'),
      '#description' => $this->t('Enter the CSS to overwrite.'),
      '#default_value' => $config->get('overwrite.css'),
    ];

    $form['overwrite']['js'] = [
      '#type' => 'textarea',
      '#title' => $this->t('JavaScript'),
      '#description' => $this->t('Enter the JS to overwrite.'),
      '#default_value' => $config->get('overwrite.js'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('metabase.settings')
      ->set('metabase.internal.base_url', $form_state->getValue('metabase_internal_base_url'))
      ->set('metabase.external.base_url', $form_state->getValue('metabase_external_base_url'))
      ->set('embeding.base_url', $form_state->getValue('embed_base_url'))
      ->set('embeding.api_token', $form_state->getValue('secret_key'))
      ->set('embeding.dashboard.top', $form_state->getValue('top_dashboard_id'))
      ->set('embeding.dashboard.main', $form_state->getValue('main_dashboard_id'))
      ->set('embeding.dashboard.other', $form_state->getValue('other_dashboard_id'))
      ->set('overwrite.css', $form_state->getValue('css'))
      ->set('overwrite.js', $form_state->getValue('js'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
