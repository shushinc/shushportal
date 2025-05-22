<?php

namespace Drupal\zcs_client_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\user\Entity\User;
use Drupal\group\Entity\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;

/**
 * Class Create Client Member.
 */
class AddClientMemberForm extends FormBase {

  protected $entityTypeManager;

  /**
   *
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'create_client_member_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $group_type = 'partner';
    $group_storage = \Drupal::entityTypeManager()->getStorage('group');
    $query = $group_storage->getQuery()->condition('type', $group_type); 
    $group_ids = $query->accessCheck()->execute();
    $clients = $group_storage->loadMultiple($group_ids);
    $group_role_storage = \Drupal::entityTypeManager()->getStorage('group_role');
    $group_roles =  $group_role_storage->loadMultiple();

    $client_roles = [];
    foreach ($group_roles as $group_role) {
      if ($group_role->getScope() == 'individual') {
        $client_roles[$group_role->id()] = $group_role->label();
      }
    }
    $client_groups = [];
    foreach($clients as $group) {
      $client_groups[$group->get('id')->value] = $group->get('label')->value;
    }
    // Show only for carrier admin
    $form['client'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Client'),
      '#options' => $client_groups,
      '#required' => TRUE,
    ];
   
    $form['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Full Name'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],  
    ];
    $form['user_mail'] = [
      '#type' => 'email',
      '#title' => t('User Email'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],  
    ];
    $form['partner_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $client_roles,
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Invite Client User'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $user_email = $form_state->getValue('user_name'); 
    $user_name = $form_state->getValue('user_mail'); 
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $user_email]);
    if ($users) {
      $form_state->setError($form['user_name'], $this->t('This user is already registered or has an active invitation. Please verify their details and try again.'));
    }
    $username = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => $user_name]);
    if ($username) {
      $form_state->setError($form['user_name'], $this->t('This username is already registered or has an active invitation. Please verify their details and try again.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $client_id = $form_state->getValue('client'); 
    $partner_role = $form_state->getValue('partner_role'); 
    $user_type  = $form_state->getValue('user_type');

    $token = $this->generateToken();
    $user_name  = $form_state->getValue('user_name');
    $user_email = $form_state->getValue('user_mail'); 

    $user = User::create([
      'name' => $user_name,
      'mail' => $user_email,
      'status' => 0, // 
      'roles' => 'authenticated', 
    ]);  
    $user->save();

    if ($client_id) {
      $group = Group::load($client_id);
      $group->addMember($user, ['group_roles' => [$partner_role]]);
      $group->save();
    }

    $save_invitation = $this->saveInvitation($client_id, $user_name, $user_email, $partner_role, $token);
    $send_email = $this->sendInvitationMail($client_id, $user_name, $user_email, $partner_role, $token);
    $this->messenger()->addMessage($this->t('Invite is sent successfully.'));
  
    $form_state->setRedirectUrl(Url::fromRoute('view.client_memberships.page_1'));   
  }

  /**
   *
   */
  public function saveInvitation($client_id, $user_name, $user_email, $partner_role, $token) {
    // make the timer configurable
     $expiration_timestamp = time() + '86400';
     $query = \Drupal::database()->insert('zcs_client_member_invitations');
     $query->fields([
       'partner_id',
       'user_name',
       'email',
       'role',
       'token',
       'status',
       'created_time',
       'expire_time',
     ]);
     $query->values([
       $client_id,
       $user_name,
       $user_email,
       $partner_role,
       $token,
       'pending',
       time(),
       $expiration_timestamp,
     ]);
     return (bool) $query->execute();
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
  public function sendInvitationMail($client_id, $user_name, $user_email, $partner_role, $token) {
    $invitation_url = Url::fromRoute('zcs_client_management.verify_invitation', [
      'token' => $token,
    ], ['absolute' => TRUE]);
    $invitation_link = Link::fromTextAndUrl(t('here'), $invitation_url)->toString();

    $email_body = 'Click below link to activate your account and you have been added to the partner.<br>After activation you will be receiving further emails for onboarding process.';
    $email_body .= '<br>link:' .  $invitation_link;


    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'zcs_client_management';
    $key = 'client_member_invite';
    $to = $user_email;
    $params['subject'] = 'Partner Onboarding - Account Activation';
    $params['message'] = $email_body;
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;
    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    if ($result['result'] !== TRUE) {
      \Drupal::messenger()->addError(t('There was a problem sending your message and it was not sent to %email.', [
        '%email' => $user_email,
      ]), 'error');
    }
    else {
      \Drupal::messenger()->addMessage(t('An invitation mail has been sent to %email for %role  role', [
        '%email' => $user_email,
        '%role ' => $partner_role,
      ]));
    }
  }

}
