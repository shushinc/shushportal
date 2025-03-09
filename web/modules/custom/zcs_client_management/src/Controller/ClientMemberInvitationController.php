<?php

namespace Drupal\zcs_client_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\group\Entity\Group;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;

/**
 * Class ClientInvitationController.
 */
class ClientMemberInvitationController extends ControllerBase {

  /**
   *
   */
  public function verifyInvitation(string $token) {
    $email = $this->getEmailFromToken($token);
    $query = \Drupal::entityQuery('user')->condition('mail', $email);
    $uids = $query->accessCheck()->execute();
    if (!empty($uids)) {
      $partner_id = $this->getPartnerFromToken($token);
      $partner_role = $this->getPartnerRoleFromToken($token);
      // Load the first user entity that matches the email.
      $uid = reset($uids);
      $user = User::load($uid);
      $user->set('status', '1');
      if ($partner_role == 'partner-admin') {
        $user->addRole('client_admin');
      }
      $user->save();
      if ($partner_id) {
        $group = Group::load($partner_id);
        $group->addMember($user, ['group_roles' => [$partner_role]]);
        $group->save();
      }
      user_login_finalize($user);

      // Let the user's password be changed without the current password check.
      $token = Crypt::randomBytesBase64(55);
      $_SESSION['pass_reset_' . $user->id()] = $token;
      // Create the URL for the redirection with dynamic parameters.
      $url = Url::fromRoute('change_pwd_page.change_password_form', [
        'user' => $user->id(),
      ], [
        'query' => ['pass-reset-token' => $token],
        'absolute' => TRUE,
      ])->toString();

      // Perform the redirect to the generated URL.
      return new RedirectResponse($url);;
    } 
    else {
      // add logger
    }
  }


    /**
   *
   */
  public function validateToken(string $token) {
    $pending_id = $this->getPendingInvitationIdsForToken($token);
    if ($pending_id == FALSE) {
      return FALSE;
    }
    $num_rows = $this->updateTokenStatus($pending_id, 'verified');
    if ($num_rows == 1) {
      return TRUE;
    }
    return FALSE;
  }


     /**
   *
   */
  public function getPartnerFromToken(string $token) {
    $query = \Drupal::database()->select('zcs_client_member_invitations', 'ci');
    $query->addField('ci', 'partner_id');
    $query = $query->condition('ci.token', $token);
    $partner_id = $query->execute()->fetchField();
    return $partner_id;
  }


  /**
   *
   */
  public function getPartnerRoleFromToken(string $token) {
    $query = \Drupal::database()->select('zcs_client_member_invitations', 'ci');
    $query->addField('ci', 'role');
    $query = $query->condition('ci.token', $token);
    $partner_role = $query->execute()->fetchField();
    return $partner_role;
  }

  /**
   *
   */
  public function getEmailFromToken(string $token) {
    $query = \Drupal::database()->select('zcs_client_member_invitations', 'ui');
    $query->addField('ui', 'email');
    $query = $query->condition('ui.token', $token);
    $email = $query->execute()->fetchField();
    $email = strtolower($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) == FALSE) {
      return FALSE;
    }
    return $email;
  }

}
