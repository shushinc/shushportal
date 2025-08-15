<?php

namespace Drupal\zcs_aws\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;

/**
 * Implements an Aws App form.
 */
class CreateAwsAppForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'create_aws_app';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (\Drupal::currentUser()->hasRole('carrier_admin') || \Drupal::currentUser()->hasRole('administrator')) {
      $group_type = 'partner';
      $group_storage = \Drupal::entityTypeManager()->getStorage('group');
      $query = $group_storage->getQuery()->condition('type', $group_type);
      $group_ids = $query->accessCheck()->execute();
      $clients = $group_storage->loadMultiple($group_ids);
      $client_groups = [];
      foreach($clients as $group) {
        $client_groups[$group->id()] = $group->get('label')->value;
      }
      // Show only for carrier admin
      $form['group_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Client'),
        '#options' => $client_groups,
        '#required' => TRUE,
      ];
    }
    $form['aws_app_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App Name'),
      '#required' => TRUE,
      '#description' => $this->t('Enter Aws App Name.'),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Create Client Credentials'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $app_name = $form_state->getValue('aws_app_name');
    if (\Drupal::currentUser()->hasRole('carrier_admin') || \Drupal::currentUser()->hasRole('administrator')) {
      $group_id = $form_state->getValue('group_id');
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
          $group_id = $memberships[0]->getGroup()->id();
        }
      }
    }

    $response = \Drupal::service('zcs_aws.aws_gateway')->createAwsAppClient($app_name);
    if ($response != "error") {
      $response_key_details = \Drupal::service('zcs_aws.aws_gateway')->saveApp($group_id, $response);
      $form_state->setRedirectUrl(Url::fromRoute('zcs_aws.app_list'));
      \Drupal::messenger()->addMessage($this->t('App created successfully in AWS Gateway'), 'status', TRUE);;
    }
    else {
      \Drupal::messenger()->addError($this->t('Gateway connection failure to create App.Please contact the administrator for further assistance.'));
      $form_state->setRedirectUrl(Url::fromRoute('zcs_aws.app_list'));
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
