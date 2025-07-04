<?php

namespace Drupal\metabase\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\metabase\Service\ProxyService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for proxying requests to another site.
 */
class ProxyController extends ControllerBase {

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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('metabase.proxy')
    );
  }

  /**
   * Proxies requests to the target site.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $path
   *   The path to proxy.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The proxied response.
   */
  public function proxy(Request $request, $path = '') {
    $response = $this->proxyService->process($request);
    return $response;
  }

  /**
   * Proxies requests to the target site.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $path
   *   The path to proxy.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The proxied response.
   */
  public function fonts(Request $request, $path = '') {
    $response = $this->proxyService->processFont($request, $path);
    return $response;
  }

}
