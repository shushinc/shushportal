<?php

declare(strict_types=1);

namespace Drupal\zcs_aws\Form;

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
final class UpdateAwsAppForm extends FormBase {



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
    return 'zcs_aws_key_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $app_id = $this->request->get('id');
    $app = Node::load($app_id);
    $form['app_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App Name'),
      '#default_value' => $app->get('field_tag')->value ?? '',
    ];

    $form['app_client_id'] = [
      '#type' => 'hidden',
      '#value' => $app->get('field_client_id')->value,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Update App'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $app_node_id = $this->request->get('id');
    $app_name = $form_state->getValue('app_name');
    $client_id = $form_state->getValue('app_client_id');

    $response = \Drupal::service('zcs_aws.aws_gateway')->updateAwsAppClient($app_name, $client_id);
    if ($response != "error") {
      $aws_update_response = $response->toArray();
      $update_app_name = $aws_update_response['UserPoolClient']['ClientName'];
      // Load the node by its ID.
      $node = Node::load($app_node_id);
      if ($node) {
        $node->set('field_tag',  $update_app_name); // Example of a custom field.
        // Save the updated node.
        $node->save();
        $this->messenger()->addMessage('App updated Successfully');
        $form_state->setRedirectUrl(Url::fromRoute('zcs_aws.app_list'));
      } else {
        \Drupal::messenger()->addMessage('Node not found.', 'error');
      }
    }
    else {
      \Drupal::messenger()->addError($this->t('Gateway connection failure to Update App.Please contact the administrator for further assistance.'));
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
