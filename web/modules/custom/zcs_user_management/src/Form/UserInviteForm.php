<?php

declare(strict_types=1);

namespace Drupal\zcs_user_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;



/**
 * Provides a zcs_user_management form.
 */
final class UserInviteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_user_management_user_invite';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $roles = Role::loadMultiple();
    $roles_to_keep = [
       'carrier_admin',
       'finance_admin', 
       'financial_rate_sheet_approval_level_1', 
       'financial_rate_sheet_approval_level_2',
       'api_attribute_admin',
       'api_attribute_approval_level_1',
       'api_attribute_approval_level_2',
      ];
    $role_options = [];
    
    foreach ($roles as $role) {
      if (in_array($role->id(), $roles_to_keep)) {
          $role_options[$role->id()] = $role->label();
      }
    }
    $form['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Full Name'),
      '#required' => TRUE,
      '#maxlength' => 18,
      '#attributes' => [
        'autocomplete' => 'off',
        'data-1password-disable' => 'true',
      ],
    ];
    $form['user_mail'] = array(
      '#type' => 'email',
      '#title' => t('User Email'),
      '#required' => TRUE,
    );
    $form['user_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $role_options,
      '#empty_option' => $this->t('- Select a role -'),
      '#multiple' => true,
      '#attributes' => [
        'class' => ['multi-select']
      ]
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Invite Carrier User'),
      ],
    ];
    $form['#attached']['library'][] = 'zcs_user_management/bootstrap_multiselect';
    $form['#attached']['library'][] = 'zcs_user_management/bootstrap_multiselect_css';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // @endcode
    parent::validateForm($form, $form_state);
    $user_email = $form_state->getValue('user_mail'); 
    $user_name = $form_state->getValue('user_name'); 
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $user_email]);
    if ($users) {
      $form_state->setError($form['user_mail'], $this->t('This user is already registered or has an active invitation. Please verify their details and try again.'));
    }
    $username = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => $user_name]);
    if ($username) {
      $form_state->setError($form['user_name'], $this->t('This username is already registered or has an active invitation. Please verify their details and try again.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $token = $this->generateToken();
    $passkey = $this->randomPassword();
    $user_email = $form_state->getValue('user_mail'); 
    $roles = $form_state->getValue('user_role');
    $user_name = $form_state->getValue('user_name');

    $user = User::create([
      'name' => $user_name,
      'mail' => $user_email,
      'pass' => $passkey,
      'status' => 0, // 
      'roles' => array_keys($roles)
    ]);  
    $user->save();
    $user_roles_list = $this->getUserRolelableUsingRoleId($roles);
    $save_invitation = $this->saveInvitation($user_email, $user_roles_list, $token, $passkey, $user_name);
    $send_emai = $this->sendInvitationMail($user_email,  $user_roles_list, $token, $passkey, $user_name);
    $form_state->setRedirectUrl(Url::fromRoute('view.user_management.page_1'));
  }


   /**
   *
   */
  public function generateToken() {
    $token_length = 12;
    $min_exponent = $token_length - 1;
    $min = pow(10, $min_exponent);
    $max = pow(10, $token_length) - 1;
    $token = mt_rand($min, $max);
    return Crypt::hmacBase64($token, Settings::getHashSalt());
  }

   /**
   *
   */
  public function sendInvitationMail(string $email, $roles, $token, $passkey, $user_name) {

    $pass = $this->randomPassword();
    $invitation_url = Url::fromRoute('zcs_user_management.verify_invitation', [
      'token' => $token,
    ], ['absolute' => TRUE]);
    $attributes = [
      'target' => '_blank',
      'style' => 'display: inline-block; padding: 12px 24px; font-size: 16px; color: #ffffff; text-decoration: none; font-weight: bold; background:#007bff; border-radius: 5px;',
    ];
    
    $invitation_link = Link::fromTextAndUrl(t('Activate'), $invitation_url)
      ->toRenderable();
    $invitation_link['#attributes'] = $attributes;

    $rendered_link = \Drupal::service('renderer')->render($invitation_link);
   
    $site_name = \Drupal::config('system.site')->get('name');
  
    $email_subject = \Drupal::config('zcs_custom.portal_email_settings')->get('email_subject');
    $email_body = \Drupal::config('zcs_custom.portal_email_settings')->get('email_body');

 

    $user_name = $this->getUserNameUsingEmail($email);
    $email_body = \Drupal::token()->replace($email_body, ['user_name' => $user_name, 'user_invite_activation_url' => $rendered_link, 'site_name' => $site_name, 'roles' => $roles]); 
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'zcs_user_management';
    $key = 'user_invite';
    $to = $email;
    $params['subject'] = $email_subject;
    $params['message'] = $email_body;
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;
    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    if ($result['result'] !== TRUE) {
      \Drupal::messenger()->addError(t('There was a problem sending your message and it was not sent to %email.', [
        '%email' => $email,
      ]), 'error');
    }
    else {
      \Drupal::messenger()->addMessage(t('An initation mail has been sent to %email for %role  role', [
        '%email' => $email,
        '%role ' => $roles,
      ]));
    }
  }

  public function getUserNameUsingEmail($email) {
    $username = '';
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    if (!empty($users)) {
      $user = reset($users);
      $username = $user->getAccountName();
    }
    return $username;
  }


  public function getUserRolelableUsingRoleId($roles) {
    $role_storage = \Drupal::entityTypeManager()->getStorage('user_role');
    $role_entities = $role_storage->loadMultiple(array_keys($roles));
    
    $role_labels = [];
    foreach ($role_entities as $id => $role) {
      $role_labels[] = $role->label();
    }
    
    $role_labels_list = implode(', ', $role_labels);
    return $role_labels_list;
  }




   /**
   *
   */
  public function saveInvitation(string $email, $role, $token, $passkey, $user_name) {
   // make the timer configurable
    $expiration_timestamp = time() + '86400';
    $query = \Drupal::database()->insert('zcs_user_invitations');
    $query->fields([
      'user_name',
      'email',
      'role',
      'token',
      'passkey',
      'status',
      'created_time',
      'expire_time',
    ]);
    $query->values([
      $user_name,
      $email,
      $role,
      $token,
      $passkey,
      'pending',
      time(),
      $expiration_timestamp,
    ]);
    return (bool) $query->execute();
  }




  function randomPassword() {
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $pass = array(); //remember to declare $pass as an array
    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
    for ($i = 0; $i < 8; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass); //turn the array into a string 
  }



}
