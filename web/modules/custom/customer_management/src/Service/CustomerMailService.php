<?php

namespace Drupal\customer_management\Service;

use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountProxyInterface;
use Twig\Environment;

/**
 * Sends customer-related mail.
 */
class CustomerMailService {

  /**
   * Constructs the service.
   */
  public function __construct(
    protected MailManagerInterface $mailManager,
    protected AccountProxyInterface $currentUser,
    protected ExtensionPathResolver $extensionPathResolver,
    protected Environment $twig
  ) {}

  /**
   * Sends initial credentials email.
   */
  public function sendCreatedEmail(array $params, string $to): void {
    $this->sendCredentialsEmail('customer_created', $params, $to, 'created');
  }

  /**
   * Resends the current credentials email.
   */
  public function resendCredentialsEmail(array $params, string $to): void {
    $this->sendCredentialsEmail('credentials_resent', $params, $to, 'resent');
  }

  /**
   * Sends hashed key reset email.
   */
  public function sendResetEmail(array $params, string $to): void {
    $this->sendCredentialsEmail('hashed_key_reset', $params, $to, 'reset');
  }

  /**
   * Renders and sends a credentials email using the module Twig template.
   */
  protected function sendCredentialsEmail(string $key, array $params, string $to, string $action): void {
    $module_path = $this->extensionPathResolver->getPath('module', 'customer_management');
    $path = $module_path . '/templates/customer_credentials_mail.html.twig';

    $rendered = $this->twig->load($path)->render([
      'contact_name' => $params['contact_name'] ?? '',
      'customer_name' => $params['customer_name'] ?? '',
      'client_id' => $params['client_id'] ?? '',
      'hashed_key' => $params['hashed_key'] ?? '',
      'action' => $action,
      'site_name' => \Drupal::config('system.site')->get('name'),
    ]);

    $mail_params = [
      'message' => Markup::create(nl2br($rendered)),
    ];

    $this->mailManager->mail(
      'customer_management',
      $key,
      $to,
      $this->currentUser->getPreferredLangcode(),
      $mail_params,
      NULL,
      TRUE
    );
  }

}
