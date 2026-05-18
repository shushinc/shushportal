<?php

namespace Drupal\sam_google\Plugin\SsoProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sam\SsoAppInterface;
use Drupal\sam_oidc\Plugin\SsoProvider\AbstractOidcProvider;

/**
 * Google OpenID Connect SSO provider.
 *
 * @SsoProvider(
 *   id = "google",
 *   name = @Translation("Google"),
 *   description = @Translation("Google OpenID Connect authentication provider"),
 *   weight = 0
 * )
 */
final class GoogleProvider extends AbstractOidcProvider {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->t('Google');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->t('Authenticate users using Google OpenID Connect.');
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationUrl(array $options = []): string {
    return Url::fromRoute('sam.authenticate')->toString();
  }

  /**
   * {@inheritdoc}
   */
  protected function getIssuer(SsoAppInterface $app): string {
    return (string) $app->getSetting('issuer_url');
  }

  /**
   * {@inheritdoc}
   */
  protected function getClientId(SsoAppInterface $app): string {
    return (string) $app->getSetting('client_id');
  }

  /**
   * {@inheritdoc}
   */
  protected function getClientSecret(SsoAppInterface $app): string {
    return (string) $app->getSetting('client_secret');
  }

  /**
   * {@inheritdoc}
   */
  protected function getCallbackUri(SsoAppInterface $app): string {
    return (string) ($app->getSetting('callback_uri') ?: $app->getSetting('callback_url'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getHostedDomain(SsoAppInterface $app): string|NULL {
    return (string) $app->getDomain();
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigured(): bool {
    // V1: assume provider is configured.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationForm(array $form, FormStateInterface $form_state, SsoAppInterface $app = NULL): array {
    $config = $this->configFactory->get('sam_google.settings');
    
    $settings = $app ? $app->getSettings() : [];
    $formSettings = [
      'issuer_url' => [
        '#type' => 'textfield',
        '#title' => $this->t('Issuer URL'),
        '#default_value' => $settings['issuer_url'] ?? '',
      ],
      'client_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Client ID'),
        '#default_value' => $settings['client_id'] ?? '',
      ],
      'client_secret' => [
        '#type' => 'textfield',
        '#title' => $this->t('Client Secret'),
        '#default_value' => $settings['client_secret'] ?? '',
      ],
      'callback_uri' => [
        '#type' => 'textfield',
        '#title' => $this->t('Callback URI'),
        '#default_value' => $settings['callback_uri'] ?? '',
      ],
    ]; 
    return $formSettings;
  }
  

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $issuer_url = $form_state->getValue(['settings', 'details', 'issuer_url']);
    $client_id = $form_state->getValue(['settings', 'details', 'client_id']);
    $client_secret = $form_state->getValue(['settings', 'details', 'client_secret']);
    $callback_uri = $form_state->getValue(['settings', 'details', 'callback_uri']);

    if (empty($issuer_url)) {
      $form_state->setErrorByName('settings][details][issuer_url', $this->t('Google APP Issuer URL is required.'));
    }

    if (empty($client_id)) {
      $form_state->setErrorByName('settings][details][client_id', $this->t('Google Client ID is required.'));
    }

    if (empty($client_secret)) {
      $form_state->setErrorByName('settings][details][client_secret', $this->t('Google Client Secret is required.'));
    }

    if (empty($callback_uri)) {
      $form_state->setErrorByName('settings][details][callback_uri', $this->t('The callback URI is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, SsoAppInterface $soApp = NULL): array {
    return [
      'issuer_url' => $form_state->getValue(['settings', 'details', 'issuer_url']),
      'client_id' => $form_state->getValue(['settings', 'details', 'client_id']),
      'client_secret' => $form_state->getValue(['settings', 'details', 'client_secret']),
      'callback_uri' => $form_state->getValue(['settings', 'details', 'callback_uri']),
    ];
  }

}
