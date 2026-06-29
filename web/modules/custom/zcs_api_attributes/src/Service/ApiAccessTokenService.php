<?php

namespace Drupal\zcs_api_attributes\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for managing API access tokens.
 */
class ApiAccessTokenService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs an ApiAccessTokenService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    Connection $database,
    AccountProxyInterface $current_user,
    LoggerInterface $logger,
    RequestStack $request_stack
  ) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->logger = $logger;
    $this->requestStack = $request_stack;
  }

  /**
   * Generates a new API access token.
   *
   * @param int $client_id
   *   The client ID.
   * @param string $name
   *   The token name.
   * @param int|null $expires_at
   *   The expiration timestamp, or NULL for no expiration.
   *
   * @return array
   *   Array containing:
   *   - token_id: The database ID
   *   - full_token: The complete token (shown only once)
   *   - prefix: The token prefix
   */
  public function createToken(int $client_id, string $name, ?int $expires_at = NULL): array {
    $prefix = $this->generatePrefix();
    $secret = $this->generateSecret();
    $full_token = "shush_live_{$prefix}.{$secret}";
    $secret_hash = password_hash($secret, PASSWORD_DEFAULT);

    $current_time = time();
    $user_id = $this->currentUser->id();

    $token_id = $this->database->insert('api_access_token')
      ->fields([
        'client_id' => $client_id,
        'name' => $name,
        'token_prefix' => $prefix,
        'token_hash' => $secret_hash,
        'expires_at' => $expires_at,
        'active' => 1,
        'created_by' => $user_id,
        'created_date' => $current_time,
      ])
      ->execute();

    $this->logger->info('API token created: @name for client @client_id by user @user_id', [
      '@name' => $name,
      '@client_id' => $client_id,
      '@user_id' => $user_id,
    ]);

    return [
      'token_id' => $token_id,
      'full_token' => $full_token,
      'prefix' => $prefix,
    ];
  }

  /**
   * Regenerates an existing API access token.
   *
   * @param int $token_id
   *   The token ID to regenerate.
   *
   * @return array|null
   *   Array containing the new token data, or NULL if token not found.
   */
  public function regenerateToken(int $token_id): ?array {
    $token = $this->loadToken($token_id);
    if (!$token) {
      return NULL;
    }

    $prefix = $this->generatePrefix();
    $secret = $this->generateSecret();
    $full_token = "shush_live_{$prefix}.{$secret}";
    $secret_hash = password_hash($secret, PASSWORD_DEFAULT);

    $current_time = time();
    $user_id = $this->currentUser->id();

    $this->database->update('api_access_token')
      ->fields([
        'token_prefix' => $prefix,
        'token_hash' => $secret_hash,
        'active' => 1,
        'revoked_by' => NULL,
        'revoked_date' => NULL,
        'changed_by' => $user_id,
        'changed_date' => $current_time,
      ])
      ->condition('id', $token_id)
      ->execute();

    $this->logger->info('API token regenerated: @name (ID: @id) by user @user_id', [
      '@name' => $token->name,
      '@id' => $token_id,
      '@user_id' => $user_id,
    ]);

    return [
      'token_id' => $token_id,
      'full_token' => $full_token,
      'prefix' => $prefix,
    ];
  }

  /**
   * Enables a disabled token.
   *
   * @param int $token_id
   *   The token ID.
   *
   * @return bool
   *   TRUE on success, FALSE if token not found or expired.
   */
  public function enableToken(int $token_id): bool {
    $token = $this->loadToken($token_id);
    if (!$token) {
      return FALSE;
    }

    if ($this->isExpired($token)) {
      return FALSE;
    }

    $current_time = time();
    $user_id = $this->currentUser->id();

    $this->database->update('api_access_token')
      ->fields([
        'active' => 1,
        'revoked_by' => NULL,
        'revoked_date' => NULL,
        'changed_by' => $user_id,
        'changed_date' => $current_time,
      ])
      ->condition('id', $token_id)
      ->execute();

    $this->logger->info('API token enabled: @name (ID: @id) by user @user_id', [
      '@name' => $token->name,
      '@id' => $token_id,
      '@user_id' => $user_id,
    ]);

    return TRUE;
  }

  /**
   * Disables/revokes a token.
   *
   * @param int $token_id
   *   The token ID.
   *
   * @return bool
   *   TRUE on success, FALSE if token not found.
   */
  public function disableToken(int $token_id): bool {
    $token = $this->loadToken($token_id);
    if (!$token) {
      return FALSE;
    }

    $current_time = time();
    $user_id = $this->currentUser->id();

    $this->database->update('api_access_token')
      ->fields([
        'active' => 0,
        'revoked_by' => $user_id,
        'revoked_date' => $current_time,
        'changed_by' => $user_id,
        'changed_date' => $current_time,
      ])
      ->condition('id', $token_id)
      ->execute();

    $this->logger->info('API token disabled: @name (ID: @id) by user @user_id', [
      '@name' => $token->name,
      '@id' => $token_id,
      '@user_id' => $user_id,
    ]);

    return TRUE;
  }

  /**
   * Deletes a token.
   *
   * @param int $token_id
   *   The token ID.
   *
   * @return bool
   *   TRUE on success, FALSE if token not found.
   */
  public function deleteToken(int $token_id): bool {
    $token = $this->loadToken($token_id);
    if (!$token) {
      return FALSE;
    }

    $this->database->delete('api_access_token')
      ->condition('id', $token_id)
      ->execute();

    $this->logger->info('API token deleted: @name (ID: @id) by user @user_id', [
      '@name' => $token->name,
      '@id' => $token_id,
      '@user_id' => $this->currentUser->id(),
    ]);

    return TRUE;
  }

  /**
   * Loads a token by ID.
   *
   * @param int $token_id
   *   The token ID.
   *
   * @return object|null
   *   The token object or NULL if not found.
   */
  public function loadToken(int $token_id): ?object {
    $token = $this->database->select('api_access_token', 'aat')
      ->fields('aat')
      ->condition('id', $token_id)
      ->execute()
      ->fetchObject();

    return $token ?: NULL;
  }

  /**
   * Loads all tokens.
   *
   * @return array
   *   Array of token objects.
   */
  public function loadAllTokens(): array {
    return $this->database->select('api_access_token', 'aat')
      ->fields('aat')
      ->orderBy('created_date', 'DESC')
      ->execute()
      ->fetchAll();
  }

  /**
   * Validates an API token from an Authorization header.
   *
   * @param string $authorization_header
   *   The Authorization header value.
   *
   * @return array|null
   *   Array with token_id and client_id if valid, NULL otherwise.
   */
  public function validateToken(string $authorization_header): ?array {
    if (!str_starts_with($authorization_header, 'Bearer ')) {
      return NULL;
    }

    $full_token = substr($authorization_header, 7);
    
    if (!str_starts_with($full_token, 'shush_live_')) {
      return NULL;
    }

    $parts = explode('.', substr($full_token, 11), 2);
    if (count($parts) !== 2) {
      return NULL;
    }

    [$prefix, $secret] = $parts;

    $token = $this->database->select('api_access_token', 'aat')
      ->fields('aat')
      ->condition('token_prefix', $prefix)
      ->execute()
      ->fetchObject();

    if (!$token) {
      return NULL;
    }

    if (!$token->active) {
      return NULL;
    }

    if ($this->isExpired($token)) {
      return NULL;
    }

    if (!password_verify($secret, $token->token_hash)) {
      return NULL;
    }

    $this->updateLastUsed($token->id);

    return [
      'token_id' => (int) $token->id,
      'client_id' => (int) $token->client_id,
    ];
  }

  /**
   * Checks if a token is expired.
   *
   * @param object $token
   *   The token object.
   *
   * @return bool
   *   TRUE if expired, FALSE otherwise.
   */
  public function isExpired(object $token): bool {
    if (empty($token->expires_at)) {
      return FALSE;
    }

    return $token->expires_at < time();
  }

  /**
   * Updates the last used metadata for a token.
   *
   * @param int $token_id
   *   The token ID.
   */
  protected function updateLastUsed(int $token_id): void {
    $request = $this->requestStack->getCurrentRequest();
    
    $this->database->update('api_access_token')
      ->fields([
        'last_used' => time(),
        'last_used_ip' => $request ? $request->getClientIp() : NULL,
        'last_used_user_agent' => $request ? $request->headers->get('User-Agent') : NULL,
      ])
      ->condition('id', $token_id)
      ->execute();
  }

  /**
   * Generates a secure random prefix.
   *
   * @return string
   *   The prefix.
   */
  protected function generatePrefix(): string {
    $bytes = random_bytes(6);
    return strtoupper(bin2hex($bytes));
  }

  /**
   * Generates a secure random secret.
   *
   * @return string
   *   The secret.
   */
  protected function generateSecret(): string {
    $bytes = random_bytes(32);
    return bin2hex($bytes);
  }

  /**
   * Gets the status label for a token.
   *
   * @param object $token
   *   The token object.
   *
   * @return string
   *   The status label.
   */
  public function getStatusLabel(object $token): string {
    if ($this->isExpired($token)) {
      return 'Expired';
    }

    return $token->active ? 'Active' : 'Disabled';
  }

  /**
   * Gets the expiration label for a token.
   *
   * @param object $token
   *   The token object.
   *
   * @return string
   *   The expiration label.
   */
  public function getExpirationLabel(object $token): string {
    if (empty($token->expires_at)) {
      return 'Never';
    }

    $date = date('Y-m-d', $token->expires_at);
    
    if ($this->isExpired($token)) {
      return "Expired on {$date}";
    }

    return $date;
  }

}
