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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a zcs_user_management edit form.
 */
final class UserEditForm extends FormBase {



  protected $request;

  public function __construct(RequestStack $request_stack) {
    $this->request = $request_stack->getCurrentRequest();
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }



  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_user_management_user_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = $this->request->get('uid');
    $user = User::load($uid);

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


    $user_roles = $user->getRoles();

    foreach ($roles as $role) {
      if (in_array($role->id(), $roles_to_keep)) {
          $role_options[$role->id()] = $role->label();
      }
    }

    $form['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User Full Name'),
      '#default_value' => $user->get('name')->value,
      '#attributes' => [
        'readonly' => 'readonly',
      ],
    ]; 
    
    $form['user_mail'] = [
      '#type' => 'email',
      '#title' => t('User Email'),
      '#default_value' => $user->getEmail(),
      '#attributes' => [
        'readonly' => 'readonly',
      ],
    ];

    $form['user_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Role'),
      '#options' => $role_options,
      '#empty_option' => $this->t('- Select a role -'),
      '#default_value' => $user_roles,
      '#multiple' => true,
      '#attributes' => [
        'class' => ['multi-select']
      ]
    ];

       // Define the status options.
       $status_options = [
        1 => $this->t('Active'),
        0 => $this->t('InActive'),
      ];
  
      // Define the form fields.
      $form['status'] = [
        '#type' => 'select',
        '#title' => $this->t('Status'),
        '#options' => $status_options,
        '#default_value' => $user->isActive() ? 1 : 0, // Set default value based on current user status.
      ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Update Carrier User'),
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $user_email = $form_state->getValue('user_mail'); 
    $roles = $form_state->getValue('user_role');
    $user_name = $form_state->getValue('user_name');
    $status = $form_state->getValue('status');
    $query = \Drupal::entityQuery('user')->condition('mail', $user_email);
    $uids = $query->accessCheck()->execute();
    if (!empty($uids)) {
      // Load the first user entity that matches the email.
      $uid = reset($uids);
      $user = User::load($uid);
      $user->set('status', $status);
      $user->set('roles', array_keys($roles)); 
      $user->save();
      $this->messenger()->addMessage($this->t('User updated successfully.'));
      $form_state->setRedirectUrl(Url::fromRoute('view.user_management.page_1'));
    }
  }
}
