<?php

namespace Drupal\customer_management\Service;

/**
 * Generates Client IDs and Hashed Keys for customer records.
 */
class CredentialGenerator {

  /**
   * Generates a client ID.
   */
  public function generateClientId(): string {
    return 'cli_' . bin2hex(random_bytes(8));
  }

  /**
   * Generates a hashed key.
   */
  public function generateHashedKey(): string {
    return bin2hex(random_bytes(32));
  }

}
