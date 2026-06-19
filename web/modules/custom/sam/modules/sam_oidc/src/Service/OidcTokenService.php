<?php

namespace Drupal\sam_oidc\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Http\ClientFactory;

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
   * Validates an ID token signature using the provider JWKS.
   *
   * @param string $jwt
   *   The ID token JWT.
   * @param array $discovery
   *   The OIDC discovery document.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the JWT structure is invalid.
   * @throws \RuntimeException
   *   Thrown when the JWT signature cannot be validated.
   */
  public function validateIdTokenSignature(string $jwt, array $discovery): void {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
      throw new \InvalidArgumentException('Invalid JWT format: expected 3 parts.');
    }

    [$header_b64, $payload_b64, $signature_b64] = $parts;

    $header = Json::decode($this->base64UrlDecode($header_b64));
    if (!is_array($header)) {
      throw new \InvalidArgumentException('Invalid JWT header: unable to decode JSON.');
    }

    $algorithm = $header['alg'] ?? NULL;
    if ($algorithm !== 'RS256') {
      throw new \RuntimeException('Unsupported JWT signing algorithm.');
    }

    $jwks_uri = $discovery['jwks_uri'] ?? NULL;
    if (!is_string($jwks_uri) || $jwks_uri === '') {
      throw new \RuntimeException('OIDC discovery document does not contain a JWKS URI.');
    }

    $jwks = $this->fetchJwks($jwks_uri);
    $keys = $jwks['keys'] ?? NULL;
    if (!is_array($keys)) {
      throw new \RuntimeException('Invalid JWKS document.');
    }

    $key = $this->findSigningKey($keys, $header['kid'] ?? NULL, $algorithm);
    if ($key === NULL) {
      throw new \RuntimeException('Unable to find a matching JWT signing key.');
    }

    $public_key = $this->jwkToPem($key);
    $signed_data = $header_b64 . '.' . $payload_b64;
    $signature = $this->base64UrlDecode($signature_b64);

    $result = openssl_verify($signed_data, $signature, $public_key, OPENSSL_ALGO_SHA256);
    if ($result !== 1) {
      throw new \RuntimeException('Invalid JWT signature.');
    }
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
    string $expectedEmail,
    ?string $expectedHostedDomain = null,
  ):void {
    
    $email =
      $claims['email']
      ?? $claims['preferred_username']
      ?? $claims['upn']
      ?? NULL;

    if (($claims['iss'] ?? null) !== $expectedIssuer) {
      throw new \RuntimeException('Invalid issuer');
    }

    if ((trim($claims['aud']) ?? null) !== trim($expectedAudience)) {
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

    if (empty($email) || ($email !== $expectedEmail)) {
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
  public function decode(string $jwt): array {
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
   * Fetches a JWKS document.
   *
   * @param string $jwks_uri
   *   The JWKS URI.
   *
   * @return array
   *   The decoded JWKS document.
   *
   * @throws \RuntimeException
   *   Thrown when the JWKS document cannot be decoded.
   */
  private function fetchJwks(string $jwks_uri): array {
    $client = $this->httpClientFactory->fromOptions(['timeout' => 5]);
    $response = $client->get($jwks_uri);

    $jwks = Json::decode((string) $response->getBody());
    if (!is_array($jwks)) {
      throw new \RuntimeException('Unable to decode JWKS document.');
    }

    return $jwks;
  }

  /**
   * Finds a matching signing key in a JWKS key set.
   *
   * @param array $keys
   *   The JWKS keys.
   * @param string|null $kid
   *   The JWT key ID.
   * @param string $algorithm
   *   The JWT signing algorithm.
   *
   * @return array|null
   *   The matching JWK, or NULL when none is found.
   */
  private function findSigningKey(array $keys, ?string $kid, string $algorithm): ?array {
    foreach ($keys as $key) {
      if (!is_array($key)) {
        continue;
      }

      if (($key['kty'] ?? NULL) !== 'RSA') {
        continue;
      }

      if (($key['use'] ?? 'sig') !== 'sig') {
        continue;
      }

      if (isset($key['alg']) && $key['alg'] !== $algorithm) {
        continue;
      }

      if ($kid !== NULL && (($key['kid'] ?? NULL) !== $kid)) {
        continue;
      }

      return $key;
    }

    return NULL;
  }

  /**
   * Converts an RSA JWK to a PEM public key or certificate.
   *
   * @param array $key
   *   The JWK.
   *
   * @return string
   *   The PEM encoded key.
   *
   * @throws \RuntimeException
   *   Thrown when the key cannot be converted.
   */
  private function jwkToPem(array $key): string {
    if (!empty($key['x5c'][0]) && is_string($key['x5c'][0])) {
      return "-----BEGIN CERTIFICATE-----\n"
        . chunk_split($key['x5c'][0], 64, "\n")
        . "-----END CERTIFICATE-----\n";
    }

    if (empty($key['n']) || empty($key['e']) || !is_string($key['n']) || !is_string($key['e'])) {
      throw new \RuntimeException('JWK does not contain an x5c certificate or RSA modulus/exponent.');
    }

    $modulus = $this->base64UrlDecode($key['n']);
    $exponent = $this->base64UrlDecode($key['e']);

    $rsa_public_key = $this->derSequence(
      $this->derInteger($modulus)
      . $this->derInteger($exponent)
    );

    $algorithm_identifier = $this->derSequence(
      $this->derObjectIdentifier('1.2.840.113549.1.1.1')
      . $this->derNull()
    );

    $subject_public_key_info = $this->derSequence(
      $algorithm_identifier
      . $this->derBitString($rsa_public_key)
    );

    return "-----BEGIN PUBLIC KEY-----\n"
      . chunk_split(base64_encode($subject_public_key_info), 64, "\n")
      . "-----END PUBLIC KEY-----\n";
  }

  /**
   * Encodes a DER sequence.
   *
   * @param string $value
   *   The DER value.
   *
   * @return string
   *   The DER encoded sequence.
   */
  private function derSequence(string $value): string {
    return "\x30" . $this->derLength(strlen($value)) . $value;
  }

  /**
   * Encodes a DER integer.
   *
   * @param string $value
   *   The integer bytes.
   *
   * @return string
   *   The DER encoded integer.
   */
  private function derInteger(string $value): string {
    $value = ltrim($value, "\x00");

    if ($value === '') {
      $value = "\x00";
    }

    if ((ord($value[0]) & 0x80) !== 0) {
      $value = "\x00" . $value;
    }

    return "\x02" . $this->derLength(strlen($value)) . $value;
  }

  /**
   * Encodes a DER bit string.
   *
   * @param string $value
   *   The bit string bytes.
   *
   * @return string
   *   The DER encoded bit string.
   */
  private function derBitString(string $value): string {
    $value = "\x00" . $value;
    return "\x03" . $this->derLength(strlen($value)) . $value;
  }

  /**
   * Encodes a DER object identifier.
   *
   * @param string $oid
   *   The object identifier.
   *
   * @return string
   *   The DER encoded object identifier.
   */
  private function derObjectIdentifier(string $oid): string {
    $parts = array_map('intval', explode('.', $oid));
    if (count($parts) < 2) {
      throw new \InvalidArgumentException('Invalid object identifier.');
    }

    $encoded = chr((40 * $parts[0]) + $parts[1]);

    for ($i = 2; $i < count($parts); $i++) {
      $encoded .= $this->derBase128($parts[$i]);
    }

    return "\x06" . $this->derLength(strlen($encoded)) . $encoded;
  }

  /**
   * Encodes a DER NULL value.
   *
   * @return string
   *   The DER encoded NULL value.
   */
  private function derNull(): string {
    return "\x05\x00";
  }

  /**
   * Encodes a DER length.
   *
   * @param int $length
   *   The length.
   *
   * @return string
   *   The DER encoded length.
   */
  private function derLength(int $length): string {
    if ($length < 128) {
      return chr($length);
    }

    $bytes = '';
    while ($length > 0) {
      $bytes = chr($length & 0xff) . $bytes;
      $length >>= 8;
    }

    return chr(0x80 | strlen($bytes)) . $bytes;
  }

  /**
   * Encodes an integer using DER base-128 encoding.
   *
   * @param int $value
   *   The value.
   *
   * @return string
   *   The DER base-128 encoded value.
   */
  private function derBase128(int $value): string {
    $bytes = chr($value & 0x7f);
    $value >>= 7;

    while ($value > 0) {
      $bytes = chr(($value & 0x7f) | 0x80) . $bytes;
      $value >>= 7;
    }

    return $bytes;
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
