<?php

namespace Drupal\metalite\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for interacting with the Metalite API.
 */
class MetaliteService {

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
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new MetaliteService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    MessengerInterface $messenger,
  ) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('metalite');
    $this->messenger = $messenger;
  }

  /**
   * Get the embed URL for a dashboard.
   *
   * @return string|null
   *   The embed URL, or NULL if there was an error.
   */
  public function getEmbedUrl() {
    $config = $this->configFactory->get('metalite.settings');
    $remote_base_url = $config->get('remote_base_url');
    $api_key = $config->get('api_key');
    $dashboard_id = $config->get('dashboard_id');

    // Ensure we have all required configuration.
    if (empty($remote_base_url) || empty($api_key) || empty($dashboard_id)) {
      $this->logger->error('Missing required configuration for Metalite API.');
      return NULL;
    }

    // Display URL in the UI.
    $this->messenger->addMessage("{$remote_base_url}/{$dashboard_id}");

    try {
      $response = $this->httpClient->request(
        'GET',
        "{$remote_base_url}/{$dashboard_id}",
        [
          'headers' => [
            'Authorization' => "Bearer {$api_key}",
            'Accept' => 'application/json',
          ],
        ]
      );

      $data = Json::decode($response->getBody()->getContents());

      if (isset($data['embed_url'])) {
        return $data['embed_url'];
      }
      else {
        $this->logger->error('Embed URL not found in API response.');
        return NULL;
      }
    }
    catch (RequestException $e) {
      $this->logger->error('Error fetching embed URL from Metalite API: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

}
