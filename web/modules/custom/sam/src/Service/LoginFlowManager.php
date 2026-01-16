<?php

namespace Drupal\sam\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
   * SAM module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

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
   * Constructs the LoginFlowManager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    RequestStack $request_stack
  ) {
    $this->config = $config_factory->get('sam.settings');
    $this->logger = $logger_factory->get('sam');
    $this->requestStack = $request_stack;
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
    if (!$this->isSsoActive()) {
      return;
    }

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

    $this->logger->debug('User login form altered for SSO authentication.');
  }

  /**
   * Handles submission of the email-only login form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function handleLoginFormSubmit(FormStateInterface $form_state): void {
    $email = trim((string) $form_state->getValue('name'));

    if ($email === '') {
      $form_state->setErrorByName('name', t('Email is required.'));
      return;
    }

    // Resolve active provider.
    $provider = $this->getActiveProvider();

    if ($provider === NULL) {
      $this->logger->error('SSO login attempted but no active provider is configured.');
      $form_state->setErrorByName('name', t('SSO authentication is not properly configured.'));
      return;
    }

    // Redirect to SSO authentication entry point.
    $url = Url::fromRoute('sam.authenticate', [
      'provider' => $provider,
    ]);

    $this->logger->info('Redirecting login request to SSO provider "{provider}".', [
      'provider' => $provider,
    ]);
    

    $form_state->setResponse(
      new RedirectResponse($url->toString())
    );

    $form_state->setRebuild(FALSE);
    $form_state->disableRedirect();
  }

  /**
   * Determines whether SSO is currently active.
   *
   * @return bool
   *   TRUE if SSO authentication is enabled.
   */
  protected function isSsoActive(): bool {
    return (bool) $this->config->get('sso_active');
  }

  /**
   * Returns the active SSO provider ID.
   *
   * @return string|null
   *   Provider ID or NULL if not configured.
   */
  protected function getActiveProvider(): ?string {
    $provider = $this->config->get('active_provider');
    return is_string($provider) && $provider !== '' ? $provider : NULL;
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