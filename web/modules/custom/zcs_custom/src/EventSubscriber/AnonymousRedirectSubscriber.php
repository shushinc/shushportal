<?php

namespace Drupal\zcs_custom\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;

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
    $events[KernelEvents::REQUEST][] = ['checkRedirect', 30];
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
        && \Drupal::service('path.current')->getPath() !== '/external-login/callback'
        && (strpos(\Drupal::service('path.current')->getPath(), '/user/reset') === FALSE)
        && (strpos(\Drupal::service('path.current')->getPath(), '/user/registrationpassword') === FALSE)
        && (strpos(\Drupal::service('path.current')->getPath(), '/verify_invitation') === FALSE)
        && (strpos(\Drupal::service('path.current')->getPath(), '/verify_client_invitation') === FALSE)
        ) {
      $response = new RedirectResponse($redirect_path);
      $event->setResponse($response);
    }
    $current_user = \Drupal::currentUser();
    if (!\Drupal::currentUser()->isAnonymous()) {
      $request = $event->getRequest();
     // dump($request);
      $current_path = $request->getPathInfo();
      $user_id = $current_user->id();
      // Check if the current path matches 'user/{uid}/edit'.
      if (preg_match('/^\/user\/(\d+)\/edit$/', $current_path, $matches)) {
        $uid = $matches[1];
        $query_params = $request->query->all();

        // Check for the specific query parameter.
        if (!empty($query_params['pass-reset-token'])) {
          // Build the new URL. Replace 'your.new.route' with your actual route.
          // Let the user's password be changed without the current password check.
          $token = Crypt::randomBytesBase64(55);
          $_SESSION['pass_reset_' .  $user_id] = $token;
          // Create the URL for the redirection with dynamic parameters.
          $url = Url::fromRoute('change_pwd_page.change_password_form', [
            'user' => $user_id,
          ], [
            'query' => ['pass-reset-token' => $token],
            'absolute' => TRUE,
          ])->toString();
          $response = new RedirectResponse($url);
          $event->setResponse($response);
        }
      }
    }
  }


  public function checkRedirect(RequestEvent $event) {
    // Ensure this runs only for authenticated users
    if (\Drupal::currentUser()->isAuthenticated()) {
      $request = $event->getRequest();
      $path = $request->getPathInfo(); // Get the current URL path
      $queryParams = $request->query->all(); // Get query parameters

      // Check if user is visiting /user/{uid} with ?check_logged_in=1
      if (preg_match('/^\/user\/\d+$/', $path) && isset($queryParams['check_logged_in'])) {
        $response = new RedirectResponse(Url::fromRoute('<front>')->toString());
        $event->setResponse($response);
      }
      if (preg_match('/^\/user\/\d+$/', $path)) {
        $response = new RedirectResponse(Url::fromRoute('<front>')->toString());
        $event->setResponse($response);
      }
    }
  }


}
