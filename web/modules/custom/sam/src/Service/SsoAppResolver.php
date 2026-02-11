<?php

namespace Drupal\sam\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sam\SsoAppInterface;

class SsoAppResolver {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Resolves an SSO App by email address.
   */
  public function resolveByEmail(string $email): SsoAppInterface|NULL {
    if (!str_contains($email, '@')) {
      return NULL;
    }

    [, $domain] = explode('@', strtolower(trim($email)), 2);

    return $this->resolveByDomain($domain);
  }

  /**
   * Resolves an SSO App by domain.
   */
  public function resolveByDomain(string $domain): SsoAppInterface|NULL|bool {
    $storage = $this->entityTypeManager->getStorage('sam_sso_app');

    $apps = $storage->loadByProperties([
      'domain' => $domain,
      'status' => TRUE,
    ]);

    if (empty($apps)) {
      return NULL;
    }

    // Domain is unique, so we expect exactly one result.
    return reset($apps);
  }

}
