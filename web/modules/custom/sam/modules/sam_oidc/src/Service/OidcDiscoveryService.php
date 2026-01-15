<?php

namespace Drupal\sam_oidc\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Http\ClientFactory;

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

  /**
   * Constructs the discovery service.
   *
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   The HTTP client factory.
   */
  public function __construct(ClientFactory $http_client_factory) {
    $this->httpClientFactory = $http_client_factory;
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
    $client = $this->httpClientFactory->fromOptions([
      'base_uri' => $issuer,
    ]);

    $response = $client->get('.well-known/openid-configuration');

    return Json::decode((string) $response->getBody());
  }

}
