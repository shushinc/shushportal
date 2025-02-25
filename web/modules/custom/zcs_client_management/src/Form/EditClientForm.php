<?php

declare(strict_types=1);

namespace Drupal\zcs_client_management\Form;

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
use Drupal\group\Entity\Group;



/**
 * Provides a zcs_client_management edit form.
 */
final class EditClientForm extends FormBase {



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
    return 'zcs_client_management_user_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $gid = $this->request->get('id');
    $group = Group::load($gid);
    
    $form['partner_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Name'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],
      '#maxlength' => 20,      
      '#default_value' =>  $group->get('label')->value ?? '',     
    ];

    $form['contact_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Name'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],  
      '#default_value' =>  $group->get('field_contact_name')->value ?? '',      
    ];

    $form['contact_email'] = [
      '#type' => 'email',
      '#title' => 'Contact Email',
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],
      '#default_value' =>  $group->get('field_contact_email')->value ?? '',
    ];
    $form['partner_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#required' => TRUE,
      '#default_value' =>  strip_tags($group->get('field_description')->value) ?? '',
    ];
    $form['partner_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
         'demandpartner' => 'Demand Partner',
         'enterprise' => 'Enterprise',
        ],
      '#required' => TRUE,
      '#default_value' => $group->get('field_partner_type')->value ?? '',
    ];

    $form['partner_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
         'active' => 'Active',
         'in_active' => 'Inactive',
        ],
      '#default_value' => 'active',  
      '#required' => TRUE,
      '#default_value' => $group->get('field_partner_status')->value ?? '',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Update Client'),
      ],
    ];
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
    $partner_name = $form_state->getValue('partner_name'); 
    $contact_name = $form_state->getValue('contact_name'); 
    $contact_email = $form_state->getValue('contact_email'); 
    $partner_description = $form_state->getValue('partner_description'); 
    $partner_status = $form_state->getValue('partner_status'); 
    $partner_type = $form_state->getValue('partner_type'); 

    $gid = $this->request->get('id');
    $group = Group::load($gid);

    $group->set('label', $partner_name);
    $group->set('field_contact_name', $contact_name);
    $group->set('field_contact_email', $contact_email);
    $group->set('field_description', $partner_description);
    $group->set('field_partner_status', $partner_status);
    $group->set('field_partner_type', $partner_type);
    
    $group->save();    
    $this->messenger()->addMessage($this->t('Client is updated successfully.'));
    $form_state->setRedirectUrl(Url::fromRoute('view.client_details.page_1'));
  }
}
