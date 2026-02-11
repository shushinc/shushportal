<?php

namespace Drupal\sam_oidc\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Performs OpenID Connect discovery using the well-known endpoint.
 */
final class OidcDiscoveryService {

  /**
   * The HTTP client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  private ClientFactory $httpClientFactory;

  private $logger;

  /**
   * Constructs the discovery service.
   *
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   The HTTP client factory.
   */
  public function __construct(ClientFactory $http_client_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->httpClientFactory = $http_client_factory;
    $this->logger = $logger_factory->get('sam_oidc');
  }

  /**
   * Fetches the OpenID Connect configuration document.
   *
   * @param string $issuer
   *   The issuer base URL.
   *
   * @return array
   *   The decoded discovery document.
   */
  public function discover(string $issuer): array {
    $url = rtrim($issuer, '/') . '/.well-known/openid-configuration';

    try {
      $response = $this->httpClientFactory
        ->fromOptions(['timeout' => 5])
        ->get($url);

      return Json::decode((string) $response->getBody());
    }
    catch (RequestException $e) {
      $this->logger->error('OIDC discovery failed for @issuer: @message', [
        '@issuer' => $issuer,
        '@message' => $e->getMessage(),
      ]);

      throw $e;
    }
  }

}
