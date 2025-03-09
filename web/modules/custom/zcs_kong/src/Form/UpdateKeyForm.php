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
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;



/**
 * Provides a zcs_kong api key form.
 */
final class UpdateKeyForm extends FormBase {



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
    return 'zcs_kong_key_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
   
    $app_id = $this->request->get('id');
   // $consumer_id = $this->request->get('id');

    if (\Drupal::currentUser()->hasRole('carrier_admin') || \Drupal::currentUser()->hasRole('administrator')) {
      $group_type = 'partner';
      $group_storage = \Drupal::entityTypeManager()->getStorage('group');
      $query = $group_storage->getQuery()->condition('type', $group_type); 
      $group_ids = $query->accessCheck()->execute();
      $clients = $group_storage->loadMultiple($group_ids);
      $client_groups = [];
      foreach($clients as $group) {
        if (isset($group->get('field_consumer_id')->getValue()[0])) {
          $consumer_ids = $group->get('field_consumer_id')->getValue()[0]['value']; 
          $client_groups[$consumer_ids] = $group->get('label')->value;
        }
      }
      // Show only for carrier admin
      $form['client'] = [
        '#type' => 'select',
        '#title' => $this->t('Select Client'),
        '#options' => $client_groups,
        '#required' => TRUE,
        '#default_value' => $consumer_ids,
        '#attributes' => ['disabled' => 'disabled'],
      ]; 
    }

     $app = Node::load($app_id);
     $ttl = [
      "7776000" => '90 Days',
      "15552000" => '180 Days',
      "14688000" => '170 Days',
      "31536000" => '365 Days',
      "never_expires" => 'Never Expires',
    ];

    $form['app_key_id'] = [
      '#type' => 'hidden',
      '#value' => $app->get('field_app_id')->value,
    ];
    $form['consumer_id'] = [
      '#type' => 'hidden',
      '#value' => $app->get('field_consumer_id')->value,
    ];
    $form['consumer_app_key'] = [
      '#type' => 'hidden',
      '#value' => $app->get('field_app_key')->value,
    ];

    $form['kong_key_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#description' => $this->t('Limit upto 15 characters.'),
      '#default_value' => $app->get('field_tag')->value ?? '',
      // '#attributes' => ['readonly' => 'readonly'],
    ];
    $form['kong_key_ttl'] = [
      '#type' => 'select',
      '#title' => $this->t('TTL'),
      '#options' => $ttl,
      '#required' => TRUE,
      '#default_value' => $app->get('field_ttl')->value,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Regenerate Key'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // parent::validateForm($form, $form_state);
    $tags = $form_state->getValue('kong_key_tags');
    if (!preg_match('/^[a-zA-Z0-9\-]{1,15}$/', $tags)) {
      // Set an error on the form if validation fails.
      $form_state->setErrorByName('kong_key_tags', $this->t('The Tags field can contain numbers, hypens and alphabets upto 15 characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $app_node_id = $this->request->get('id');
    $app_key_id = $form_state->getValue('app_key_id');
    $consumer_id = $form_state->getValue('consumer_id');
    $ttl = $form_state->getValue('kong_key_ttl'); 
    $tag = $form_state->getValue('kong_key_tags'); 
    $app_key = $form_state->getValue('consumer_app_key'); 
    $response_key_details = \Drupal::service('zcs_kong.kong_gateway')->updateApp($consumer_id, $app_key_id, $ttl, $tag, $app_key);
    if(!empty($response_key_details)) {
      $status_code = $response_key_details->getStatusCode();
      if ($status_code == '200') {
        $response_key_details = \Drupal::service('zcs_kong.kong_gateway')->updateAppNode($app_node_id, $ttl ,$response_key_details->getBody()->getContents());
        $this->messenger()->addMessage('App updated Successfully');  
        $form_state->setRedirectUrl(Url::fromRoute('zcs_kong.app_list'));   
      }else {
        $this->messenger()->addError('Unable to update App. Please try again later or check Adminstrator');
      }
    }
    $form_state->setRedirectUrl(Url::fromRoute('zcs_kong.app_list'));
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
