<?php

namespace Drupal\sam_oidc\Plugin\SsoProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sam\SsoAppInterface;

/**
 * Microsoft Entra Consumer OpenID Connect SSO provider.
 *
 * @SsoProvider(
 *   id = "entra_consumer",
 *   label = @Translation("Microsoft Entra Consumer")
 * )
 */
final class EntraConsumerProvider extends AbstractOidcProvider {

  /**
   * Microsoft consumer authority alias used for discovery.
   */
  private const CONSUMER_AUTHORITY_ALIAS = 'consumers';

  /**
   * Microsoft login issuer base URL.
   */
  private const MICROSOFT_LOGIN_BASE_URL = 'https://login.microsoftonline.com';

  /**
   * Microsoft login issuer host.
   */
  private const MICROSOFT_LOGIN_HOST = 'login.microsoftonline.com';

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return (string) $this->t('Microsoft Entra Consumer');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->t('Authenticate users using Microsoft personal accounts, including Outlook.com, Hotmail.com, and Live.com accounts.');
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
    return $this->buildIssuer(self::CONSUMER_AUTHORITY_ALIAS);
  }

  /**
   * Gets the Microsoft Entra Consumer discovery URL.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return string
   *   The OpenID Connect discovery URL.
   */
  protected function getDiscoveryUrl(SsoAppInterface $app): string {
    return self::MICROSOFT_LOGIN_BASE_URL . '/' . self::CONSUMER_AUTHORITY_ALIAS . '/v2.0/.well-known/openid-configuration';
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
    return Url::fromRoute(
      'sam.callback',
      ['provider' => $app->getProvider()],
      ['absolute' => TRUE]
    )->toString();
  }

  /**
   * {@inheritdoc}
   */
  protected function getHostedDomain(SsoAppInterface $app): string|NULL {
    return NULL;
  }

  /**
   * Extracts an email address from Microsoft Entra Consumer token claims.
   *
   * @param array $claims
   *   The decoded ID token claims.
   *
   * @return string|null
   *   The email address, or NULL when none is available.
   */
  protected function extractEmailFromClaims(array $claims): ?string {
    return $this->extractPersonalAccountEmail($claims);
  }

  /**
   * Validates Microsoft Entra Consumer-specific token claims.
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
    $this->validateConsumerAccountClaims($claims);
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldValidateIssuerWithTokenService(SsoAppInterface $app): bool {
    return FALSE;
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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $client_id = $form_state->getValue(['settings', 'details', 'client_id']);
    $client_secret = $form_state->getValue(['settings', 'details', 'client_secret']);

    if (empty($client_id)) {
      $form_state->setErrorByName('settings][details][client_id', $this->t('Microsoft Entra Consumer Client ID is required.'));
    }

    if (empty($client_secret)) {
      $form_state->setErrorByName('settings][details][client_secret', $this->t('Microsoft Entra Consumer Client Secret is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, SsoAppInterface $ssoApp = NULL): array {
    return [
      'client_id' => $form_state->getValue(['settings', 'details', 'client_id']),
      'client_secret' => $form_state->getValue(['settings', 'details', 'client_secret']),
    ];
  }

  /**
   * Validates Microsoft consumer account token claims.
   *
   * Consumer-account issuer validation is intentionally isolated in this
   * provider so the enterprise Entra provider keeps its tenant-specific
   * behavior unchanged. The issuer is validated dynamically using the token's
   * tenant ID claim because Microsoft may issue tokens with a concrete tenant
   * issuer even when discovery uses a non-tenant-specific authority alias.
   *
   * @param array $claims
   *   The decoded ID token claims.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the token does not contain valid Microsoft consumer account
   *   claims.
   */
  private function validateConsumerAccountClaims(array $claims): void {
    $issuer = $claims['iss'] ?? NULL;
    $tenant_id = $claims['tid'] ?? NULL;

    if (!is_string($tenant_id) || trim($tenant_id) === '') {
      throw new \InvalidArgumentException('Invalid Microsoft Entra Consumer tenant claim.');
    }

    if (!is_string($issuer) || !$this->isValidDynamicIssuer($issuer, $tenant_id)) {
      throw new \InvalidArgumentException('Invalid Microsoft Entra Consumer issuer claim.');
    }

    if ($this->extractPersonalAccountEmail($claims) === NULL) {
      throw new \InvalidArgumentException('Microsoft Entra Consumer token does not contain an email claim.');
    }
  }

  /**
   * Extracts the email address from Microsoft personal account claims.
   *
   * Microsoft personal, Outlook.com, Hotmail.com, and Live.com accounts may
   * expose the user's sign-in address through different claim names depending
   * on account configuration. This method keeps that fallback logic isolated to
   * the consumer provider.
   *
   * @param array $claims
   *   The decoded ID token claims.
   *
   * @return string|null
   *   The email address, or NULL when none is available.
   */
  private function extractPersonalAccountEmail(array $claims): ?string {
    $email =
      $claims['email']
      ?? $claims['preferred_username']
      ?? $claims['upn']
      ?? NULL;

    return is_string($email) && $email !== '' ? $email : NULL;
  }

  /**
   * Validates a Microsoft issuer claim dynamically.
   *
   * Microsoft ID tokens may be issued with the resolved tenant ID in the issuer
   * URL, even when discovery uses a non-tenant-specific authority alias such as
   * "consumers". This method validates the issuer against the token's own
   * tenant ID claim and keeps that behavior isolated to the consumer provider.
   *
   * @param string $issuer
   *   The issuer claim from the ID token.
   * @param string $tenant_id
   *   The tenant ID claim from the ID token.
   *
   * @return bool
   *   TRUE if the issuer is valid for the resolved Microsoft tenant.
   */
  private function isValidDynamicIssuer(string $issuer, string $tenant_id): bool {
    $tenant_id = trim($tenant_id);

    if ($tenant_id === '') {
      return FALSE;
    }

    $issuer_parts = parse_url(rtrim($issuer, '/'));

    if (!is_array($issuer_parts)) {
      return FALSE;
    }

    if (($issuer_parts['scheme'] ?? '') !== 'https') {
      return FALSE;
    }

    if (($issuer_parts['host'] ?? '') !== self::MICROSOFT_LOGIN_HOST) {
      return FALSE;
    }

    $path = $issuer_parts['path'] ?? '';

    if (!is_string($path) || !preg_match('#^/([^/]+)/v2\.0$#', $path, $matches)) {
      return FALSE;
    }

    $issuer_tenant = rawurldecode($matches[1]);

    return $issuer_tenant === $tenant_id || $issuer_tenant === self::CONSUMER_AUTHORITY_ALIAS;
  }

  /**
   * Builds a Microsoft Entra v2 issuer URL for a tenant identifier.
   *
   * @param string $tenant_id
   *   The Microsoft tenant identifier or authority alias.
   *
   * @return string
   *   The Microsoft Entra v2 issuer URL.
   */
  private function buildIssuer(string $tenant_id): string {
    return self::MICROSOFT_LOGIN_BASE_URL . '/' . trim($tenant_id, '/') . '/v2.0';
  }

}
