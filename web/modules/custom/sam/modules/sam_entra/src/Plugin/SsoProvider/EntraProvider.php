<?php

namespace Drupal\sam_entra\Plugin\SsoProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sam\SsoAppInterface;
use Drupal\sam_oidc\Plugin\SsoProvider\AbstractOidcProvider;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\sam\Security\SamStateTokenService;
use Drupal\sam_oidc\Service\OidcDiscoveryService;
use Drupal\sam_oidc\Service\OidcTokenService;

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
final class EntraProvider extends AbstractOidcProvider implements ContainerFactoryPluginInterface {

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
        '#title' => $this->t('Drupal Key ID'),
        '#description' => $this->t('The Drupal Key entity ID that stores the Tenant Entra ID.'),
        '#default_value' => $settings['tenant_id_env_var'] ?? '',
        '#required' => TRUE,
      ],
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
    );

    $this->validateEnvironmentVariableNameField(
      $form_state,
      'client_id_env_var',
      $client_id_env_var,
      'The Client ID environment variable name is required.',
    );

    $this->validateEnvironmentVariableNameField(
      $form_state,
      'client_secret_env_var',
      $client_secret_env_var,
      'The Client Secret environment variable name is required.',
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
  protected function getTenantId(SsoAppInterface $app): string {

    $key_name = trim((string) $app->getSetting('tenant_id_env_var'));

    if ($key_name === '') {
      throw new \RuntimeException(
        'Tenant ID Key name is not configured.'
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
  ): void {
    if ($value === '') {
      $form_state->setErrorByName('settings][details][' . $field_name, $this->t($required_message));
      return;
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


}
