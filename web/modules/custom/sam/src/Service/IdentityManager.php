<?php

namespace Drupal\sam\Service;

use Drupal\user\Entity\User;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;

/**
 * Handles identity resolution and user lifecycle for SSO authentication.
 */
class IdentityManager {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected ConfigFactoryInterface $configFactory;
  protected Connection $connection;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $configFactory,
    Connection $database
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->connection = $database;
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

  /**
   * Resolve user context by email.
   *
   * @param string $email
   *   User email.
   *
   * @return array
   *   [
   *     exists => bool,
   *     uid => int|null,
   *     type => carrier|client|unknown,
   *     can_use_sso => bool,
   *   ]
   */
  public function resolveUserContextByEmail(string $email): array {

    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties([
        'mail' => strtolower(trim($email)),
      ]);
    
    if (empty($users)) {
      return [
        'exists' => FALSE,
        'uid' => NULL,
        'type' => 'unknown',
        'can_use_sso' => FALSE,
      ];
    }

    /** @var \Drupal\user\UserInterface $user */
    $user = reset($users);
    $uid = (int) $user->id();

    // Check group membership (client users).
    $query = $this->connection->select('group_relationship_field_data', 'gr');
    $query->addField('gr', 'id');
    $query->condition('gr.entity_id', $uid);
    $query->range(0, 1);

    $is_client = (bool) $query->execute()->fetchField();
    
    if ($is_client) {
      return [
        'exists' => TRUE,
        'uid' => $uid,
        'type' => 'client',
        'can_use_sso' => FALSE,
      ];
    }

    return [
      'exists' => TRUE,
      'uid' => $uid,
      'type' => 'carrier',
      'can_use_sso' => TRUE,
    ];
  }


  public function getUserFromToken(string $token): bool|EntityInterface {
    $query = $this->connection->select('zcs_user_invitations', 'ui');
    $query->addField('ui', 'email');
    $query = $query->condition('ui.token', $token);
    $email = $query->execute()->fetchField();
    $email = strtolower($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) == FALSE) {
      return FALSE;
    }

    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties([
        'mail' => strtolower(trim($email)),
      ]);
    
    if (!empty($users)) {
      $user = reset($users);
      return $user;
    }

    return FALSE;
  }

}
