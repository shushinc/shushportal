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

    $form['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          .toggle-checkbox {
            position: relative;
            width: 75px !important;
            height: 30px;
            -webkit-appearance: none;
            background: #c6c6c6;
            outline: none;
            border-radius: 30px;
            transition: 0.4s;
            cursor: pointer;
          }
          .toggle-checkbox:checked {
            background: #4cd964;
          }
          .toggle-checkbox:before {
            content: "";
            position: absolute;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            background: white;
            transition: 0.4s;
          }
          .toggle-checkbox:checked:before {
            transform: translateX(30px);
          }
          fieldset.custom-fieldset {
            border: 2px solid #0074D9;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
          }
          fieldset.custom-fieldset {
            border: 2px solid #ddd;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
          }
        ',
      ],
      'custom_toggle_css',
    ];



    $lists = require __DIR__ . '/../../resources/currencies.php';
    $defaultCurrency = 'en_US';
    if (!empty($this->getRequest()->get('cur'))) {
      $defaultCurrency = $this->getRequest()->get('cur');
    }

    // to fetch currencies.
    $currencies = [];
    foreach ($lists as $list) {
      if (!empty($list['locale'])) {
        $currencies[$list['locale']] = $list['currency'] .' ('. $list['alphabeticCode'] .')';
      }
    }

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
        'autocomplete' => 'off',
        'readonly' => 'readonly',
      ],
      '#default_value' =>  $group->get('field_contact_email')->value ?? '',
    ];
    $form['partner_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#required' => TRUE,
      '#default_value' =>  strip_tags($group->get('field_description')->value) ?? '',
    ];

    $form['client_legal_contact'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Legal Contact'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],        
    ];

    $form['client_point_of_contact'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Point of Contact'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],        
    ];

    $form['agreement_effective_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Agreement Effective Date'),
      '#default_value' => date('Y-m-d'),
    ];
    $form['industry'] = [
      '#type' => 'select',
      '#title' => $this->t('Industry'),
      '#options' => [
         'aggregator' => 'Aggregator',
         'fintech' => 'Fintech',
         'bank' => 'Bank',
         'social_media' => 'Social Media',
         'crypto' => 'Crypto',
         'ride_share' => 'Ride Share',
         'other_app' => 'Other App',
        ],
      '#required' => TRUE,
    ];

    $form['agreement_effective_date'] = [
      '#title' => $this->t('Agreement Effective Date'),
      '#type' => 'date',
      '#default_value' => '',
      '#disabled' => TRUE,
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



    $form['prepayment_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Prepayment Information'),
      '#attributes' => [
        'class' => ['custom-fieldset'],
      ],
    ];

    $form['prepayment_info']['currencies'] = [
      '#type' => 'select',
      '#options' => $currencies,
      '#default_value' => $defaultCurrency
    ];

    $form['prepayment_info']['prepayment_amount'] = [
      '#type' => 'number',
      '#title' => 'Prepayment Amount',
      '#min' => 0,
      '#default_value' => 0.00,
      '#step' => 0.001,
    ];

    $form['prepayment_info']['prepayment_balance_left'] = [
      '#type' => 'number',
      '#title' => 'Prepayment Balance left',
      '#min' => 0,
      '#default_value' => 0.00,
      '#step' => 0.001,
    ];


    $form['address_info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Address'),
      '#attributes' => [
        'class' => ['custom-fieldset'],
      ],
    ];

  //  $address = $group->get('field_address')->getValue();
   // $address_value = $address[0];
    $form['address_info']['address'] = [
      '#type' => 'address',
      '#title' => $this->t('Address'),
      '#required' => TRUE,  
      '#default_value' => $address_value ?? '',    
    ];


   

    $form['api_agreement_covers'] = [
      '#type' => 'fieldset',
      '#title' => 'APIs Agreement Covers',
      '#attributes' => [
        'class' => ['custom-fieldset'],
      ],
    ];


    $contents =  \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        $nids[] = $content->id();
        // Checkbox to enable/select this attribute
        $form['api_agreement_covers']['attribute_' . $content->id()]= [
          '#type' => 'checkbox',
          '#title' => $content->label(),
          '#default_value' => FALSE,
          '#attributes' => [
            'class' => ['toggle-checkbox'],
          ],
        ];
      }
    }

    $form['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(",", $nids),
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
