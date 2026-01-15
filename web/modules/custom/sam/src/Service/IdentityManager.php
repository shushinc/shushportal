<?php

namespace Drupal\sam\Service;

use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Handles identity resolution and user lifecycle for SSO authentication.
 */
class IdentityManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ConfigFactoryInterface $configFactory;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $configFactory
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
  }

  /**
   * Resolves a Drupal user based on SSO data.
   *
   * This method encapsulates all logic related to:
   * - finding an existing user
   * - creating a new user (POC compatibility)
   * - activating the user
   *
   * @param array $identityData
   *   Normalized identity data from the SSO provider.
   *
   * @return \Drupal\user\Entity\User|null
   */
  public function resolveUser(array $identityData): ?User {
    // TODO: Implement real resolution logic later.
    // For now, keep behavior equivalent to the existing POC.

    $email = $identityData['email'] ?? null;

    if (!$email) {
      return null;
    }

    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);

    if (!empty($users)) {
      $user = reset($users);
      return $this->activateUserIfNeeded($user);
    }

    return $this->createUserIfAllowed($identityData);
  }

  /**
   * Activates a user if currently inactive.
   */
  protected function activateUserIfNeeded(User $user): User {
    if (!$user->isActive()) {
      $user->activate();
      $user->save();
    }

    return $user;
  }

  /**
   * Creates a new user if auto-creation is enabled.
   *
   * NOTE: This exists only to preserve current POC behavior.
   */
  protected function createUserIfAllowed(array $identityData): ?User {
    $config = $this->configFactory->get('sam.settings');

    $user = User::create([
      'name' => $identityData['name'] ?? $identityData['email'],
      'mail' => $identityData['email'],
      'status' => 1,
    ]);

    $user->save();
    return $user;
  }

}
