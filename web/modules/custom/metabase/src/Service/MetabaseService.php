<?php

namespace Drupal\metabase\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for interacting with the Metabase API.
 */
class MetabaseService {

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

  /**
   * Get the base URL from configuration.
   *
   * @return string
   *   The base URL.
   */
  protected function getBaseUrl() {
    return $this->configFactory->get('metabase.settings')->get('metabase.internal.base_url');
  }

  /**
   * Get the API token from configuration.
   *
   * @return string
   *   The API token.
   */
  protected function getApiToken() {
    return $this->configFactory->get('metabase.settings')->get('api.api_token');
  }

  /**
   * Makes a request to the Metabase API.
   *
   * @param string $endpoint
   *   The API endpoint to call.
   * @param string $method
   *   The HTTP method to use (GET, POST, PUT, DELETE).
   * @param array $data
   *   The data to send with the request.
   * @param array $headers
   *   Additional headers to send with the request.
   *
   * @return array|null
   *   The response data as an array, or NULL if an error occurred.
   */
  public function request($endpoint, $method = 'GET', array $data = [], array $headers = []) {
    // Make sure we have the required settings.
    $base_url = $this->getBaseUrl();
    $api_token = $this->getApiToken();

    if (empty($base_url) || empty($api_token)) {
      $this->loggerFactory->get('metabase')->error('Metabase API settings are not configured');
      return NULL;
    }

    // Prepare the URL.
    $url = rtrim($base_url, '/') . '/' . ltrim($endpoint, '/');

    // Set up default headers.
    $default_headers = [
      'Content-Type' => 'application/json',
      'Accept' => 'application/json',
      'X-Metabase-Session' => $api_token,
      'X-Api-Key' => $api_token,
    ];

    // Merge with any custom headers.
    $request_headers = array_merge($default_headers, $headers);

    // Prepare the request options.
    $options = [
      'headers' => $request_headers,
    ];

    // Add data to the request.
    if (!empty($data)) {
      if ($method === 'GET') {
        $options['query'] = $data;
      }
      else {
        $options['json'] = $data;
      }
    }

    try {
      // Make the request.
      $response = $this->httpClient->request($method, $url, $options);

      // Parse the response.
      $contents = $response->getBody()->getContents();
      $result = !empty($contents) ? json_decode($contents, TRUE) : [];

      return $result;
    }
    catch (RequestException $e) {
      $this->loggerFactory->get('metabase')->error(
            'Error calling Metabase API: @error', [
              '@error' => $e->getMessage(),
            ]
        );
      return NULL;
    }
  }

  /**
   * Helper method to make a GET request.
   *
   * @param string $endpoint
   *   The API endpoint.
   * @param array $params
   *   Query parameters.
   * @param array $headers
   *   Custom headers.
   *
   * @return array|null
   *   The response data.
   */
  public function get($endpoint, array $params = [], array $headers = []) {
    return $this->request($endpoint, 'GET', $params, $headers);
  }

  /**
   * Helper method to make a POST request.
   *
   * @param string $endpoint
   *   The API endpoint.
   * @param array $data
   *   The data to send.
   * @param array $headers
   *   Custom headers.
   *
   * @return array|null
   *   The response data.
   */
  public function post($endpoint, array $data = [], array $headers = []) {
    return $this->request($endpoint, 'POST', $data, $headers);
  }

  /**
   * Helper method to make a PUT request.
   *
   * @param string $endpoint
   *   The API endpoint.
   * @param array $data
   *   The data to send.
   * @param array $headers
   *   Custom headers.
   *
   * @return array|null
   *   The response data.
   */
  public function put($endpoint, array $data = [], array $headers = []) {
    return $this->request($endpoint, 'PUT', $data, $headers);
  }

  /**
   * Helper method to make a DELETE request.
   *
   * @param string $endpoint
   *   The API endpoint.
   * @param array $params
   *   Query parameters.
   * @param array $headers
   *   Custom headers.
   *
   * @return array|null
   *   The response data.
   */
  public function delete($endpoint, array $params = [], array $headers = []) {
    return $this->request($endpoint, 'DELETE', $params, $headers);
  }

}
