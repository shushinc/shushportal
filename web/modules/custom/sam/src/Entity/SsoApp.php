<?php

namespace Drupal\sam\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\sam\SsoAppInterface;

/**
 * Defines the SSO App configuration entity.
 *
 * @ConfigEntityType(
 *   id = "sam_sso_app",
 *   label = @Translation("SSO App"),
 *   label_collection = @Translation("SSO Apps"),
 *   label_singular = @Translation("sso app type"),
 *   label_plural = @Translation("sso app types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count sso app",
 *     plural = "@count sso apps",
 *   ),   
 *   handlers = {
 *     "list_builder" = "Drupal\sam\SsoAppListBuilder",
 *     "form" = {
 *       "add" = "Drupal\sam\Form\SsoAppForm",
 *       "edit" = "Drupal\sam\Form\SsoAppForm",
 *       "delete" = "Drupal\sam\Form\SsoAppDeleteForm"
 *     }
 *   },
 *   admin_permission = "administer sam sso",
 *   config_prefix = "sso_app",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "is_enabled"
 *   },
 *   links = {
 *     "collection" = "/admin/config/people/multi-sso/apps",
 *     "add-form" = "/admin/config/people/multi-sso/apps/add",
 *     "edit-form" = "/admin/config/people/multi-sso/apps/{sam_sso_app}",
 *     "delete-form" = "/admin/config/people/multi-sso/apps/{sam_sso_app}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "domain",
 *     "provider",
 *     "settings",
 *   }
 * )
 */
class SsoApp extends ConfigEntityBase implements SsoAppInterface {

  protected string $id;
  protected string $label;
  

  public function id(): string {
    return $this->id ?? '';
  }

  public function label(): string {
    return $this->label ?? '';
  }

  public function getDomain(): string {
    return $this->get('domain') ?? '';
  }

  public function getProvider(): string {
    return $this->get('provider') ?? '';
  }

  public function getSettings(): array {
    return $this->get('settings') ?? [];
  }

  public function getSetting(string $key, $default = NULL) {
    $settings = $this->getSettings();
    return $settings[$key] ?? $default;
  }

  public function isEnabled(): bool {
    return (bool) $this->status();
  }

}
