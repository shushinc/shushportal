<?php

namespace Drupal\metabase\EventSubscriber;

use Drupal\metabase\Service\ProxyService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Custom path event subscriber.
 */
class CustomPathSubscriber implements EventSubscriberInterface {

  /**
   * The proxy service.
   *
   * @var \Drupal\metabase\Service\ProxyService
   */
  protected $proxyService;

  /**
   * Constructs a ProxyController object.
   *
   * @param \Drupal\metabase\Service\ProxyService $proxy_service
   *   The proxy service.
   */
  public function __construct(ProxyService $proxy_service) {
    $this->proxyService = $proxy_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Use a high priority so this runs early, but not before routing.
    $events[KernelEvents::REQUEST][] = ['checkPath', 100];
    return $events;
  }

  /**
   * Checks the path and performs actions based on the path.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function checkPath(RequestEvent $event) {
    // Don't process subrequests.
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $path = $request->getPathInfo();

    // Define patterns that you want to catch.
    if (preg_match('|^/proxy/|', $path)) {
      $response = $this->proxyService->process($event->getRequest());
      $event->setResponse($response);
      return;
    }
  }

}
