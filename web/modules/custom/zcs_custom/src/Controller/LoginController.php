<?php

declare(strict_types=1);

namespace Drupal\zcs_custom\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for zcs_custom routes.
 */
final class LoginController extends ControllerBase {

  /**
   * Builds the response.
   */

  public function login(): array {
    $module_path = \Drupal::service('module_handler')->getModule('zcs_custom')->getPath();

    return [
      '#theme' => 'portal_login', // This matches the name of the template file without the ".html.twig" extension
      '#title' => '',
      '#description' => $this->t('Welcome to Moriarty'),
      '#module_path' => $module_path,
    ];
  }

}
