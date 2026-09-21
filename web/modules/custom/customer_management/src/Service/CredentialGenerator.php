<?php

namespace Drupal\customer_management\Service;

/**
 * Generates Client IDs and Hashed Keys for customer records.
 */
class CredentialGenerator {

  /**
   * Generates a client ID, e.g. "cli_9f8a2b1c4d3e7a1f".
   *
   * This is not a secret — it identifies the customer/account and is safe
   * to log or display.
   */
  public function generateClientId(): string {
    return 'cli_' . bin2hex(random_bytes(8));
  }

  /**
   * Generates a 64-character hex secret used as the HMAC key.
   *
   * This IS a secret. It must never be logged, and should only ever be
   * shown to an authorized user via the reveal-key endpoint, or sent once
   * to the customer contact by email.
   */
  public function generateHashedKey(): string {
    return bin2hex(random_bytes(32));
  }

}
