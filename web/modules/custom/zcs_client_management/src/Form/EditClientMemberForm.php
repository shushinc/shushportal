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
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\group\Entity\GroupMembership;

/**
 * Class Update Client Member.
 */
class EditClientMemberForm extends FormBase {

  protected $entityTypeManager;



  protected $request;


  /**
   *
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_client_member_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $gid = $this->request->get('id');
    $cid = $this->request->get('cid');
    $user = User::load($cid);  
    $group_type = 'partner';


    $group_object = Group::load($gid);
    $group_storage = \Drupal::entityTypeManager()->getStorage('group');
    $query = $group_storage->getQuery()->condition('type', $group_type); 
    $group_ids = $query->accessCheck()->execute();

    $membership = GroupMembership::load($cid);
    if ($membership && $membership->get('plugin_id')->value == 'group_membership') {
      $user_id = $membership->get('entity_id')->target_id;
      $user = User::load($user_id);
      $email = $user->getEmail();
      $username = $user->getAccountName();
      $client_role = $membership->get('group_roles')->target_id;
    }

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
    // $form['client'] = [
    //   '#type' => 'select',
    //   '#title' => $this->t('Select Client'),
    //   '#options' => $client_groups,
    //   '#required' => TRUE,
    //   '#default_value' => $gid,
    //   '#attributes' => ['disabled' => 'disabled'],
    // ];
    $form['client'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client'),
      '#default_value' => $group_object->get('label')->value ?? '',
      '#attributes' => [
        'readonly' => 'readonly',
      ],
    ];

    $form['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Full Name'),
      '#required' => TRUE,
      '#attributes' => [
        'readonly' => 'readonly',
      ], 
      '#default_value' => $username,
    ];
 
    // To to validation fetch only the user who is not admin
    $form['client_email'] = [
      '#type' => 'email',
      '#title' => $this->t('User Email'),
      '#required' => TRUE,
      '#placeholder' => 'Enter the client Email',
      '#default_value' => $email,
      '#attributes' => [
        'readonly' => 'readonly',
      ], 
    ];
    $form['partner_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $client_roles,
      '#required' => TRUE,
      '#default_value' => $client_role,
    ];

  // Define the status options.
      $status_options = [
      1 => $this->t('Active'),
      0 => $this->t('Inactive'),
    ];

    // Define the form fields.
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => $status_options,
      '#default_value' => $user->isActive() ? 1 : 0, // Set default value based on current user status.
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Client User'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // To do validation.

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $partner_role = $form_state->getValue('partner_role'); 
    $user_email = $form_state->getValue('client_email'); 
    $user_status = $form_state->getValue('status');
    $cid = $this->request->get('cid');
 
    $membership = GroupMembership::load($cid);
    if ($membership) {
      $membership->set('group_roles', $partner_role);
      // Save the updated membership entity.
      $membership->save();
    }
    $query = \Drupal::entityQuery('user')->condition('mail', $user_email);
    $uids = $query->accessCheck()->execute();
    if (!empty($uids)) {
      $uid = reset($uids);
      $user = User::load($uid);
      $user->set('status', $user_status);
      if ($partner_role == 'partner-admin') {
        $user->addRole('client_admin');
      }
      else {
        $user->removeRole('client_admin');
      }
      $user->save();
    }
    $this->messenger()->addMessage($this->t('Client member updated successfully.'));
    $form_state->setRedirectUrl(Url::fromRoute('view.client_memberships.page_1'));  
  }
}
