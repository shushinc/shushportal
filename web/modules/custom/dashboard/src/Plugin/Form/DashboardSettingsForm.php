<?php

namespace Drupal\dashboard\Plugin\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Dashboard settings.
 */
class DashboardSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dashboard_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dashboard.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('dashboard.settings');

    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General Settings'),
    ];

    $form['general']['refresh_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Auto-refresh interval (seconds)'),
      '#description' => $this->t('Set to 0 to disable auto-refresh.'),
      '#default_value' => $config->get('refresh_interval') ?? 300,
      '#min' => 0,
      '#step' => 1,
    ];

    $form['general']['default_date_range'] = [
      '#type' => 'select',
      '#title' => $this->t('Default date range'),
      '#options' => [
        '7' => $this->t('Last 7 days'),
        '30' => $this->t('Last 30 days'),
        '90' => $this->t('Last 90 days'),
        '365' => $this->t('Last year'),
      ],
      '#default_value' => $config->get('default_date_range') ?? '30',
    ];

    $form['charts'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Chart Settings'),
    ];

    $form['charts']['enable_animations'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable chart animations'),
      '#default_value' => $config->get('enable_animations') ?? TRUE,
    ];

    $form['charts']['chart_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Chart height (pixels)'),
      '#default_value' => $config->get('chart_height') ?? 400,
      '#min' => 200,
      '#max' => 800,
      '#step' => 50,
    ];

    $form['api'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Settings'),
    ];

    $form['api']['cache_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache duration (seconds)'),
      '#description' => $this->t('How long to cache dashboard data.'),
      '#default_value' => $config->get('cache_duration') ?? 3600,
      '#min' => 60,
      '#step' => 60,
    ];

    $form['permissions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Access Control'),
    ];

    $form['permissions']['restrict_by_role'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Restrict dashboard access by role'),
      '#default_value' => $config->get('restrict_by_role') ?? FALSE,
    ];

    $form['permissions']['allowed_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed roles'),
      '#options' => array_map(function ($role) {
        return $role->label();
      }, user_roles()),
      '#default_value' => $config->get('allowed_roles') ?? [],
      '#states' => [
        'visible' => [
          ':input[name="restrict_by_role"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dashboard.settings')
      ->set('refresh_interval', $form_state->getValue('refresh_interval'))
      ->set('default_date_range', $form_state->getValue('default_date_range'))
      ->set('enable_animations', $form_state->getValue('enable_animations'))
      ->set('chart_height', $form_state->getValue('chart_height'))
      ->set('cache_duration', $form_state->getValue('cache_duration'))
      ->set('restrict_by_role', $form_state->getValue('restrict_by_role'))
      ->set('allowed_roles', array_filter($form_state->getValue('allowed_roles')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
