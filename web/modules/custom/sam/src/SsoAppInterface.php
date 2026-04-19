<?php

namespace Drupal\sam;

interface SsoAppInterface {

  /**
   * Returns the app ID.
   */
  public function id(): string;

  /**
   * Returns the app label.
   */
  public function label(): string;

  /**
   * Returns the email domain associated with this SSO app.
   */
  public function getDomain(): string;

  /**
   * Returns the provider ID (e.g. google, okta, microsoft).
   */
  public function getProvider(): string;

  /**
   * Returns all provider-specific settings.
   */
  public function getSettings(): array;

  /**
   * Returns a single provider-specific setting.
   *
   * @param string $key
   *   The setting key.
   * @param mixed $default
   *   Default value if the key does not exist.
   */
  public function getSetting(string $key, $default = NULL);

  /**
   * Indicates whether this SSO app is enabled.
   */
  public function isEnabled(): bool;

}
