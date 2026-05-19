<?php

namespace Drupal\sam_oidc\Plugin\SsoProvider;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\sam\Plugin\SsoProvider\SsoProviderInterface;
use Drupal\sam\SsoAppInterface;
use Drupal\sam_oidc\Service\OidcDiscoveryService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\sam\Security\SamStateTokenService;
use Drupal\sam_oidc\Service\OidcTokenService;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Base class for OIDC-based SSO providers.
 */
abstract class AbstractOidcProvider extends PluginBase implements SsoProviderInterface, ContainerFactoryPluginInterface {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The OIDC discovery service.
   *
   * @var \Drupal\sam_oidc\Service\OidcDiscoveryService
   */
  protected OidcDiscoveryService $discovery;

  /**
   * The Session Manager service.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected SessionInterface $session;

  /**
   * The stateToken service.
   *
   * @var \Drupal\sam\Security\SamStateTokenService
   */
  protected SamStateTokenService $stateToken;

  /**
   * The OidcToken service.
   *
   * @var \Drupal\sam_oidc\Service\OidcTokenService
   */
  protected OidcTokenService $tokenService;

  /**
   * The EntityTypeManager Service
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs the OIDC provider.
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
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->discovery = $discovery;
    $this->session = $session;
    $this->stateToken = $state_token;
    $this->tokenService = $token_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
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
    );
  }

  /**
   * Returns the issuer URL for the provider.
   */
  abstract protected function getIssuer(SsoAppInterface $app): string;

  /**
   * Returns the OIDC client ID.
   */
  abstract protected function getClientId(SsoAppInterface $app): string;

  /**
   * Returns the OIDC client secret.
   */
  abstract protected function getClientSecret(SsoAppInterface $app): string;

  /**
   * {@inheritdoc}
   */
  abstract protected function getCallbackUri(SsoAppInterface $app): string;

