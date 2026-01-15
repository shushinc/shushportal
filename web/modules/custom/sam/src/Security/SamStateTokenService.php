<?php

namespace Drupal\sam\Security;

use Drupal\Component\Utility\Crypt;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Default implementation of the state token manager.
 *
 * Stores the state token in the session for the duration of the
 * authentication flow.
 */
final class SamStateTokenService implements SamStateTokenInterface {

  /**
   * Session key used to store the state token.
   */
  private const SESSION_KEY = 'sam_sso_state';

  /**
   * The Symfony session service.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  private SessionInterface $session;

  /**
   * Constructs the state token service.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session service.
   */
  public function __construct(SessionInterface $session) {
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    $state = Crypt::randomBytesBase64();
    $this->session->set(self::SESSION_KEY, $state);

    return $state;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(string $state): bool {
    $stored_state = $this->session->get(self::SESSION_KEY);

    // Always invalidate the stored state token to prevent replay attacks.
    $this->session->remove(self::SESSION_KEY);

    return !empty($stored_state) && hash_equals($stored_state, $state);
  }

}
