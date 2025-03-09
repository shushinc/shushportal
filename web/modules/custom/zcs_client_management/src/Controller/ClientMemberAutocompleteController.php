<?php

namespace Drupal\zcs_client_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Component\Utility\Xss;

/**
 * Class ClientMemberAutocompleteController.
 */
class ClientMemberAutocompleteController extends ControllerBase {

  /**
   * Handleautocomplete.
   *
   * @return string
   *   Return users
   */
  public function Autocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse($results);
    }

    $query = \Drupal::entityQuery('user')->condition('status', 1); // Optional: Only fetch active users.
    $user_ids = $query->accessCheck()->execute();
    $users = $user_ids ? \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($uids) : [];
    foreach ($users as $user) {
      $results[] = [
        'value' => $user->getEmail(),
        'label' => $user->getEmail(),
      ];
    }

    return new JsonResponse($results);
  }

}
