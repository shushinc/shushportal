<?php

namespace Drupal\metabase\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use GuzzleHttp\ClientInterface;

/**
 * Class ProxyService.
 */
class ProxyService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new MetabaseService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
  }


  public function process(Request $request) {

    $config = $this->configFactory->get('metabase.settings');

    $targetSite = $config->get('metabase.external.base_url');

    $path = $request->getPathInfo();
    $path = str_replace('/proxy', '', $path);

    // Construct the target URL.
    $targetUrl = $targetSite . $path;
    if (empty($path)) {
      $targetUrl = $targetSite;
    }

    // Get query parameters.
    $queryString = $request->getQueryString();
    if ($queryString) {
      $targetUrl .= '?' . $queryString;
    }

    // Get request headers.
    $headers = [];
    foreach ($request->headers->all() as $key => $value) {
      if (strtolower($key) !== 'host') {
        $headers[$key] = $value[0];
      }
    }

    try {
      // Make the request to the target site.
      $options = [
        'headers' => $headers,
        'body' => $request->getContent(),
        // 'cookies' => $request->cookies->all(),
        'allow_redirects' => false,
        'http_errors' => false,
      ];

      // Send the request.
      $proxyResponse = $this->httpClient->request(
        $request->getMethod(),
        $targetUrl,
        $options
      );

      // Get response headers.
      $responseHeaders = [];
      $excludedHeaders = ['content-encoding', 'content-length', 'transfer-encoding', 'connection'];

      foreach ($proxyResponse->getHeaders() as $name => $values) {
        if (!in_array(strtolower($name), $excludedHeaders)) {
          $responseHeaders[$name] = implode(', ', $values);
        }
      }

      // For GET requests that match CSS files, modify the content.
      if (
        $request->isMethod('GET') &&
        strpos($targetUrl, '/app/dist/styles.') !== FALSE &&
        strpos($targetUrl, '.css') !== FALSE
      ) {

        // Get the entire content.
        $content = (string) $proxyResponse->getBody();

        $css = $config->get('overwrite.css');

        if ($css) {
          $content .= $css;
        }
        // $content .= 'footer{display:none !important;}';

        // Create and return the response.
        $response = new Response(
          $content,
          $proxyResponse->getStatusCode(),
          $responseHeaders
        );

        return $response;
      }
      elseif ($request->isMethod('GET') &&
        strpos($targetUrl, '/app/dist/runtime.') !== FALSE &&
        strpos($targetUrl, '.js') !== FALSE
      ){
        // Get the entire content.
        $content = (string) $proxyResponse->getBody();

        $js = $config->get('overwrite.js');

        if ($js) {
          $content .= $js;
        }

        // Create and return the response.
        $response = new Response(
          $content,
          $proxyResponse->getStatusCode(),
          $responseHeaders
        );

        return $response;
      }
      else {
        // For other requests, return the response as is.
        $response = new Response(
          (string) $proxyResponse->getBody(),
          $proxyResponse->getStatusCode(),
          $responseHeaders
        );

        return $response;
      }
    }
    catch (RequestException $e) {
      return new Response('Error: ' . $e->getMessage(), 500);
    }

  }
}
