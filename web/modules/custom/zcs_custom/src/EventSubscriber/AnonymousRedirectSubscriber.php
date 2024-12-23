<?php

namespace Drupal\zcs_custom\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Class AnonymousRedirectSubscriber.
 */
class AnonymousRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new AnonymousRedirectSubscriber object.
   */
  public function __construct() {

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 100];
    return $events;
  }

  /**
   * This method is called when the kernel.request is dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function onRequest(RequestEvent $event) {
    $skip_user_login_path = TRUE;
    $redirect_path = '/login';

      if (\Drupal::service('path.current')->getPath() == '/login') {
        $skip_user_login_path = FALSE;
      }
     if (\Drupal::currentUser()->isAnonymous()
        && $skip_user_login_path
        && \Drupal::service('path.current')->getPath() !== '/user/register'
        && \Drupal::service('path.current')->getPath() !== '/user/password'
        && \Drupal::service('path.current')->getPath() !== '/rest/session/token'
        && \Drupal::service('path.current')->getPath() !== '/session/token'
        && \Drupal::service('path.current')->getPath() !== '/analytics/node/add'
        && \Drupal::service('path.current')->getPath() !== '/user/login'
        && (strpos(\Drupal::service('path.current')->getPath(), '/user/reset') === FALSE)
        && (strpos(\Drupal::service('path.current')->getPath(), '/user/registrationpassword') === FALSE)
        && (strpos(\Drupal::service('path.current')->getPath(), '/verify_invitation') === FALSE)
        && (strpos(\Drupal::service('path.current')->getPath(), '/verify_client_invitation') === FALSE)
        ) {
      $response = new RedirectResponse($redirect_path);
      $event->setResponse($response);
    }
  }

}
