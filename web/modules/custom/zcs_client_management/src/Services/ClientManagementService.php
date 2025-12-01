<?php

namespace Drupal\zcs_client_management\Services;

use Drupal\group\Entity\GroupInterface;

/**
 * Service to consolidate analytics data.
 */
class ClientManagementService {

  public function __construct() {

  }

  /**
   * Get the current user's groups.
   */
  public function currentUserGroups() {
    $groups = [];

    // Get the current user.
    $current_user = \Drupal::currentUser();

    // Load the user entity.
    $user = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->load($current_user->id());

    if ($user) {
      // Get the group membership service.
      $group_membership_loader = \Drupal::service('group.membership_loader');

      // Load all group memberships for the user.
      $group_memberships = $group_membership_loader->loadByUser($user);

      // Extract the groups from the memberships.
      foreach ($group_memberships as $membership) {
        $group = $membership->getGroup();
        if ($group instanceof GroupInterface) {
          $groups[$group->id()] = $group;
        }
      }
    }

    return $groups;
  }


  public function createUpdateClientBilling($group){
    $client_name = $group->get('label')->value ?? '';
    $client_type = $group->get('field_partner_type')->value ?? '';
    $client_status = $group->get('field_partner_status')->value ?? '';
    $pricing_type = $group->get('field_pricing_type')->value ?? '';

    $endpoint = \Drupal::config('zcs_custom.settings')->get('consent_endpoint') ?? '';

    $response = \Drupal::httpClient()->request('POST', $endpoint, [
      'headers' => [
        'content-type' => 'application/json',
      ],
      'verify' => FALSE,
      'json' => $consent_data,
    ]);
    if ($response->getStatusCode() == '201') {
      $add_response = json_decode($response->getBody()->getContents(), TRUE);
      $response = '<div class="consent-success">' . $add_response['msisdn'] . ' - ' . $add_response['message'] . '</div>';
    }
    
  return TRUE;
 
  }

}
