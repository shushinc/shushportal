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
      '#description' => $this->t("When enabled, the SAM module will intercept user login and redirect authentication to the configured SSO provider that matches the user's email domain."),
      '#default_value' => (bool) $config->get('sso_active'),
    ];

    // ------------------------------------------------------------------
    // Provider selection
    // ------------------------------------------------------------------

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $sso_active = (bool) $form_state->getValue('sso_active');

    if (!$sso_active) {
      return;
    }
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('sam.settings');

    $sso_active = (bool) $form_state->getValue('sso_active');

    $config
      ->set('sso_active', $sso_active)
      ->save();

    parent::submitForm($form, $form_state);
  }


}