<?php

declare(strict_types=1);

namespace Drupal\zcs_kong\Form;

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
use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a zcs_kong api key form.
 */
final class CreateKeyForm extends FormBase {



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
    return 'zcs_kong_key_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if (\Drupal::currentUser()->hasRole('carrier_admin') || \Drupal::currentUser()->hasRole('administrator')) {
      $group_type = 'partner';
      $group_storage = \Drupal::entityTypeManager()->getStorage('group');
      $query = $group_storage->getQuery()->condition('type', $group_type); 
      $group_ids = $query->accessCheck()->execute();
      $clients = $group_storage->loadMultiple($group_ids);
      $client_groups = [];
      foreach($clients as $group) {
        if (isset($group->get('field_consumer_id')->getValue()[0])) {
          $consumer_id = $group->get('field_consumer_id')->getValue()[0]['value']; 
          $client_groups[$consumer_id] = $group->get('label')->value;
        }
      } 
      // Show only for carrier admin
      $form['consumer_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Client'),
        '#options' => $client_groups,
        '#required' => TRUE,
      ]; 
    }

    $ttl = [
      "7776000" => '90 Days',
      "15552000" => '180 Days',
      "14688000" => '170 Days',
      "31536000" => '365 Days',
      "never_expires" => 'Never Expires',
    ];

    $form['kong_key_tags'] = [ 
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#required' => TRUE,
      '#description' => $this->t('Limit upto 15 characters.'),
      '#attributes' => [
        'maxlength' => 15,
      ],
    ];
    $form['kong_key_ttl'] = [
      '#type' => 'select',
      '#title' => $this->t('TTL'),
      '#options' => $ttl,
      '#required' => TRUE,
      '#default_value' => '7776000',
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Generate Key'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $tags = $form_state->getValue('kong_key_tags'); 
    $ttl = $form_state->getValue('kong_key_ttl'); 

    if (!preg_match('/^[a-zA-Z0-9\-]{1,15}$/', $tags)) {
      // Set an error on the form if validation fails.
      $form_state->setErrorByName('kong_key_tags', $this->t('The Tags field can contain numbers, hypens and alphabets upto 15 characters.'));
    }
    // // Check if the value is a numeric string and an integer between 0 and 100,000,000.
    // if (!is_numeric($ttl) || intval($ttl) != $ttl || $ttl < 0 || $ttl > 100000000) {
    //   // Set an error on the form if validation fails.
    //   $form_state->setErrorByName('kong_key_ttl', $this->t('The TTL field must be an integer between 0 and 100,000,000.'));
    // }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $tags = $form_state->getValue('kong_key_tags'); 
    $ttl = $form_state->getValue('kong_key_ttl'); 
    if (\Drupal::currentUser()->hasRole('carrier_admin') || \Drupal::currentUser()->hasRole('administrator')) {
      $client_id = $form_state->getValue('consumer_id'); 
      $response_key_details = \Drupal::service('zcs_kong.kong_gateway')->generateKey($client_id, $tags, $ttl);
      if (!empty($response_key_details)) {
        if ($response_key_details == 'error') {
          $this->messenger()->addError('Something went wrong');  
          $form_state->setRedirectUrl(Url::fromRoute('zcs_kong.app_list'));  
        }
        else {
          $client_id = $form_state->getValue('consumer_id'); 
          $status_code = $response_key_details->getStatusCode();
          if ($status_code == '201') {
            $response_key_details = \Drupal::service('zcs_kong.kong_gateway')->saveApp($client_id, $ttl, $response_key_details->getBody()->getContents());
            $this->messenger()->addMessage('App created Successfully');  
            $form_state->setRedirectUrl(Url::fromRoute('zcs_kong.app_list'));   
          }
        }  
      }
      else {
        $this->messenger()->addError('something went wrong');
        $form_state->setRedirectUrl(Url::fromRoute('zcs_kong.create_key'));
      } 
    }
    else {
      $consumer_id =  \Drupal::service('zcs_kong.kong_gateway')->checkUserAccessGeneratekey(); 
      if ($consumer_id == 'error'){
        \Drupal::messenger()->addMessage('Something went wrong....!');
      } 
      else {
        $response_key_details = \Drupal::service('zcs_kong.kong_gateway')->generateKey($consumer_id, $tags, $ttl);
        if(!empty($response_key_details)) {
          $status_code = $response_key_details->getStatusCode();
          if ($status_code == '201') {
            $response_key_details = \Drupal::service('zcs_kong.kong_gateway')->saveApp($consumer_id, $ttl, $response_key_details->getBody()->getContents());
            $this->messenger()->addMessage('App created Successfully');  
            $form_state->setRedirectUrl(Url::fromRoute('zcs_kong.app_list'));   
          }
        }
        else {
          $this->messenger()->addError('something went wrong');
          $form_state->setRedirectUrl(Url::fromRoute('zcs_kong.create_key'));
        }    
      }
    }
  }




   /**
   *
   */
  public function access(AccountInterface $account) {
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
    return AccessResult::forbidden();
  }
}
