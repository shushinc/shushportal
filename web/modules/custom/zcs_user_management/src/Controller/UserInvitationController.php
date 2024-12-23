<?php

namespace Drupal\zcs_user_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;

/**
 * Class UserInvitationController.
 */
class UserInvitationController extends ControllerBase {


  /**
   *
   */
  public function verifyInvitation(string $token) {
      
    $email = $this->getEmailFromToken($token);
    $query = \Drupal::entityQuery('user')->condition('mail', $email);
    $uids = $query->accessCheck()->execute();

    if (!empty($uids)) {
      // Load the first user entity that matches the email.
      $uid = reset($uids);
      $user = User::load($uid);
      $user->set('status', '1');
      $user->save();
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
    return new RedirectResponse($url);
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
  public function getFirstNameFromToken(string $token) {
    $query = \Drupal::database()->select('zcs_user_invitations', 'ui');
    $query->addField('ui', 'first_name');
    $query = $query->condition('ui.token', $token);
    $first_name = $query->execute()->fetchField();
    return $first_name;
  }


     /**
   *
   */
  public function getLastNameFromToken(string $token) {
    $query = \Drupal::database()->select('zcs_user_invitations', 'ui');
    $query->addField('ui', 'last_name');
    $query = $query->condition('ui.token', $token);
    $last_name = $query->execute()->fetchField();
    return $last_name;
  }


    /**
   *
   */
  public function getEmailFromToken(string $token) {
    $query = \Drupal::database()->select('zcs_user_invitations', 'ui');
    $query->addField('ui', 'email');
    $query = $query->condition('ui.token', $token);
    $email = $query->execute()->fetchField();
    $email = strtolower($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) == FALSE) {
      return FALSE;
    }
    return $email;
  }

    /**
   *
   */
  public function getUserNameFromToken(string $token) {
    $query = \Drupal::database()->select('zcs_user_invitations', 'ui');
    $query->addField('ui', 'user_name');
    $query = $query->condition('ui.token', $token);
    $user_name = $query->execute()->fetchField();
    return $user_name;
  }

  /**
   *
   */
  public function passkey(string $token) {
    $query = \Drupal::database()->select('zcs_user_invitations', 'ui');
    $query->addField('ui', 'passkey');
    $query = $query->condition('ui.token', $token);
    $passkey = $query->execute()->fetchField();
    return $passkey;
  }
  /**
   *
   */
  public function getUserRoleFromToken(string $token) {
    $query = \Drupal::database()->select('zcs_user_invitations', 'ui');
    $query->addField('ui', 'role');
    $query = $query->condition('ui.token', $token);
    $role = $query->execute()->fetchField();
    return $role;
  }

}
