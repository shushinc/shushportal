<?php

namespace Drupal\zcs_aws\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Class AwsDeleteController.
 */
class AwsDeleteController extends ControllerBase {


  /**
   *
   */
  public function deleteKey($id) {
    $app = Node::load($id);
    $client_id = $app->get('field_client_id')->value;
    $response = \Drupal::service('zcs_aws.aws_gateway')->deleteApp($client_id);
    if ($response != 'error') {
      $app->set('field_app_status', 'deleted');
      $app->save();
      \Drupal::messenger()->addMessage('App Deleted Successfully');
      $response = new RedirectResponse(Url::fromRoute('zcs_aws.app_list')->toString());
      return $response->send();
    }
    else {
      \Drupal::messenger()->addError('An error occurred while Deletion.', 'error');
      $response = new RedirectResponse(Url::fromRoute('zcs_aws.app_list')->toString());
      return $response->send();
    }
  }

   /**
   *
   */
  public function access(AccountInterface $account) {
    if (\Drupal::currentUser()->hasRole('carrier_admin') || \Drupal::currentUser()->hasRole('administrator')) {
      return AccessResult::allowed();
    }
    else {
      $memberships = \Drupal::service('group.membership_loader')->loadByUser(\Drupal::currentUser());
      if (isset($memberships)) {
        $roles = $memberships[0]->getRoles();
        $group_roles = [];
        foreach($roles as $role) {
          $group_roles[] = $role->id();
        }
        if (in_array('partner-admin', $group_roles)) {
          return AccessResult::allowed();
        }
        else{
          return AccessResult::forbidden();
        }
      }
    }
    return AccessResult::forbidden();
  }

}
