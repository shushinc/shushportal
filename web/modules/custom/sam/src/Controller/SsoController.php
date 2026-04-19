<?php

namespace Drupal\sam\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\sam\SsoProviderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\sam\Service\IdentityManager;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sam\Service\SsoAppResolver;

/**
 * Controller for SSO authentication.
 */
class SsoController extends ControllerBase {

  /**
   * The SSO provider manager.
   *
   * @var \Drupal\sam\SsoProviderManager
   */
  protected $providerManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The IdentityManager service.
   *
   * @var \Drupal\sam\Service\IdentityManager
   */
  protected $identityManager;

  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Summary of ssoAppResolver
   * @var \Drupal\sam\Service\SsoAppResolver SsoAppResolver
   */
  protected SsoAppResolver $ssoAppResolver;

  /**
   * Constructs a new SsoController object.
   *
   * @param \Drupal\sam\SsoProviderManager $provider_manager
   *   The SSO provider manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\sam\Service\IdentityManager $identity_manager
   *   The Identity Manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity Type Manager service.
   * @param \Drupal\sam\Service\SsoAppResolver $sso_app_resolver
   *   The SSO provider manager.
   */
  public function __construct(
    SsoProviderManager $provider_manager, 
    MessengerInterface $messenger,
    IdentityManager $identity_manager,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    SsoAppResolver $sso_app_resolver
    )
    {
      $this->providerManager = $provider_manager;
      $this->messenger = $messenger;
      $this->identityManager = $identity_manager;
      $this->configFactory = $config_factory;
      $this->entityTypeManager = $entity_type_manager;
      $this->ssoAppResolver = $sso_app_resolver;
    }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sam.provider_manager'),
      $container->get('messenger'),
      $container->get('sam.identity_manager'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('sam.sso_app_resolver'),
    );
  }

  /**
   * Initiates authentication with an SSO provider.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The authentication response.
   */
  public function authenticate(Request $request) {
    $session = $request->getSession();
    $app_id = $session->get('sam_sso_app_id');

    if (!$app_id) {
      throw new AccessDeniedHttpException('No SSO application found in session.');
    }

    /** @var \Drupal\sam\SsoAppInterface|null $app */
    $app = $this->entityTypeManager
      ->getStorage('sam_sso_app')
      ->load($app_id);

    if (!$app || !$app->isEnabled()) {
      throw new AccessDeniedHttpException('SSO application is not available.');
    }

    $provider_id = $app->getProvider();

    $provider = $this->providerManager->getProvider($provider_id);

    if (!$provider) {
      throw new NotFoundHttpException('SSO provider not found.');
    }

    try {
      return $provider->authenticate($request, $app);
    }
    catch (\Exception $e) {
      $this->getLogger('sam')->error(
        'Authentication error with provider "@provider" for SSO app "@app": @message',
        [
          '@provider' => $provider_id,
          '@app' => $app->id(),
          '@message' => $e->getMessage(),
        ]
      );

      $this->messenger->addError(
        $this->t('Authentication failed. Please try again.')
      );

      return new RedirectResponse('/user/login');
    }
  }

  /**
   * Handles the callback from an SSO provider.
   *
   * @param string $provider
   *   The provider ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The callback request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The callback response.
   */
  public function callback($provider, Request $request) {
    
    try {
      $session = $request->getSession();
      $app_id = $session->get('sam_sso_app_id');
      
      /** @var \Drupal\sam\SsoAppInterface|null $app */
      $app = $this->entityTypeManager
        ->getStorage('sam_sso_app')
        ->load($app_id);
      
      $provider_id = $app->getProvider();
      $provider = $this->providerManager->getProvider($provider_id);

      $auth_data = $provider->handleCallback($request, $app);

      if (empty($auth_data['tokens']['id_token'])) {
        $this->messenger->addError(
          $this->t('Login failed. Invalid authentication token.')
        );
        return $this->redirect('user.login');
      }

      $email = $auth_data['auth_email'];

      if (empty($email)) {
        throw new \RuntimeException('Authenticated email not found in ID token.');
      }

      // Find or create local user.
      $account = $this->identityManager->resolveUser([
        'email' => $email,
      ]);

      if (!$account) {
        $this->messenger->addError(
          $this->t('Unable to create or find user account.')
        );
        return $this->redirect('user.login');
      }

      user_login_finalize($account);
      $redirect_path = '/user';

      return new RedirectResponse(Url::fromUserInput($redirect_path)->toString());

    }
    catch (\Exception $e) {
      $this->getLogger('sam')->error('Callback error with provider @provider: @message', [
        '@provider' => $provider,
        '@message' => $e->getMessage(),
      ]);

      $this->messenger->addError($this->t('Login failed. Please try again.'));
      return $this->redirect('user.login');
    }
  }

  /**
   * Handles SSO login via invitation token.
   */
  public function verifyInvitation(string $token, Request $request): RedirectResponse {
    /** @var \Drupal\user\Entity\User $user */
    $user = $this->identityManager->getUserFromToken($token);
    $email = $this->identityManager->getEmailFromToken($token);
    $ssoApp = $this->ssoAppResolver->resolveByEmail($email);

    if (!$user) {
      throw new AccessDeniedHttpException('Invalid or expired invitation token.');
    }

    $user->set('status', '1');
    $user->save();
    $session = \Drupal::request()->getSession();
    $session->set('sam_sso_user', $user->id());
    $session->set('sam_login_email', $this->identityManager->getEmailFromToken($token));
    $provider = $this->providerManager->getProvider($ssoApp->getProvider());
    return $provider->authenticate($request, $ssoApp);
  }
}