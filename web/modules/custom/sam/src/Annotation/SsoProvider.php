<?php

namespace Drupal\sam\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an SSO provider annotation object.
 *
 * @see \Drupal\sam\SsoProviderManager
 * @see plugin_api
 *
 * @Annotation
 */
class SsoProvider extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the SSO provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

  /**
   * The description of the SSO provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The weight of the provider (for ordering).
   *
   * @var int
   */
  public $weight = 0;

}
