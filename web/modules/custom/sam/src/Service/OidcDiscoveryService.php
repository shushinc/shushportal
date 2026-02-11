<?php

namespace Drupal\sam\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service responsible for OpenID Connect discovery.
 */
final class OidcDiscoveryService {

  private ClientFactory $httpClientFactory;
  private $logger;

  public function __construct(
    ClientFactory $http_client_factory,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->httpClientFactory = $http_client_factory;
    $this->logger = $logger_factory->get('sam_oidc');
  }

  /**
   * Fetches the OIDC discovery document for a given issuer.
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
