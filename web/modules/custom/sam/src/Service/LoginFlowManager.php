<?php

namespace Drupal\sam\Service;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\sam\Service\IdentityManager;
use Drupal\sam\Service\SsoAppResolver;
use Drupal\sam\SsoProviderManager;

/**
 * Manages the passwordless SSO login flow.
 *
 * This service is responsible for:
 * - Altering the core user login form when SSO is active
 * - Removing password-based validation and submit handlers
 * - Validating the email-only login input
 * - Redirecting the user to the configured SSO provider
 *
 * This class intentionally contains all SSO login flow logic,
 * keeping .module files thin and declarative.
 */
final class LoginFlowManager {

  /**
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The Symfony session service.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  private SessionInterface $session;

  /**
   * Summary of identityManager
   * @var \Drupal\sam\Service\IdentityManager IdentityManager
   */
  protected IdentityManager $identityManager;

  /**
   * Summary of ssoAppResolver
   * @var \Drupal\sam\Service\SsoAppResolver SsoAppResolver
   */
  protected SsoAppResolver $ssoAppResolver;

  /**
   * The SSO provider manager.
   *
   * @var \Drupal\sam\SsoProviderManager
   */
  protected $providerManager;

  /**
   * Constructs the LoginFlowManager.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack service.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session service.
   * @param \Drupal\sam\Service\IdentityManager $identity_manager
   *   The identityManager service.
   * @param \Drupal\sam\Service\SsoAppResolver $sso_app_resolver
   *   The ssoAppResolver service.
   * @param \Drupal\sam\SsoProviderManager $provider_manager
   *   The providerManger service.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    RequestStack $request_stack,
    SessionInterface $session,
    IdentityManager $identity_manager,
    SsoAppResolver $sso_app_resolver,
    SsoProviderManager $provider_manager,
  ) {
    $this->logger = $logger_factory->get('sam');
    $this->requestStack = $request_stack;
    $this->session = $session;
    $this->identityManager = $identity_manager;
    $this->ssoAppResolver = $sso_app_resolver;
    $this->providerManager = $provider_manager;
  }

  /**
   * Alters the core user login form to enable passwordless SSO.
   *
   * @param array $form
   *   The login form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function alterLoginForm(array &$form, FormStateInterface $form_state): void {

    // Remove password field (passwordless flow).
    unset($form['pass']);

    $form['#submit'] = [];

    if (!isset($form['actions']['submit']['#submit'])) {
      $form['actions']['submit']['#submit'] = [];
    }

    $form['actions']['submit']['#submit'] = [
      '_sam_user_login_sso_submit',
    ];
    
    // Remove core password-based validators and submit handlers.
    $this->removePasswordValidators($form);

  }

  /**
   * Handles submission of the email-only login form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function handleLoginFormSubmit(FormStateInterface $form_state): void {
    $email = trim((string) $form_state->getCompleteForm()['name']['#value']);

    if ($email === '') {
      $form_state->setErrorByName('name', t('Email is required.'));
      return;
    }

    /**
     * @var \Drupal\user\UserInterface
     */
    $user = user_load_by_mail($email);
    $app = $this->ssoAppResolver->resolveByEmail($email);

    if ($app === NULL || $user->hasRole('administrator')) {
      // No SSO app configured for this domain OR
      // User has the administrator role:
      // fallback to normal login.
      $this->session->set('sam_login_email', $email);

      $url = Url::fromRoute('sam.client_auth_screen');
      $form_state->setResponse(new RedirectResponse($url->toString()));
      $form_state->setRebuild(FALSE);
      $form_state->disableRedirect();
    }

    else {
      $this->session->set('sam_login_email', $email);
      $this->session->set('sam_sso_app_id', $app->id());

      $provider_id = $app->getProvider();
      $provider = $this->providerManager->getProvider($provider_id);

      if ($provider !== NULL) {
        // Redirect to SSO authentication entry point.
        $url = Url::fromRoute('sam.authenticate');

        $form_state->setResponse(
          new RedirectResponse($url->toString())
        );

        $form_state->setRebuild(FALSE);
        $form_state->disableRedirect();
      }
    }
  }

  /**
   * Removes password-based validators from the login form.
   *
   * This prevents:
   * - Core authentication attempts
   * - Deprecated trim(null) warnings
   *
   * @param array $form
   *   The login form render array.
   */
  protected function removePasswordValidators(array &$form): void {
    if (empty($form['#validate']) || !is_array($form['#validate'])) {
      return;
    }

    $form['#validate'] = array_values(array_filter(
      $form['#validate'],
      static function ($callback) {
        return !(
          is_string($callback)
          || str_ends_with($callback, '::validateAuthentication')
          || str_ends_with($callback, '::validateFinal')
          || str_ends_with($callback, '::validateForm')
        );
      }
    ));
  }

}