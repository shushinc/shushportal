<?php

namespace Drupal\sam\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sam\SsoProviderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Multi SSO module.
 */
class SamConfigForm extends ConfigFormBase {

  /**
   * The SSO provider manager.
   *
   * @var \Drupal\sam\SsoProviderManager
   */
  protected $providerManager;

  /**
   * Constructs a new SamConfigForm object.
   *
   * @param \Drupal\sam\SsoProviderManager $provider_manager
   *   The SSO provider manager.
   */
  public function __construct(SsoProviderManager $provider_manager) {
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sam.provider_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['sam.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sam_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sam.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Settings'),
      '#open' => TRUE,
    ];

    $form['general']['auto_create_users'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically create users'),
      '#description' => $this->t('If enabled, new users will be created automatically when they log in via SSO for the first time.'),
      '#default_value' => $config->get('auto_create_users'),
    ];

    $form['general']['default_redirect'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default redirect path'),
      '#description' => $this->t('Path to redirect users after successful SSO login. Leave empty to redirect to user profile.'),
      '#default_value' => $config->get('default_redirect'),
    ];

    $form['providers'] = [
      '#type' => 'details',
      '#title' => $this->t('SSO Providers'),
      '#open' => TRUE,
    ];

    $provider_options = $this->providerManager->getProviderOptions();

    $form['providers']['enabled_providers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Enabled providers'),
      '#options' => $provider_options,
      '#default_value' => $config->get('enabled_providers') ?: [],
      '#description' => $this->t('Select which SSO providers should be available for authentication.'),
    ];

    // Add provider-specific configuration forms.
    foreach ($provider_options as $provider_id => $provider_name) {
      $provider_instance = $this->providerManager->getProvider($provider_id);
      if ($provider_instance) {
        $form['provider_' . $provider_id] = [
          '#type' => 'details',
          '#title' => $this->t('@provider Configuration', ['@provider' => $provider_name]),
          '#open' => FALSE,
          '#states' => [
            'visible' => [
              ':input[name="enabled_providers[' . $provider_id . ']"]' => ['checked' => TRUE],
            ],
          ],
        ];

        $provider_form = $provider_instance->getConfigurationForm($form, $form_state);
        $form['provider_' . $provider_id] += $provider_form;
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate provider-specific configurations.
    $enabled_providers = array_filter($form_state->getValue('enabled_providers'));

    foreach ($enabled_providers as $provider_id) {
      $provider_instance = $this->providerManager->getProvider($provider_id);
      if ($provider_instance) {
        $provider_instance->validateConfigurationForm($form, $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sam.settings');

    $enabled_providers = array_filter($form_state->getValue('enabled_providers'));

    $config
      ->set('enabled_providers', array_keys($enabled_providers))
      ->set('auto_create_users', $form_state->getValue('auto_create_users'))
      ->set('default_redirect', $form_state->getValue('default_redirect'))
      ->save();

    // Save provider-specific configurations.
    foreach ($enabled_providers as $provider_id) {
      $provider_instance = $this->providerManager->getProvider($provider_id);
      if ($provider_instance) {
        $provider_instance->submitConfigurationForm($form, $form_state);
      }
    }

    parent::submitForm($form, $form_state);
  }

}