  /**
   * {@inheritdoc}
   */
  abstract protected function getHostedDomain(SsoAppInterface $app): string|NULL;

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request, SsoAppInterface $app = NULL): TrustedRedirectResponse {
    $issuer = $this->getIssuer($app);
    $discovery = $this->discovery->discover($issuer);

    $clientId = $app->getSetting('client_id');
    $redirectUri = $this->normalizeCallbackUri($this->getCallbackUri($app), $request);

    \Drupal::logger('SSO Authentication Manager')->info('Client ID: @nid', [
      '@nid' => $clientId,
    ]);
    \Drupal::logger('SSO Authentication Manager')->info('Redirect URI: @redirectUri', [
      '@redirectUri' => $redirectUri,
    ]);

    if (empty($clientId) || empty($redirectUri)) {
      throw new \RuntimeException('SSO app is missing required OIDC configuration.');
    }

    // Generate security tokens.
    $state = Crypt::randomBytesBase64(32);
    $nonce = Crypt::randomBytesBase64(32);

    $this->session->start();
    $this->session->set('sam_oidc_state', $state);
    $this->session->set('sam_oidc_nonce', $nonce);
    $this->session->set('sam_sso_app_id', $app->id());

    $query = [
      'client_id' => $clientId,
      'response_type' => 'code',
      'scope' => 'openid email profile',
      'redirect_uri' => $redirectUri,
      'state' => $state,
      'nonce' => $nonce,
      'prompt' => 'select_account',
    ];

    $authorizationUrl =
      $discovery['authorization_endpoint']
      . '?' . http_build_query($query);

    \Drupal::logger('SSO Authentication Manager')->info('Authorization URL: @authorizationUrl', [
      '@authorizationUrl' => $authorizationUrl,
    ]);

    $response = new TrustedRedirectResponse($authorizationUrl);
    $response->headers->addCacheControlDirective('no-store', TRUE);
    $response->headers->addCacheControlDirective('no-cache', TRUE);
    $response->headers->addCacheControlDirective('must-revalidate', TRUE);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function handleCallback(Request $request, SsoAppInterface $app): array {
    
    $code = $request->query->get('code');
    $state = $request->query->get('state');
    $auth_email = $this->session->get('sam_login_email');

    if (!$code || !$state) {
      throw new \RuntimeException('Missing "code" or "state" in OIDC callback.');
    }

    $app_id = $this->session->get('sam_sso_app_id');

    if (!$app_id) {
      throw new \RuntimeException('Missing SSO application context in session.');
    }

    /** @var \Drupal\sam\SsoAppInterface|null $app */
    $app = $this->entityTypeManager
      ->getStorage('sam_sso_app')
      ->load($app_id);

    if (!$app || !$app->isEnabled()) {
      throw new \RuntimeException('SSO application is not available.');
    }

    if (!$this->stateToken->validate($state)) {
      throw new \RuntimeException('Invalid OIDC state token:' . $state);
    }

    $discovery = $this->discovery->discover($this->getIssuer($app));

    // Step 3: Exchange code for tokens
    $tokens = $this->tokenService->exchangeCodeForTokens(
      $discovery,
      $code,
      $this->normalizeCallbackUri($this->getCallbackUri($app), $request),
      $this->getClientId($app),
      $this->getClientSecret($app),
    );

    if (empty($tokens['id_token']) || !is_string($tokens['id_token'])) {
      throw new \RuntimeException('OIDC token response does not contain an ID token.');
    }

    $this->tokenService->validateIdTokenSignature($tokens['id_token'], $discovery);

    $claims = $this->tokenService->decode($tokens['id_token']);

    $expectedIssuer = $this->shouldValidateIssuerWithTokenService($app)
      ? $this->getIssuer($app)
      : (string) ($claims['iss'] ?? '');
  
    $this->tokenService->validateIdTokenClaims(
      claims: $claims,
      expectedIssuer: $expectedIssuer,
      expectedAudience: $this->getClientId($app),
      expectedNonce: $this->session->get('sam_oidc_nonce'),
      expectedEmail: $auth_email,
      expectedHostedDomain: $this->getHostedDomain($app),
    );

    $this->validateProviderSpecificClaims($claims, $app);

    return [
      'issuer' => $this->getIssuer($app),
      'discovery' => $discovery,
      'tokens' => $tokens,
      'claims'=> $claims,
      'sso_app' => $app,
      'auth_email' => $auth_email,
    ];
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
  public function getConfigurationForm(array $form, FormStateInterface $form_state, SsoAppInterface $soApp = NULL): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  abstract public function submitConfigurationForm(array &$form, FormStateInterface $form_state, SsoAppInterface $soApp = NULL): array;

  /**
   * Returns the redirect URI registered in the IdP.
   */
  protected function getRedirectUri(): string {
    return Url::fromRoute(
      'sam.callback',
      ['provider' => $this->getPluginId()],
      ['absolute' => TRUE]
    )->toString();
  }

  /**
   * Normalizes a configured callback URI to an absolute URI.
   *
   * Microsoft Entra requires redirect_uri to be an absolute URI. Existing
   * provider configuration may store only the route path, such as
   * /sso/callback/entra_consumer, so this method resolves relative callback
   * paths against the current request scheme and host.
   *
   * @param string $callback_uri
   *   The configured callback URI.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return string
   *   The absolute callback URI.
   */
  protected function normalizeCallbackUri(string $callback_uri, Request $request): string {
    $callback_uri = trim($callback_uri);

    if ($callback_uri === '') {
      return '';
    }

    if (parse_url($callback_uri, PHP_URL_SCHEME) !== NULL) {
      return $callback_uri;
    }

    if (str_starts_with($callback_uri, '//')) {
      return $request->getScheme() . ':' . $callback_uri;
    }

    if ($callback_uri[0] !== '/') {
      $callback_uri = '/' . $callback_uri;
    }

    return $request->getSchemeAndHttpHost() . $callback_uri;
  }

  /**
   * Determines whether the shared token service should validate issuer strictly.
   *
   * Providers that use non-tenant-specific discovery but receive tenant-specific
   * issuer claims can return FALSE and perform provider-specific issuer
   * validation in validateProviderSpecificClaims().
   *
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   *
   * @return bool
   *   TRUE when the shared token service should compare the issuer claim against
   *   getIssuer().
   */
  protected function shouldValidateIssuerWithTokenService(SsoAppInterface $app): bool {
    return TRUE;
  }

  /**
   * Performs provider-specific claim validation after shared OIDC validation.
   *
   * @param array $claims
   *   The decoded ID token claims.
   * @param \Drupal\sam\SsoAppInterface $app
   *   The SSO app.
   */
  protected function validateProviderSpecificClaims(array $claims, SsoAppInterface $app): void {}

}
