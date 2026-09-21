<?php

namespace Drupal\customer_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns the plaintext hashed key for an authorized reveal request.
 */
class HashedKeyController extends ControllerBase {

  /**
   * AJAX endpoint: GET /customer/{node}/reveal-key.
   */
  public function reveal(NodeInterface $node) {
    if ($node->bundle() !== 'customer') {
      throw new NotFoundHttpException();
    }

    if (!$this->currentUser()->hasPermission('view hashed key')) {
      throw new AccessDeniedHttpException();
    }

    $value = $node->hasField('field_hashed_key') ? $node->get('field_hashed_key')->value : NULL;

    $response = new JsonResponse([
      'hashed_key' => $value,
      'customer_id' => (int) $node->id(),
    ]);
    $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
    return $response;
  }

}
