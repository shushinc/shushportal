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
use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DrupalDateTime;




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
      '#prefix' => '<div class="client-Layout-column-wrapper"><div class="tiles-wrapper client-Layout-column-first"><div class="partner-info grid-layout-column">',        
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
      '#suffix' => '</div>', 
    ];

    $form['client_legal_contact'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Legal Contact'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],
      '#default_value' =>  $group->get('field_client_legal_contact')->value ?? '',   
      '#prefix' => '<div class="partner-contact-info grid-layout-column">',     
    ];

    $form['client_point_of_contact'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Point of Contact'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],
      '#default_value' =>  $group->get('field_client_point_of_contact')->value ?? '',        
    ];

    $form['agreement_effective_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Agreement Effective Date'),
      '#default_value' => $group->get('field_agreement_effective_date')->value ?? '',
      '#date_date_format' => 'Y-m-d',
      '#disabled' => TRUE,
      '#suffix' => '</div>',
    ];
   



    $form['partner_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
         'demand_partner' => 'Demand Partner',
         'enterprise' => 'Enterprise',
        ],
      '#required' => TRUE,
      '#default_value' => $group->get('field_partner_type')->value ?? '',
      '#prefix' => '<div class="partner-details grid-layout-column">',
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
      '#default_value' =>  $group->get('field_industry')->value ?? '',
    ];

   
    $form['partner_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
         'active' => 'Active',
         'inactive' => 'Inactive',
        ],
      '#default_value' => 'active',  
      '#required' => TRUE,
      '#default_value' => $group->get('field_partner_status')->value ?? '',
      '#suffix' => '</div>',
    ];


    $form['client_Layout_column_wrapper_text'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'class' => ['client-contact-details-col-4'],
      ],
    ];

    $address = $group->get('field_address')->getValue();
    $address_value = isset($address[0]) ? $address[0]: '';
    $form['client_Layout_column_wrapper_text']['address'] = [
      '#type' => 'address',
      '#title' => $this->t('Address'),
      '#required' => TRUE,  
      '#default_value' => $address_value ?? '', 
      '#suffix' => '</div>',   
      '#after_build' => [[$this, 'customAddressAlter']],
    ];


    $form['client_Layout_column_wrapper_text']['partner_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#required' => TRUE,
      '#default_value' =>  strip_tags($group->get('field_description')->value) ?? '',
      '#prefix' => '<div class="partner-desc">',
      '#suffix' => '</div></div>',
    ];

    $form['currencies'] = [
      '#type' => 'select',
      '#options' => $currencies,
      '#default_value' => \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US',
      '#disabled' => TRUE, // disables the field
      '#prefix' => '<div class="payment-details client-Layout-column-second">',
    ];

    $form['prepayment_amount'] = [
      '#type' => 'number',
      '#title' => 'Prepayment Amount',
      '#min' => 0,
      '#default_value' =>  $group->get('field_prepayment_amount')->value ?? '',
     // '#step' => 0.001,
    ];

    $form['prepayment_balance_left'] = [
      '#type' => 'number',
      '#title' => 'Prepayment Balance left',
      '#min' => 0,
      '#default_value' =>  $group->get('field_prepayment_balance_left')->value ?? '',
    //  '#step' => 0.001,
    ];

    $form['prepayment_balance_used'] = [
      '#type' => 'number',
      '#title' => 'Prepayment Balance left',
      '#min' => 0,
      '#default_value' =>  $group->get('field_prepayment_balance_used')->value ?? '',
      //'#step' => 0.001,
      '#suffix' => '</div></div>',
    ];


   
    $form['api_agreement_covers'] = [
      '#type' => 'fieldset',
      '#title' => 'APIs Agreement Covers',
      '#attributes' => [
        'class' => ['custom-fieldset'],
      ],
      '#prefix' => '<div class="agreement-covers">',
    ];

    $api_aggreement_covers_array = [];
    if(!empty($group->get('field_apis_agreement_covers')->value)) {
      $api_aggreement_covers_array = json::decode($group->get('field_apis_agreement_covers')->value);
    }   
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'api_attributes')
      ->sort('field_attribute_weight', 'ASC')
      ->accessCheck()
      ->execute();
      $contents = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        $nids[] = $content->id();
        // Checkbox to enable/select this attribute
        $form['api_agreement_covers']['attribute_' . $content->id()]= [
          '#type' => 'checkbox',
          '#title' => $content->label(),
          '#default_value' => isset($api_aggreement_covers_array[$content->id()]) ? $api_aggreement_covers_array[$content->id()] : 0,
          '#attributes' => [
            'class' => ['toggle-checkbox'],
          ],
        ];
      }
    }

    $form['api_agreement_covers']['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(",", $nids),
      '#suffix' => '</div>',
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
    $form['#attached']['library'][] = 'zcs_client_management/client-view-page';
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
    $values = $form_state->getValues();
    $nids = explode(",", $values['nodes']);
    $json = [];
    foreach ($nids as $nid) {
      $json[$nid] = $values['attribute_' . $nid];
    }
   

    
    $encoded_data = Json::encode($json);


    $partner_name = $form_state->getValue('partner_name'); 
    $contact_name = $form_state->getValue('contact_name'); 
    $contact_email = $form_state->getValue('contact_email'); 
    $partner_description = $form_state->getValue('partner_description'); 
    $partner_status = $form_state->getValue('partner_status'); 
    $partner_type = $form_state->getValue('partner_type'); 


    
    $client_legal_contact = $form_state->getValue('client_legal_contact'); 
    $client_point_of_contact = $form_state->getValue('client_point_of_contact'); 
    $agreement_effective_date = $form_state->getValue('agreement_effective_date'); 
    $industry = $form_state->getValue('industry'); 
    $prepayment_amount = $form_state->getValue('prepayment_amount');
    $prepayment_balance_left = $form_state->getValue('prepayment_balance_left');
    $prepayment_balance_used= $form_state->getValue('prepayment_balance_used');
    $currency = $form_state->getValue('currencies');



    $gid = $this->request->get('id');
    $group = Group::load($gid);

    $group->set('label', $partner_name);
    $group->set('field_contact_name', $contact_name);
    $group->set('field_contact_email', $contact_email);
    $group->set('field_description', $partner_description);
    $group->set('field_partner_status', $partner_status);
    $group->set('field_partner_type', $partner_type);
    $group->set('field_agreement_effective_date', $agreement_effective_date);

    $group->set('field_client_legal_contact', $client_legal_contact);
    $group->set('field_client_point_of_contact', $client_point_of_contact);
    $group->set('field_prepayment_amount', $prepayment_amount);
    $group->set('field_prepayment_balance_left', $prepayment_balance_left);
    $group->set('field_prepayment_balance_used', $prepayment_balance_used);

    $group->set('field_currency', $currency);
    $group->set('field_industry', $industry);
    $group->set('field_apis_agreement_covers', $encoded_data);




       // Address details
       $country_code =  $form_state->getValue('address')['country_code'];
       $administrative_area = $form_state->getValue('address')['administrative_area'];
       $locality = $form_state->getValue('address')['locality'];
       $dependent_locality = $form_state->getValue('address')['dependent_locality'];
       $postal_code = $form_state->getValue('address')['postal_code'];
       $sorting_code = $form_state->getValue('address')['sorting_code'];
       $address_line1 = $form_state->getValue('address')['address_line1'];
       $address_line2 = $form_state->getValue('address')['address_line2'];
       $address_line3 = $form_state->getValue('address')['address_line3'];
       $organization = $form_state->getValue('address')['organization'];
       $given_name = $form_state->getValue('address')['given_name'];
       $additional_name = $form_state->getValue('address')['additional_name'];
       $family_name =  $form_state->getValue('address')['family_name'];

       $group->set('field_address', [
        "langcode" => null,
        "country_code" => $country_code ?? '',
        "administrative_area" => $administrative_area ?? '',
        "locality" => $locality ?? '',
        "dependent_locality" => $dependent_locality ?? '',
        "postal_code" => $postal_code ?? '',
        "sorting_code" => $sorting_code ?? '',
        "address_line1" => $address_line1 ?? '',
        "address_line2" => $address_line2 ?? '',
        "address_line3" => $address_line3 ?? '',
        "organization" => $organization ?? '',
        "given_name" => $given_name ?? '',
        "additional_name" => $additional_name ?? '',
        "family_name" => $family_name ?? '',
      ]);
    
    $group->save();    
    $this->messenger()->addMessage($this->t('Client is updated successfully.'));
    $form_state->setRedirectUrl(Url::fromRoute('view.client_details.page_1'));
  }



  function customAddressAlter(array $element, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Remove specific subfields
    unset($element['address_line2']);
    unset($element['address_line2']);
    unset($element['address_line3']);
    unset($element['organization']);
    unset($element['given_name']);
    unset($element['family_name']);
    return $element;
  }
}




