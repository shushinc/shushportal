<?php

namespace Drupal\sam\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\sam\Service\IdentityManager;
use Drupal\sam\Service\SsoAppResolver;
use Drupal\sam\SsoProviderManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClientPasswordForm extends FormBase {

  protected AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The SSO app resolver.
   *
   * @var \Drupal\sam\Service\SsoAppResolver
   */
  protected SsoAppResolver $ssoAppResolver;

  /**
   * The SSO provider manager.
   *
   * @var \Drupal\sam\SsoProviderManager
   */
  protected SsoProviderManager $providerManager;

  /**
   * The identity manager.
   *
   * @var \Drupal\sam\Service\IdentityManager
   */
  protected IdentityManager $identityManager;

  public function __construct(
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager,
    SsoAppResolver $sso_app_resolver,
    SsoProviderManager $provider_manager,
    IdentityManager $identity_manager
  ) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->ssoAppResolver = $sso_app_resolver;
    $this->providerManager = $provider_manager;
    $this->identityManager = $identity_manager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('sam.sso_app_resolver'),
      $container->get('sam.provider_manager'),
      $container->get('sam.identity_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sam_client_password_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $session = $this->getRequest()->getSession();
    $email = $session->get('sam_login_email');

    if (!$email) {
      $this->messenger()->addError($this->t('Your login session expired.'));
      return $this->redirect('user.login')->send();
    }

    $form['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#default_value' => $email,
      '#disabled' => TRUE,
    ];

    $form['pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sign in'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $session = $this->getRequest()->getSession();
    $email = $session->get('sam_login_email');
    $password = $form_state->getValue('pass');

    if (!$email || !$password) {
      $this->messenger()->addError($this->t('Invalid login attempt.'));
      return;
    }

    // Resolve SSO app from session.
    $app_id = $session->get('sam_sso_app_id');
    $app = NULL;

    if ($app_id) {
      $app = $this->entityTypeManager
        ->getStorage('sam_sso_app')
        ->load($app_id);
    }

    // Determine authentication strategy.
    if ($app && $app->isEnabled()) {
      $provider = $this->providerManager->getProvider($app->getProvider());

      if ($provider && $provider->supportsCredentialAuthentication()) {
        // LDAP authentication.
        try {
          $identity_data = $provider->authenticateCredentials($email, $password, $app);

          // Resolve or create Drupal user.
          $account = $this->identityManager->resolveUser($identity_data);

          if (!$account) {
            $this->messenger()->addError($this->t('Unable to create or find user account.'));
            return;
          }

          // Login user.
          user_login_finalize($account);

          // Cleanup.
          $session->remove('sam_login_email');
          $session->remove('sam_sso_app_id');

          $form_state->setRedirect('user.page');
          return;
        }
        catch (\Exception $e) {
          $this->getLogger('sam')->error('LDAP authentication failed for @email: @message', [
            '@email' => $email,
            '@message' => $e->getMessage(),
          ]);

          $this->messenger()->addError($this->t('Invalid credentials.'));
          return;
        }
      }
    }

    // Fallback to Drupal password authentication.
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);

    /** @var \Drupal\user\Entity\User|null $account */
    $account = reset($users);

    if (!$account || !$account->isActive()) {
      $this->messenger()->addError($this->t('Invalid credentials.'));
      return;
    }

    // Validate password.
    $password_hasher = \Drupal::service('password');
    if (!$password_hasher->check($password, $account->getPassword())) {
      $this->messenger()->addError($this->t('Invalid credentials.'));
      return;
    }

    // Login user.
    user_login_finalize($account);

    // Cleanup.
    $session->remove('sam_login_email');
    $session->remove('sam_sso_app_id');

    $form_state->setRedirect('user.page');
  }

}
