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

    $form['general']['sso_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable SSO authentication'),
      '#description' => $this->t('When enabled, the SAM module will intercept user login and redirect authentication to the configured SSO provider.'),
      '#default_value' => (bool) $config->get('sso_active'),
    ];

    $form['general']['default_redirect'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default redirect path'),
      '#description' => $this->t('Internal path to redirect users after successful SSO login. Leave empty to redirect to the user profile.'),
      '#default_value' => $config->get('default_redirect'),
    ];

    // ------------------------------------------------------------------
    // Provider selection
    // ------------------------------------------------------------------

    $provider_options = $this->providerManager->getProviderOptions();

    $form['provider'] = [
      '#type' => 'details',
      '#title' => $this->t('SSO Provider'),
      '#open' => TRUE,
    ];

    $form['provider']['active_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Active SSO provider'),
      '#options' => $provider_options,
      '#empty_option' => $this->t('- Select a provider -'),
      '#default_value' => $config->get('active_provider'),
      '#states' => [
        'visible' => [
          ':input[name="sso_active"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="sso_active"]' => ['checked' => TRUE],
        ],
      ],
      '#description' => $this->t('Select which SSO provider will handle authentication when SSO is enabled.'),
    ];

    // ------------------------------------------------------------------
    // Provider-specific configuration (ONLY for the selected provider)
    // ------------------------------------------------------------------

    $active_provider = $form_state->getValue('active_provider') ?? $config->get('active_provider');

    if ($active_provider && $this->providerManager->hasDefinition($active_provider)) {
      $provider_instance = $this->providerManager->getProvider($active_provider);

      if ($provider_instance) {
        $form['provider_config'] = [
          '#type' => 'details',
          '#title' => $this->t('@provider configuration', [
            '@provider' => $provider_options[$active_provider],
          ]),
          '#open' => TRUE,
          '#states' => [
            'visible' => [
              ':input[name="sso_active"]' => ['checked' => TRUE],
            ],
          ],
        ];

        $provider_form = $provider_instance->getConfigurationForm($form, $form_state);
        $form['provider_config'] += $provider_form;
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $sso_active = (bool) $form_state->getValue('sso_active');
    $active_provider = $form_state->getValue('active_provider');

    // If SSO is enabled, an active provider is mandatory.
    if ($sso_active && empty($active_provider)) {
      $form_state->setErrorByName(
        'active_provider',
        $this->t('You must select an active SSO provider when SSO is enabled.')
      );
      return;
    }

    // Validate provider-specific configuration (ONLY the active provider).
    if ($sso_active && $active_provider) {
      $provider_instance = $this->providerManager->getProvider($active_provider);

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

    $sso_active = (bool) $form_state->getValue('sso_active');
    $active_provider = $form_state->getValue('active_provider');

    $config
      ->set('sso_active', $sso_active)
      ->set('active_provider', $active_provider)
      ->set('default_redirect', $form_state->getValue('default_redirect'))
      ->save();

    // Persist provider-specific configuration (ONLY the active provider).
    if ($sso_active && $active_provider) {
      $provider_instance = $this->providerManager->getProvider($active_provider);

      if ($provider_instance) {
        $provider_instance->submitConfigurationForm($form, $form_state);
      }
    }

    parent::submitForm($form, $form_state);
  }


}