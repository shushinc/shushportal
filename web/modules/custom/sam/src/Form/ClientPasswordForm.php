<?php

namespace Drupal\sam\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClientPasswordForm extends FormBase {

  protected AccountProxyInterface $currentUser;

  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user')
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

    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);

    /** @var \Drupal\user\Entity\User|null $account */
    $account = reset($users);

    if (!$account || !$account->isActive()) {
      $this->messenger()->addError($this->t('Invalid credentials.'));
      return;
    }

    // Validate password.
    if (!\Drupal::service('password')->check($password, $account->getPassword())) {
      $this->messenger()->addError($this->t('Invalid credentials.'));
      return;
    }

    // Login user.
    user_login_finalize($account);

    // Cleanup.
    $session->remove('sam_login_email');

    $form_state->setRedirect('user.page');
  }

}
