<?php

namespace Drupal\sam_oidc\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\ClientInterface;

final class OidcTokenService {

  public function __construct(
    private readonly ClientFactory $httpClientFactory,
  ) {}

  /**
   * Exchanges an authorization code for OIDC tokens.
   */
  public function exchangeCodeForTokens(
    array $discovery,
    string $code,
    string $redirectUri,
    string $clientId,
    string $clientSecret,
  ): array {
    $client = $this->httpClientFactory->fromOptions();

    $response = $client->post($discovery['token_endpoint'], [
      'form_params' => [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
      ],
    ]);

    return Json::decode((string) $response->getBody());
  }

  /**
   * Summary of validateIdTokenClaims
   * @param array $claims
   * @param string $expectedIssuer
   * @param string $expectedAudience
   * @param string $expectedNonce
   * @param mixed $expectedHostedDomain
   * @throws \RuntimeException
   * @return void
   */
  public function validateIdTokenClaims(
    array $claims,
    string $expectedIssuer,
    string $expectedAudience,
    string $expectedNonce,
    ?string $expectedHostedDomain = null
  ):void {
    
    if (($claims['iss'] ?? null) !== $expectedIssuer) {
      throw new \RuntimeException('Invalid issuer');
    }

    if (($claims['aud'] ?? null) !== $expectedAudience) {
      throw new \RuntimeException('Invalid audience');
    }

    if (($claims['exp'] ?? 0) < time()) {
      throw new \RuntimeException('Token expired');
    }

    if (($claims['nonce'] ?? null) !== $expectedNonce) {
      throw new \RuntimeException('Invalid nonce');
    }

    if ($expectedHostedDomain && ($claims['hd'] ?? null) !== $expectedHostedDomain) {
      throw new \RuntimeException('Invalid hosted domain');
    }

    if (empty($claims['email']) || empty($claims['email_verified'])) {
      throw new \RuntimeException('Email not verified');
    }

  }

  /**
   * Decodes a JWT payload without verifying its signature.
   *
   * @param string $jwt
   * @throws \InvalidArgumentException
   * @return array
   */
  public function decodeWithoutVerification(string $jwt): array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
      throw new \InvalidArgumentException('Invalid JWT format: expected 3 parts.');
    }

    // We only need payload for V1 claim validation.
    $payloadB64 = $parts[1];
    $payloadJson = $this->base64UrlDecode($payloadB64);

    $claims = json_decode($payloadJson, TRUE);
    if (!is_array($claims)) {
      throw new \InvalidArgumentException('Invalid JWT payload: unable to decode JSON.');
    }

    return $claims;
  }

  /**
   * Base64URL decodes a string.
   * 
   * @param string $data
   * @throws \InvalidArgumentException
   * @return bool|string
   */
  private function base64UrlDecode(string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder !== 0) {
      $data .= str_repeat('=', 4 - $remainder);
    }

    $decoded = base64_decode(strtr($data, '-_', '+/'), TRUE);
    if ($decoded === FALSE) {
      throw new \InvalidArgumentException('Invalid base64url encoding in JWT.');
    }

    return $decoded;
  }

}
