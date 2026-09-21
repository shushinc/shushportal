<?php

namespace Drupal\customer_management\Service;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Sends customer-related mail.
 */
class CustomerMailService {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs the service.
   */
  public function __construct(
    MailManagerInterface $mail_manager,
    AccountProxyInterface $current_user
  ) {
    $this->mailManager = $mail_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Sends initial credentials email.
   */
  public function sendCreatedEmail(array $params, string $to): void {
    $this->mailManager->mail(
      'customer_management',
      'customer_created',
      $to,
      $this->currentUser->getPreferredLangcode(),
      $params
    );
  }

  /**
   * Sends hashed key reset email.
   */
  public function sendResetEmail(array $params, string $to): void {
    $this->mailManager->mail(
      'customer_management',
      'hashed_key_reset',
      $to,
      $this->currentUser->getPreferredLangcode(),
      $params
    );
  }

}
