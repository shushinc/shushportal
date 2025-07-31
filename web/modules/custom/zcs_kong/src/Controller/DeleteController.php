<?php

namespace Drupal\zcs_kong\Controller;

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
class DeleteController extends ControllerBase {


  /**
   *
   */
  public function deleteKey($id) {
    $app = Node::load($id);

    $consumer_id = $app->get('field_consumer_id')->getValue()[0]['value'];
    $app_key_id = $app->get('field_app_id')->getValue()[0]['value'];


    $delete_response = \Drupal::service('zcs_kong.kong_gateway')->deleteApp($consumer_id, $app_key_id);
    if (!empty($delete_response)) {
      $status_code = $delete_response->getStatusCode();
      if ($status_code == '204') {
        $app->set('field_app_status', 'deleted');
        $app->set('field_expiry_date', time());
        $app->save();
        \Drupal::messenger()->addMessage('App Deleted Successfully');
      }
    }
    else {
      \Drupal::messenger()->addError('Gateway connection failure to delete App.Please contact the administrator for further assistance.');
    }
    $response = new RedirectResponse(Url::fromRoute('zcs_kong.app_list')->toString());
    return $response->send();
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
