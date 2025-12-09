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


  public function createUpdateClientBilling($group) {
    $client_name = $group->get('label')->value ?? '';
    $client_type = $group->get('field_partner_type')->value ?? '';
    $client_status = $group->get('field_partner_status')->value ?? '';
    $pricing_type = $group->get('field_pricing_type')->value ?? '';
    $pricing_label = '';
    if ($pricing_type == 'international_pricing') {
      $pricing_label = 'international';
    }
    if ($pricing_type == 'domestic_pricing'){
      $pricing_label = 'domestic';
    }
    if($client_type == 'demand_partner'){
      $client_type = 'demandpartner';
    }

    $endpoint = \Drupal::config('zcs_custom.settings')->get('client_billing_api_endpoint') ?? '';
    
    $client_billing_data = [
      'client' => $client_name,
      'client_type' => $client_type,
      'status' => $client_status,
      'pricing_type' => $pricing_label,
    ];
    $data = json_encode($client_billing_data, TRUE);
    try {
      $response = \Drupal::httpClient()->request('POST', $endpoint, [
        'headers' => [
          'content-type' => 'application/json',
        ],
        'verify' => FALSE,
        'body' => $data,
      ]);
      if ($response->getStatusCode() == '200') {
        \Drupal::logger('client_billing')->info('success billing  update for the client @client_name', [
          '@client_name' => $client_name,
        ]);
        return TRUE;
      } else {
        \Drupal::logger('client_billing')->error('failure for  billing  update for the client @client_name', [
          '@client_name' => $client_name,
        ]);
        return FALSE;
      }
 
    }
    catch (\Exception $e) {
      \Drupal::logger('client_billing')->error('failure for  billing  update for the client @client_name', [
        '@client_name' => $client_name,
      ]);
      return FALSE;
    }
  }

}
