<?php

namespace Drupal\metabase\Service;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\ClientInterface;
use Http\Client\Exception\RequestException;

/**
 * Metabase proxy service.
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
   * The cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a new MetabaseService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    CacheBackendInterface $cache,
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->cache = $cache;
  }

  /**
   * Process a request.
   */
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
        'allow_redirects' => FALSE,
        'http_errors' => FALSE,
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

      // $contentType = $proxyResponse->getHeader('Content-Type');
      // if (is_array($contentType) && count($contentType) > 0) {
      //   $contentType = $contentType[0];
      //   \Drupal::logger('metabase')->info($contentType);
      // }

      // For GET requests that match CSS files, modify the content.
      if (
        strpos($targetUrl, '/app/dist/styles.') !== FALSE &&
        strpos($targetUrl, '.css') !== FALSE
      ) {

        $content = (string) $proxyResponse->getBody();
        $content = $this->cachedContent($request, 'css', $content, $path, $queryString);

        // Create and return the response.
        $response = new Response(
          $content,
          $proxyResponse->getStatusCode(),
          $responseHeaders
        );
        $response->setMaxAge(3600);
        $response->setPublic();

        return $response;
      }
      elseif (
        strpos($targetUrl, '/app/dist/runtime.') !== FALSE &&
        strpos($targetUrl, '.js') !== FALSE
      ) {
        $content = (string) $proxyResponse->getBody();
        $content = $this->cachedContent($request, 'js', $content, $path, $queryString);

        // Create and return the response.
        $response = new Response(
          $content,
          $proxyResponse->getStatusCode(),
          $responseHeaders
        );
        $response->setMaxAge(3600);
        $response->setPublic();

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

  /**
   * Process a request.
   */
  public function processFont(Request $request, $path = '') {
    $config = $this->configFactory->get('metabase.settings');

    $targetSite = $config->get('metabase.external.base_url');

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
        'allow_redirects' => FALSE,
        'http_errors' => FALSE,
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

      // Create and return the response.
      $response = new Response(
        $proxyResponse->getBody(),
        $proxyResponse->getStatusCode(),
        $responseHeaders
      );
      $response->setMaxAge(3600);
      $response->setPublic();

      return $response;
    }
    catch (RequestException $e) {
      return new Response('Error: ' . $e->getMessage(), 500);
    }
  }

  private function cachedContent(Request $request, $type, $content, $path, $queryString) {
    $cache_key = 'metabase:' . $type . ':' . md5($path) . ':' . md5($queryString);
    $config = $this->configFactory->get('metabase.settings');

    // Try to get from cache first
    if ($cache = $this->cache->get($cache_key)) {
      $content = $cache->data;
    } else {

      if ($type == 'css') {
        $css = "\r\n" . $config->get('overwrite.css');
        if ($css) {
          $content .= $css;
        }
      }
      elseif ($type == 'js') {
        $js = "\r\n" . $config->get('overwrite.js');
        if ($js) {
          $content .= $js;
        }
      }

      $this->cache->set(
        $cache_key,
        $content,
        \Drupal::time()->getRequestTime() + 3600,
        ['metabase:css']
      );
    }
    return $content;
  }

}
