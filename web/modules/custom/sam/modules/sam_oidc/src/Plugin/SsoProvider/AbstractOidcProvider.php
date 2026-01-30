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
   * The stateToken service.
   *
   * @var \Drupal\sam_oidc\Service\OidcTokenService
   */
  protected OidcTokenService $tokenService;

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
    OidcTokenService $token_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->discovery = $discovery;
    $this->session = $session;
    $this->stateToken = $state_token;
    $this->tokenService = $token_service;
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
    );
  }

  /**
   * Returns the issuer URL for the provider.
   */
  abstract protected function getIssuer(): string;

  /**
   * Returns the OIDC client ID.
   */
  abstract protected function getClientId(): string;

  /**
   * Returns the OIDC client secret.
   */
  abstract protected function getClientSecret(): string;

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request): TrustedRedirectResponse {
    $issuer = $this->getIssuer();
    $discovery = $this->discovery->discover($issuer);

    $config = $this->configFactory->get('sam_google.settings');

    $clientId = $config->get('client_id');
    $redirectUri = $request->getSchemeAndHttpHost() . $this->getCallbackUri();

    // Generate security tokens.
    $state = Crypt::randomBytesBase64(32);
    $nonce = Crypt::randomBytesBase64(32);

    $this->session->start();
    $this->session->set('sam_oidc_state', $state);
    $this->session->set('sam_oidc_nonce', $nonce);

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

    $response = new TrustedRedirectResponse($authorizationUrl);
    $response->headers->addCacheControlDirective('no-store', TRUE);
    $response->headers->addCacheControlDirective('no-cache', TRUE);
    $response->headers->addCacheControlDirective('must-revalidate', TRUE);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function handleCallback(Request $request): array {
    
    $code = $request->query->get('code');
    $state = $request->query->get('state');
    $auth_email = $this->session->get('sam_login_email');

    if (!$code || !$state) {
      throw new \RuntimeException('Missing "code" or "state" in OIDC callback.');
    }

    // Step 1: Validate state
    if (!$this->stateToken->validate($state)) {
      throw new \RuntimeException('Invalid OIDC state token:' . $state);
    }

    // Step 2: Discover provider metadata
    $discovery = $this->discovery->discover($this->getIssuer());

    // Step 3: Exchange code for tokens
    $tokens = $this->tokenService->exchangeCodeForTokens(
      $discovery,
      $code,
      $this->getRedirectUri(),
      $this->getClientId(),
      $this->getClientSecret(),
    );

    $claims = $this->tokenService->decodeWithoutVerification($tokens['id_token']);
  
    $this->tokenService->validateIdTokenClaims(
      claims: $claims,
      expectedIssuer: $this->getIssuer(),
      expectedAudience: $this->getClientId(),
      expectedNonce: $this->session->get('sam_oidc_nonce'),
      expectedHostedDomain: NULL
    );

    return [
      'issuer' => $this->getIssuer(),
      'discovery' => $discovery,
      'tokens' => $tokens,
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
   * {@inheritdoc}
   */
  public function getCallbackUri(): ?string {
    return NULL;
  }

}
