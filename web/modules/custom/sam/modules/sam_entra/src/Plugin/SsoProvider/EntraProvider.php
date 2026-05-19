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
    return $this->buildIssuer($this->getTenantId($app), $this->getMicrosoftLoginBaseUrl($app));
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
    return $this->getMicrosoftLoginBaseUrl($app) . '/' . $this->getTenantId($app) . '/v2.0/.well-known/openid-configuration';
  }

  /**
   * {@inheritdoc}
   */
  protected function getClientId(SsoAppInterface $app): string {
    $environment_variable_name = $this->getClientIdEnvironmentVariableName($app);

    return $this->getRequiredEnvironmentVariable($environment_variable_name, 'Microsoft Entra ID Client ID');
  }

  /**
   * {@inheritdoc}
   */
  protected function getClientSecret(SsoAppInterface $app): string {
    $environment_variable_name = $this->getClientSecretEnvironmentVariableName($app);

    return $this->getRequiredEnvironmentVariable($environment_variable_name, 'Microsoft Entra ID Client Secret');
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
      'tenant_id_env_var' => [
        '#type' => 'textfield',
        '#title' => $this->t('Tenant ID environment variable name'),
        '#description' => $this->t('The name of the environment variable that contains the Microsoft Entra ID tenant ID, such as a tenant UUID or verified tenant domain. The tenant ID value itself is not stored in Drupal configuration.'),
        '#default_value' => $settings['tenant_id_env_var'] ?? '',
        '#required' => TRUE,
      ],
      'client_id_env_var' => [
        '#type' => 'textfield',
        '#title' => $this->t('Client ID environment variable name'),
        '#description' => $this->t('The name of the environment variable that contains the Microsoft Entra ID Client ID. The credential value itself is not stored in Drupal configuration.'),
        '#default_value' => $settings['client_id_env_var'] ?? '',
        '#required' => TRUE,
      ],
      'client_secret_env_var' => [
        '#type' => 'textfield',
        '#title' => $this->t('Client Secret environment variable name'),
        '#description' => $this->t('The name of the environment variable that contains the Microsoft Entra ID Client Secret. The credential value itself is not stored in Drupal configuration.'),
        '#default_value' => $settings['client_secret_env_var'] ?? '',
        '#required' => TRUE,
      ],
      'login_base_url' => [
        '#type' => 'textfield',
        '#title' => $this->t('Microsoft login base URL'),
        '#description' => $this->t('The base URL used to build the Microsoft Entra ID issuer and discovery URLs. For example: https://login.microsoftonline.com'),
        '#default_value' => $settings['login_base_url'] ?? '',
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
    $tenant_id_env_var = trim((string) $form_state->getValue(['settings', 'details', 'tenant_id_env_var']));
    $client_id_env_var = trim((string) $form_state->getValue(['settings', 'details', 'client_id_env_var']));
    $client_secret_env_var = trim((string) $form_state->getValue(['settings', 'details', 'client_secret_env_var']));
    $login_base_url = trim((string) $form_state->getValue(['settings', 'details', 'login_base_url']));
    $callback_uri = $form_state->getValue(['settings', 'details', 'callback_uri']);

    $this->validateEnvironmentVariableNameField(
      $form_state,
      'tenant_id_env_var',
      $tenant_id_env_var,
      'The Tenant ID environment variable name is required.',
      'The Tenant ID environment variable name is invalid.',
      'The Tenant ID environment variable @env_var must be set.'
    );

    if (
      $tenant_id_env_var !== ''
      && $this->isValidEnvironmentVariableName($tenant_id_env_var)
      && $this->hasEnvironmentVariable($tenant_id_env_var)
    ) {
      $tenant_id = trim((string) $this->getEnvironmentVariable($tenant_id_env_var), '/');

      if (!$this->isValidTenantId($tenant_id)) {
        $form_state->setErrorByName('settings][details][tenant_id_env_var', $this->t('The Tenant ID environment variable @env_var must contain a valid Microsoft Entra ID tenant ID.', [
          '@env_var' => $tenant_id_env_var,
        ]));
      }
    }

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

    if (empty($callback_uri)) {
      $form_state->setErrorByName('settings][details][callback_uri', $this->t('The callback URI is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, SsoAppInterface $ssoApp = NULL): array {
    return [
      'tenant_id_env_var' => trim((string) $form_state->getValue(['settings', 'details', 'tenant_id_env_var'])),
      'client_id_env_var' => trim((string) $form_state->getValue(['settings', 'details', 'client_id_env_var'])),
      'client_secret_env_var' => trim((string) $form_state->getValue(['settings', 'details', 'client_secret_env_var'])),
      'login_base_url' => rtrim(trim((string) $form_state->getValue(['settings', 'details', 'login_base_url'])), '/'),
      'callback_uri' => $form_state->getValue(['settings', 'details', 'callback_uri']),
    ];
  }

  /**
   * Builds a Microsoft Entra v2 issuer URL for a tenant identifier.
   *
   * @param string $tenant_id
   *   The Microsoft tenant identifier.
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
   * Gets the configured Microsoft Entra ID tenant ID from an environment variable.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return string
   *   The normalized tenant ID.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the tenant ID environment variable is missing or invalid.
   */
  private function getTenantId(SsoAppInterface $app): string {
    $environment_variable_name = $this->getTenantIdEnvironmentVariableName($app);
    $tenant_id = trim($this->getRequiredEnvironmentVariable($environment_variable_name, 'Microsoft Entra ID Tenant ID'), '/');

    if (!$this->isValidTenantId($tenant_id)) {
      throw new \InvalidArgumentException(sprintf('Microsoft Entra ID Tenant ID environment variable %s contains an invalid tenant ID.', $environment_variable_name));
    }

    return $tenant_id;
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
   * Gets the configured Tenant ID environment variable name.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return string
   *   The configured environment variable name.
   */
  private function getTenantIdEnvironmentVariableName(SsoAppInterface $app): string {
    return $this->getRequiredEnvironmentVariableNameSetting($app, 'tenant_id_env_var', 'Tenant ID environment variable name');
  }

  /**
   * Gets the configured Client ID environment variable name.
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return string
   *   The configured environment variable name.
   */
  private function getClientIdEnvironmentVariableName(SsoAppInterface $app): string {
    return $this->getRequiredEnvironmentVariableNameSetting($app, 'client_id_env_var', 'Client ID environment variable name');
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

    if (!$this->isValidEnvironmentVariableName($value)) {
      $form_state->setErrorByName('settings][details][' . $field_name, $this->t($invalid_message));
      return;
    }

    if (!$this->hasEnvironmentVariable($value)) {
      $form_state->setErrorByName('settings][details][' . $field_name, $this->t($missing_environment_message, [
        '@env_var' => $value,
      ]));
    }
  }

  /**
   * Checks whether an environment variable name is valid.
   *
   * @param string $name
   *   The environment variable name.
   *
   * @return bool
   *   TRUE when the name is valid.
   */
  private function isValidEnvironmentVariableName(string $name): bool {
    return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name);
  }

  /**
   * Checks whether a Microsoft Entra ID tenant ID is valid.
   *
   * @param string $tenant_id
   *   The Microsoft tenant identifier.
   *
   * @return bool
   *   TRUE when the tenant ID is valid.
   */
  private function isValidTenantId(string $tenant_id): bool {
    return $tenant_id !== '' && (bool) preg_match('/^[A-Za-z0-9._-]+$/', $tenant_id);
  }

  /**
   * Checks whether an environment variable is available and non-empty.
   *
   * @param string $name
   *   The environment variable name.
   *
   * @return bool
   *   TRUE when the environment variable exists and is non-empty.
   */
  private function hasEnvironmentVariable(string $name): bool {
    return $this->getEnvironmentVariable($name) !== NULL;
  }

  /**
   * Gets a required environment variable.
   *
   * @param string $name
   *   The environment variable name.
   * @param string $label
   *   The human-readable label used in exception messages.
   *
   * @return string
   *   The environment variable value.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the environment variable is missing or empty.
   */
  private function getRequiredEnvironmentVariable(string $name, string $label): string {
    $value = $this->getEnvironmentVariable($name);

    if ($value === NULL) {
      throw new \InvalidArgumentException(sprintf('%s environment variable %s is required.', $label, $name));
    }

    return $value;
  }

  /**
   * Gets an environment variable from the current PHP runtime.
   *
   * @param string $name
   *   The environment variable name.
   *
   * @return string|null
   *   The trimmed environment variable value, or NULL when missing or empty.
   */
  private function getEnvironmentVariable(string $name): ?string {
    $value = getenv($name);

    if ($value === FALSE && isset($_ENV[$name])) {
      $value = $_ENV[$name];
    }

    if ($value === FALSE && isset($_SERVER[$name])) {
      $value = $_SERVER[$name];
    }

    if (!is_string($value)) {
      return NULL;
    }

    $value = trim($value);

    return $value !== '' ? $value : NULL;
  }

}
