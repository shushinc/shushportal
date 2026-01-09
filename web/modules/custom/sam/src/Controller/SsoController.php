<?php

namespace Drupal\sam\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\sam\SsoProviderManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\sam\Service\IdentityManager;

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

  /**
   * Constructs a new SsoController object.
   *
   * @param \Drupal\sam\SsoProviderManager $provider_manager
   *   The SSO provider manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(SsoProviderManager $provider_manager, MessengerInterface $messenger, IdentityManager $identity_manager) {
    $this->providerManager = $provider_manager;
    $this->messenger = $messenger;
    $this->identityManager = $identity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sam.provider_manager'),
      $container->get('messenger'),
      $container->get('sam.identity_manager')
    );
  }

  /**
   * Initiates authentication with an SSO provider.
   *
   * @param string $provider
   *   The provider ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The authentication response.
   */
  public function authenticate($provider, Request $request) {
    $provider_instance = $this->providerManager->getProvider($provider);

    if (!$provider_instance) {
      throw new NotFoundHttpException('SSO provider not found.');
    }

    if (!$provider_instance->isConfigured()) {
      $this->messenger->addError($this->t('SSO provider @provider is not properly configured.', ['@provider' => $provider]));
      return $this->redirect('user.login');
    }

    try {
      return $provider_instance->authenticate($request);
    }
    catch (\Exception $e) {
      $this->getLogger('sam')->error('Authentication error with provider @provider: @message', [
        '@provider' => $provider,
        '@message' => $e->getMessage(),
      ]);

      $this->messenger->addError($this->t('Authentication failed. Please try again.'));
      return $this->redirect('user.login');
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
    $provider_instance = $this->providerManager->getProvider($provider);

    if (!$provider_instance) {
      throw new NotFoundHttpException('SSO provider not found.');
    }

    try {
      $user_data = $provider_instance->handleCallback($request);

      // Find or create user.
      $account = $this->identityManager->resolveUser($userData);

      if ($account) {
        // Log in the user.
        user_login_finalize($account);

        // Redirect to configured path or user profile.
        $config = $this->config('sam.settings');
        $redirect_path = $config->get('default_redirect') ?: '/user';

        return new RedirectResponse(Url::fromUserInput($redirect_path)->toString());
      }
      else {
        $this->messenger->addError($this->t('Unable to create or find user account.'));
        return $this->redirect('user.login');
      }
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
}