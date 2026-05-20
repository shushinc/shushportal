<?php

namespace Drupal\sam_entra_consumer\Plugin\SsoProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sam\SsoAppInterface;
use Drupal\sam_oidc\Plugin\SsoProvider\AbstractOidcProvider;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\sam\Security\SamStateTokenService;
use Drupal\sam_oidc\Service\OidcDiscoveryService;
use Drupal\sam_oidc\Service\OidcTokenService;

/**
 * Microsoft Entra Consumer OpenID Connect SSO provider.
 *
 * @SsoProvider(
 *   id = "entra_consumer",
 *   label = @Translation("Microsoft Entra Consumer"),
 *   name = @Translation("Microsoft Entra Consumer"),
 *   description = @Translation("Microsoft OpenID Connect authentication provider to users with outlook.com, hotmail.com, and live.com accounts."),
 * )
 */
final class EntraConsumerProvider extends AbstractOidcProvider implements ContainerFactoryPluginInterface {


  /**
   * The Drupal Key repository service.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected KeyRepositoryInterface $keyRepository;
  /**
    * {@inheritdoc}
    */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    OidcDiscoveryService $discovery,
    SessionInterface $session,
    SamStateTokenService $state_token,
    OidcTokenService $token_service,
    EntityTypeManagerInterface $entity_type_manager,
    KeyRepositoryInterface $key_repository,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $config_factory,
      $discovery,
      $session,
      $state_token,
      $token_service,
      $entity_type_manager,
    );
    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('sam_oidc.discovery'),
      $container->get('session'),
      $container->get('sam.state_token'),
      $container->get('sam_oidc.token_service'),
      $container->get('entity_type.manager'),
      $container->get('key.repository'),
    );
  }

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
    return $this->buildIssuer($this->getConsumerAuthorityAlias($app), $this->getMicrosoftLoginBaseUrl($app));
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
    return $this->getMicrosoftLoginBaseUrl($app) . '/' . $this->getConsumerAuthorityAlias($app) . '/v2.0/.well-known/openid-configuration';
  }

  /**
   * {@inheritdoc}
   */
  protected function getClientId(SsoAppInterface $app): string {

    $key_name = trim((string) $app->getSetting('client_id_env_var'));

    if ($key_name === '') {
      throw new \RuntimeException(
        'Client ID Key name is not configured.'
      );
    }

    $key = $this->keyRepository->getKey($key_name);

    if ($key === NULL) {
      throw new \RuntimeException(sprintf(
        'Drupal Key "%s" was not found.',
        $key_name
      ));
    }

    $value = trim((string) $key->getKeyValue());

    if ($value === '') {
      throw new \RuntimeException(sprintf(
        'Drupal Key "%s" is empty.',
        $key_name
      ));
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function getClientSecret(SsoAppInterface $app): string {

    $key_name = trim((string) $app->getSetting('client_secret_env_var'));

    if ($key_name === '') {
      throw new \RuntimeException(
        'Client Secret Key name is not configured.'
      );
    }

    $key = $this->keyRepository->getKey($key_name);

    if ($key === NULL) {
      throw new \RuntimeException(sprintf(
        'Drupal Key "%s" was not found.',
        $key_name
      ));
    }

    $value = trim((string) $key->getKeyValue());

    if ($value === '') {
      throw new \RuntimeException(sprintf(
        'Drupal Key "%s" is empty.',
        $key_name
      ));
    }

    return $value;
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
    $this->validateConsumerAccountClaims($claims, $app);
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
      'client_id_env_var' => [
        '#type' => 'textfield',
        '#title' => $this->t('Drupal Key ID'),
        '#description' => $this->t('The Drupal Key entity ID that stores the Microsoft Entra Client ID.'),
        '#default_value' => $settings['client_id_env_var'] ?? '',
        '#required' => TRUE,
      ],
      'client_secret_env_var' => [
        '#type' => 'textfield',
        '#title' => $this->t('Drupal Key ID'),
        '#description' => $this->t('The Drupal Key entity ID that stores the Microsoft Entra Client Secret.'),
        '#default_value' => $settings['client_secret_env_var'] ?? '',
        '#required' => TRUE,
      ],
      'authority_alias' => [
        '#type' => 'textfield',
        '#title' => $this->t('Consumer authority alias'),
        '#description' => $this->t('The Microsoft Entra authority alias used for discovery and issuer generation. For Microsoft personal accounts this is usually "consumers".'),
        '#default_value' => $settings['authority_alias'] ?? '',
        '#required' => TRUE,
      ],
      'login_base_url' => [
        '#type' => 'textfield',
        '#title' => $this->t('Microsoft login base URL'),
        '#description' => $this->t('The base URL used to build the Microsoft Entra issuer and discovery URLs. For example: https://login.microsoftonline.com'),
        '#default_value' => $settings['login_base_url'] ?? '',
        '#required' => TRUE,
      ],
      'login_host' => [
        '#type' => 'textfield',
        '#title' => $this->t('Microsoft login issuer host'),
        '#description' => $this->t('The expected host in the issuer claim. This should match the host from the Microsoft login base URL. For example: login.microsoftonline.com'),
        '#default_value' => $settings['login_host'] ?? '',
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
    $client_id_env_var = trim((string) $form_state->getValue(['settings', 'details', 'client_id_env_var']));
    $client_secret_env_var = trim((string) $form_state->getValue(['settings', 'details', 'client_secret_env_var']));
    $authority_alias = trim((string) $form_state->getValue(['settings', 'details', 'authority_alias']));
    $login_base_url = trim((string) $form_state->getValue(['settings', 'details', 'login_base_url']));
    $login_host = trim((string) $form_state->getValue(['settings', 'details', 'login_host']));
    $callback_uri = $form_state->getValue(['settings', 'details', 'callback_uri']);

    $this->validateEnvironmentVariableNameField(
      $form_state,
      'client_id_env_var',
      $client_id_env_var,
      'The Client ID environment variable name is required.',
      'The Client ID environment variable name is invalid.',
      'The Client ID environment variable @env_var must be set.'
    );

    $this->validateEnvironmentVariableNameField(
      $form_state,
      'client_secret_env_var',
      $client_secret_env_var,
      'The Client Secret environment variable name is required.',
      'The Client Secret environment variable name is invalid.',
      'The Client Secret environment variable @env_var must be set.'
    );

    if ($authority_alias === '') {
      $form_state->setErrorByName('settings][details][authority_alias', $this->t('The Microsoft Entra Consumer authority alias is required.'));
    }
    elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $authority_alias)) {
      $form_state->setErrorByName('settings][details][authority_alias', $this->t('The Microsoft Entra Consumer authority alias may only contain letters, numbers, dots, underscores, and hyphens.'));
    }

    if ($login_base_url === '') {
      $form_state->setErrorByName('settings][details][login_base_url', $this->t('The Microsoft login base URL is required.'));
    }
    elseif (!filter_var($login_base_url, FILTER_VALIDATE_URL)) {
      $form_state->setErrorByName('settings][details][login_base_url', $this->t('The Microsoft login base URL must be a valid URL.'));
    }
    else {
      $login_base_url_parts = parse_url($login_base_url);

      if (($login_base_url_parts['scheme'] ?? '') !== 'https') {
        $form_state->setErrorByName('settings][details][login_base_url', $this->t('The Microsoft login base URL must use HTTPS.'));
      }

      if (empty($login_base_url_parts['host'])) {
        $form_state->setErrorByName('settings][details][login_base_url', $this->t('The Microsoft login base URL must include a host.'));
      }

      if (!empty($login_base_url_parts['query']) || !empty($login_base_url_parts['fragment'])) {
        $form_state->setErrorByName('settings][details][login_base_url', $this->t('The Microsoft login base URL must not include a query string or fragment.'));
      }
    }

    if ($login_host === '') {
      $form_state->setErrorByName('settings][details][login_host', $this->t('The Microsoft login issuer host is required.'));
    }
    elseif (preg_match('#[/:]#', $login_host) || !filter_var($login_host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
      $form_state->setErrorByName('settings][details][login_host', $this->t('The Microsoft login issuer host must be a valid host name without a scheme or path.'));
    }

    if ($login_base_url !== '' && $login_host !== '' && filter_var($login_base_url, FILTER_VALIDATE_URL)) {
      $login_base_url_host = parse_url($login_base_url, PHP_URL_HOST);

      if (is_string($login_base_url_host) && strcasecmp($login_base_url_host, $login_host) !== 0) {
        $form_state->setErrorByName('settings][details][login_host', $this->t('The Microsoft login issuer host must match the host from the Microsoft login base URL.'));
      }
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
      'client_id_env_var' => trim((string) $form_state->getValue(['settings', 'details', 'client_id_env_var'])),
      'client_secret_env_var' => trim((string) $form_state->getValue(['settings', 'details', 'client_secret_env_var'])),
      'authority_alias' => trim((string) $form_state->getValue(['settings', 'details', 'authority_alias'])),
      'login_base_url' => rtrim(trim((string) $form_state->getValue(['settings', 'details', 'login_base_url'])), '/'),
      'login_host' => strtolower(trim((string) $form_state->getValue(['settings', 'details', 'login_host']))),
      'callback_uri' => $form_state->getValue(['settings', 'details', 'callback_uri']),
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
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the token does not contain valid Microsoft consumer account
   *   claims.
   */
  private function validateConsumerAccountClaims(array $claims, SsoAppInterface $app): void {
    $issuer = $claims['iss'] ?? NULL;
    $tenant_id = $claims['tid'] ?? NULL;

    if (!is_string($tenant_id) || trim($tenant_id) === '') {
      throw new \InvalidArgumentException('Invalid Microsoft Entra Consumer tenant claim.');
    }

    if (!is_string($issuer) || !$this->isValidDynamicIssuer($issuer, $tenant_id, $app)) {
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
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return bool
   *   TRUE if the issuer is valid for the resolved Microsoft tenant.
   */
  private function isValidDynamicIssuer(string $issuer, string $tenant_id, SsoAppInterface $app): bool {
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

    if (strtolower((string) ($issuer_parts['host'] ?? '')) !== $this->getMicrosoftLoginHost($app)) {
      return FALSE;
    }

    $path = $issuer_parts['path'] ?? '';

    if (!is_string($path) || !preg_match('#^/([^/]+)/v2\.0$#', $path, $matches)) {
      return FALSE;
    }

    $issuer_tenant = rawurldecode($matches[1]);

    return $issuer_tenant === $tenant_id || $issuer_tenant === $this->getConsumerAuthorityAlias($app);
  }

  /**
   * Builds a Microsoft Entra v2 issuer URL for a tenant identifier.
   *
   * @param string $tenant_id
   *   The Microsoft tenant identifier or authority alias.
   * @param string $login_base_url
   *   The configured Microsoft login base URL.
   *
   * @return string
   *   The Microsoft Entra v2 issuer URL.
   */
  private function buildIssuer(string $tenant_id, string $login_base_url): string {
    return rtrim($login_base_url, '/') . '/' . trim($tenant_id, '/') . '/v2.0';
  }

  /**
   * Gets the configured Microsoft Entra Consumer authority alias.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return string
   *   The configured authority alias.
   */
  private function getConsumerAuthorityAlias(SsoAppInterface $app): string {
    return trim($this->getRequiredAppSetting($app, 'authority_alias', 'Microsoft Entra Consumer authority alias'), '/');
  }

  /**
   * Gets the configured Microsoft login base URL.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return string
   *   The configured Microsoft login base URL.
   */
  private function getMicrosoftLoginBaseUrl(SsoAppInterface $app): string {
    return rtrim($this->getRequiredAppSetting($app, 'login_base_url', 'Microsoft login base URL'), '/');
  }

  /**
   * Gets the configured Microsoft login issuer host.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return string
   *   The configured Microsoft login issuer host.
   */
  private function getMicrosoftLoginHost(SsoAppInterface $app): string {
    return strtolower($this->getRequiredAppSetting($app, 'login_host', 'Microsoft login issuer host'));
  }

  /**
   * Gets the configured Client Secret environment variable name.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return string
   *   The configured environment variable name.
   */
  private function getClientSecretEnvironmentVariableName(SsoAppInterface $app): string {
    return $this->getRequiredEnvironmentVariableNameSetting($app, 'client_secret_env_var', 'Client Secret environment variable name');
  }

  /**
   * Gets a required string setting from an SSO app.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   * @param string $key
   *   The setting key.
   * @param string $label
   *   The human-readable setting label used in exception messages.
   *
   * @return string
   *   The configured setting value.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the setting is missing.
   */
  private function getRequiredAppSetting(SsoAppInterface $app, string $key, string $label): string {
    $value = trim((string) $app->getSetting($key));

    if ($value === '') {
      throw new \InvalidArgumentException(sprintf('%s is required.', $label));
    }

    return $value;
  }

  /**
   * Gets and validates a required environment variable name setting.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   * @param string $key
   *   The setting key.
   * @param string $label
   *   The human-readable setting label used in exception messages.
   *
   * @return string
   *   The configured environment variable name.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the setting is missing or invalid.
   */
  private function getRequiredEnvironmentVariableNameSetting(SsoAppInterface $app, string $key, string $label): string {
    $value = $this->getRequiredAppSetting($app, $key, $label);

    if (!$this->isValidEnvironmentVariableName($value)) {
      throw new \InvalidArgumentException(sprintf('%s is invalid.', $label));
    }

    return $value;
  }

  /**
   * Validates an environment variable name form field.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The field name under settings details.
   * @param string $value
   *   The submitted environment variable name.
   * @param string $required_message
   *   Error message used when the field is empty.
   * @param string $invalid_message
   *   Error message used when the field is invalid.
   * @param string $missing_environment_message
   *   Error message used when the referenced environment variable is not set.
   */
  private function validateEnvironmentVariableNameField(
    FormStateInterface $form_state,
    string $field_name,
    string $value,
    string $required_message,
    string $invalid_message,
    string $missing_environment_message,
  ): void {
    if ($value === '') {
      $form_state->setErrorByName('settings][details][' . $field_name, $this->t($required_message));
      return;
    }
  }
}
