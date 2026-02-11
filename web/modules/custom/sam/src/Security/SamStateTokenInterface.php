<?php

namespace Drupal\sam\Security;

/**
 * Manages generation and validation of state tokens used during
 * SSO authentication flows.
 *
 * State tokens are used to prevent CSRF and replay attacks
 * during external authentication redirects (e.g. OIDC).
 */
interface SamStateTokenInterface {

  /**
   * Generates a cryptographically secure state token and persists it
   * for later validation.
   *
   * @return string
   *   The generated state token.
   */
  public function generate(): string;

  /**
   * Validates the received state token against the stored one.
   *
   * Implementations must ensure the stored token is invalidated
   * after validation to prevent replay attacks.
   *
   * @param string $state
   *   The state token received from the identity provider callback.
   *
   * @return bool
   *   TRUE if the token is valid, FALSE otherwise.
   */
  public function validate(string $state): bool;

}
