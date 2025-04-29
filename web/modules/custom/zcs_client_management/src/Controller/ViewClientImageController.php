<?php

namespace Drupal\zcs_client_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;


/**
 * Class ViewClientImageController.
 */
class ViewClientImageController extends ControllerBase {

  /**
   * Handleautocomplete.
   *
   * @return string
   *   Return users
   */
  public function viewImage() {
    $module_path = \Drupal::service('extension.list.module')->getPath('zcs_client_management');
    $image_url = '/' . $module_path . '/images/client_view.png';
    return [
      '#theme' => 'client_image',
      '#image_url' => $image_url,
    ];


  }

}
