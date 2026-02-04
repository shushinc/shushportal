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
    $redirectUri = $request->getSchemeAndHttpHost() . $app->getSetting('callback_uri');

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
      $request->getSchemeAndHttpHost() . $app->getSetting('callback_uri'),
      $this->getClientId($app),
      $this->getClientSecret($app),
    );

    $claims = $this->tokenService->decode($tokens['id_token']);
  
    $this->tokenService->validateIdTokenClaims(
      claims: $claims,
      expectedIssuer: $this->getIssuer($app),
      expectedAudience: $this->getClientId($app),
      expectedNonce: $this->session->get('sam_oidc_nonce'),
      expectedHostedDomain: $this->getHostedDomain($app),
      expectedEmail: $auth_email,
    );

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

}
