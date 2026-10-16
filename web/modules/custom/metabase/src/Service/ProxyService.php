<?php

namespace Drupal\metabase\Service;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Dashboard filters whose dropdown values are limited to the user's groups.
   *
   * Matched against the Metabase parameter slug or name (lowercase).
   */
  const END_CUSTOMER_PARAMS = ['end_customers', 'end_customer', 'end customers'];

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
    AccountProxyInterface $current_user,
    Connection $database,
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->cache = $cache;
    $this->currentUser = $current_user;
    $this->database = $database;
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

      // Dropdown values for a dashboard filter: limit End Customers to the
      // ones that belong to the current user's groups.
      if (preg_match('#^/api/embed/dashboard/([^/]+)/params/([^/]+)/(values|search/)#', $path, $matches)) {
        $content = (string) $proxyResponse->getBody();
        if ($proxyResponse->getStatusCode() == 200) {
          $content = $this->filterEndCustomerValues($matches[1], $matches[2], $content);
        }
        $response = new Response(
          $content,
          $proxyResponse->getStatusCode(),
          $responseHeaders
        );
        // The list differs per user, so it must never be shared or cached.
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
      }

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
    $cache_key = 'metabase:' . $type . ':' . md5($path ?? ' ') . ':' . md5($queryString ?? '?');
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

  /**
   * Whether the current user may see every client's data.
   */
  protected function canViewAllData(): bool {
    if ((int) $this->currentUser->id() === 1) {
      return TRUE;
    }
    return (bool) array_intersect(['administrator', 'carrier_admin'], $this->currentUser->getRoles());
  }

  /**
   * Removes end customers outside the user's groups from a values response.
   *
   * @param string $token
   *   The signed embed token from the request path.
   * @param string $param_id
   *   The Metabase dashboard parameter id.
   * @param string $content
   *   The JSON body returned by Metabase.
   *
   * @return string
   *   The (possibly filtered) JSON body.
   */
  protected function filterEndCustomerValues($token, $param_id, $content) {
    if ($this->canViewAllData() || !$this->isEndCustomerParam($token, $param_id)) {
      return $content;
    }

    $data = json_decode($content, TRUE);
    if (!is_array($data)) {
      return $content;
    }

    $allowed = array_flip($this->getAllowedEndCustomers());
    $keep = function ($row) use ($allowed) {
      $value = is_array($row) ? reset($row) : $row;
      return is_scalar($value) && isset($allowed[(string) $value]);
    };

    if (isset($data['values']) && is_array($data['values'])) {
      // Format: {"values": [["Name"], ...], "has_more_values": false}.
      $data['values'] = array_values(array_filter($data['values'], $keep));
      $data['has_more_values'] = FALSE;
    }
    elseif (array_is_list($data)) {
      // Older search format: [["Name"], ...].
      $data = array_values(array_filter($data, $keep));
    }

    return json_encode($data);
  }

  /**
   * Checks whether a dashboard parameter is the End Customers filter.
   */
  protected function isEndCustomerParam($token, $param_id) {
    $cache_key = 'metabase:params:' . hash('sha256', $token);
    if ($cache = $this->cache->get($cache_key)) {
      $params = $cache->data;
    }
    else {
      $base = $this->configFactory->get('metabase.settings')->get('metabase.external.base_url');
      try {
        $result = $this->httpClient->request('GET', rtrim($base, '/') . '/api/embed/dashboard/' . $token, [
          'headers' => ['Accept' => 'application/json'],
          'http_errors' => FALSE,
        ]);
      }
      catch (\Exception $e) {
        $this->loggerFactory->get('metabase')->error('Could not load dashboard parameters: @error', ['@error' => $e->getMessage()]);
        return FALSE;
      }
      if ($result->getStatusCode() != 200) {
        return FALSE;
      }
      $dashboard = json_decode((string) $result->getBody(), TRUE);
      $params = [];
      foreach ($dashboard['parameters'] ?? [] as $parameter) {
        $params[$parameter['id']] = [
          strtolower($parameter['slug'] ?? ''),
          strtolower($parameter['name'] ?? ''),
        ];
      }
      $this->cache->set($cache_key, $params, \Drupal::time()->getRequestTime() + 3600);
    }

    if (!isset($params[$param_id])) {
      return FALSE;
    }
    return (bool) array_intersect($params[$param_id], self::END_CUSTOMER_PARAMS);
  }

  /**
   * Gets the names of end customers linked to the user's groups.
   *
   * An end customer belongs to a group when a published analytics node has
   * that group as its partner and that end customer as its end customer.
   *
   * @return string[]
   *   End customer term names.
   */
  protected function getAllowedEndCustomers() {
    $query = "SELECT DISTINCT t.name
      FROM {group_relationship_field_data} gr
      INNER JOIN {node__field_partner} np
        ON np.field_partner_target_id = gr.gid AND np.deleted = 0
      INNER JOIN {node_field_data} n
        ON n.nid = np.entity_id AND n.type = 'analytics' AND n.status = 1
      INNER JOIN {node__field_end_customer} ec
        ON ec.entity_id = n.nid AND ec.deleted = 0
      INNER JOIN {taxonomy_term_field_data} t
        ON t.tid = ec.field_end_customer_target_id AND t.vid = 'analytics_customer'
      WHERE gr.plugin_id = 'group_membership'
        AND gr.entity_id = :uid";

    return $this->database->query($query, [':uid' => (int) $this->currentUser->id()])->fetchCol();
  }

}
