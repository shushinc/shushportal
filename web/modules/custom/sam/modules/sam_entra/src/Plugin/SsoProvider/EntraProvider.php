<?php

namespace Drupal\sam_entra\Plugin\SsoProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sam\SsoAppInterface;
use Drupal\sam_oidc\Plugin\SsoProvider\AbstractOidcProvider;

/**
 * Microsoft Entra ID OpenID Connect SSO provider.
 *
 * @SsoProvider(
 *   id = "entra",
 *   label = @Translation("Microsoft Entra ID"),
 *   name = @Translation("Microsoft Entra ID"),
 *   description = @Translation("Microsoft Entra ID OpenID Connect authentication provider"),
 *   weight = 0
 * )
 */
final class EntraProvider extends AbstractOidcProvider {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return (string) $this->t('Microsoft Entra ID');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->t('Authenticate users using Microsoft Entra ID OpenID Connect.');
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
    return sprintf(
      'https://login.microsoftonline.com/%s/v2.0',
      $this->getTenantId($app)
    );
  }

  /**
   * Gets the Microsoft Entra ID discovery URL.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return string
   *   The OpenID Connect discovery URL.
   */
  protected function getDiscoveryUrl(SsoAppInterface $app): string {
    return sprintf(
      'https://login.microsoftonline.com/%s/v2.0/.well-known/openid-configuration',
      $this->getTenantId($app)
    );
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
    return NULL;
  }

  /**
   * Extracts an email address from Microsoft Entra ID token claims.
   *
   * @param array $claims
   *   The decoded ID token claims.
   *
   * @return string|null
   *   The email address, or NULL when none is available.
   */
  protected function extractEmailFromClaims(array $claims): ?string {
    $email = $claims['email'] ?? $claims['preferred_username'] ?? NULL;

    return is_string($email) && $email !== '' ? $email : NULL;
  }

  /**
   * Validates Microsoft Entra ID-specific token claims.
   *
   * @param array $claims
   *   The decoded ID token claims.
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @throws \InvalidArgumentException
   *   Thrown when required provider-specific claims are invalid.
   */
  protected function validateProviderSpecificClaims(array $claims, SsoAppInterface $app): void {
    $issuer = $claims['iss'] ?? NULL;

    if ($issuer !== $this->getIssuer($app)) {
      throw new \InvalidArgumentException('Invalid Microsoft Entra ID issuer claim.');
    }

    if ($this->extractEmailFromClaims($claims) === NULL) {
      throw new \InvalidArgumentException('Microsoft Entra ID token does not contain an email claim.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigured(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationForm(array $form, FormStateInterface $form_state, SsoAppInterface $app = NULL): array {
    $settings = $app ? $app->getSettings() : [];

    return [
      'tenant_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Tenant ID'),
        '#description' => $this->t('The Microsoft Entra ID tenant ID, such as a tenant UUID or verified tenant domain.'),
        '#default_value' => $settings['tenant_id'] ?? '',
        '#required' => TRUE,
      ],
      'client_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Client ID'),
        '#default_value' => $settings['client_id'] ?? '',
        '#required' => TRUE,
      ],
      'client_secret' => [
        '#type' => 'textfield',
        '#title' => $this->t('Client Secret'),
        '#default_value' => $settings['client_secret'] ?? '',
        '#required' => TRUE,
      ],
      'callback_uri' => [
        '#type' => 'textfield',
        '#title' => $this->t('Callback URI'),
        '#default_value' => $settings['callback_uri'] ?? $settings['callback_url'] ?? '',
        '#required' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $tenant_id = $form_state->getValue(['settings', 'details', 'tenant_id']);
    $client_id = $form_state->getValue(['settings', 'details', 'client_id']);
    $client_secret = $form_state->getValue(['settings', 'details', 'client_secret']);
    $callback_uri = $form_state->getValue(['settings', 'details', 'callback_uri']);

    if (empty($tenant_id)) {
      $form_state->setErrorByName('settings][details][tenant_id', $this->t('Microsoft Entra ID Tenant ID is required.'));
    }

    if (empty($client_id)) {
      $form_state->setErrorByName('settings][details][client_id', $this->t('Microsoft Entra ID Client ID is required.'));
    }

    if (empty($client_secret)) {
      $form_state->setErrorByName('settings][details][client_secret', $this->t('Microsoft Entra ID Client Secret is required.'));
    }

    if (empty($callback_uri)) {
      $form_state->setErrorByName('settings][details][callback_uri', $this->t('The callback URI is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, SsoAppInterface $ssoApp = NULL): array {
    return [
      'tenant_id' => $form_state->getValue(['settings', 'details', 'tenant_id']),
      'client_id' => $form_state->getValue(['settings', 'details', 'client_id']),
      'client_secret' => $form_state->getValue(['settings', 'details', 'client_secret']),
      'callback_uri' => $form_state->getValue(['settings', 'details', 'callback_uri']),
    ];
  }

  /**
   * Gets the configured Microsoft Entra ID tenant ID.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return string
   *   The normalized tenant ID.
   */
  private function getTenantId(SsoAppInterface $app): string {
    return trim(trim((string) $app->getSetting('tenant_id')), '/');
  }

}
