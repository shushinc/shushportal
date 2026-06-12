<?php

declare(strict_types=1);

namespace Drupal\zcs_client_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\user\Entity\User;
use Drupal\group\Entity\GroupRelationship;
use Drupal\group\Entity\GroupContent;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\Markup;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AddClassCommand;
use Drupal\Core\Ajax\InvokeCommand;

/**
 * Provides a zcs Client Management form.
 */
class CreateClientForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_client_management_create_client_partner';
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
            border: 2px solid #ddd;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
          }
        ',
      ],
      'custom_toggle_css',
    ];

    $lists_currencies = require __DIR__ . '/../../resources/currencies.php';

    $form['client_validation_message'] = [
      '#type' => 'markup',
      '#markup' => '<div id="client_validation_message" class="zcs_validation_message"></div>',
      '#weight' => -100,
    ];
    $form['partner_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Name'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],
      '#ajax' => [
        'callback' => '::validateClientajax',
        'event' => 'change',
      ],
      '#maxlength' => 20,
      '#prefix' => '<div class="client-Layout-column-wrapper"><div class="tiles-wrapper client-Layout-column-first"><div class="partner-info grid-layout-column">',
    ];

    $form['contact_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Name'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],
      '#ajax' => [
        'callback' => '::validateClientajax',
        'event' => 'change',
      ],
    ];

    $form['contact_email'] = [
      '#type' => 'email',
      '#title' => 'Contact Email',
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],
      '#ajax' => [
        'callback' => '::validateClientajax',
        'event' => 'change',
      ],
      '#suffix' => '</div>', // Closes the wrapper
    ];

    $form['client_legal_contact'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Legal Contact'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],
      '#prefix' => '<div class="partner-contact-info grid-layout-column">',
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
    ];

    $form['partner_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'active' => 'Active',
        'inactive' => 'InActive',
      ],
      '#default_value' => 'active',
      '#required' => TRUE,
      '#suffix' => '</div>',
    ];

    $form['client_pricing_column_wrapper_text'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'class' => ['client-contact-details-col-4'],
      ],
    ];

    $form['client_pricing_column_wrapper_text']['pricing_type'] = [
      '#type' => 'select',
      '#title' => 'Pricing Type',
      '#required' => TRUE,
      '#options' => [
        'domestic_pricing' => $this->t('Domestic Pricing'),
        'international_pricing' => $this->t('International Pricing'),
      ],
    ];

    $form['client_Layout_column_wrapper_text'] = [
      '#type' => 'fieldset',
      '#attributes' => [
        'class' => ['client-contact-details-col-4'],
      ],
    ];

    $form['client_Layout_column_wrapper_text']['address'] = [
      '#type' => 'address',
      '#title' => $this->t('Address'),
      '#required' => TRUE,
      '#after_build' => [[$this, 'customAddressAlter']],
      '#default_value' => [
        'country_code' => 'US',
      ],
      '#suffix' => '</div>',
    ];

    $form['client_Layout_column_wrapper_text']['partner_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#required' => TRUE,
      '#prefix' => '<div class="partner-desc">',
      '#suffix' => '</div></div>',
    ];

    $code = \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US';
    foreach($lists_currencies as $currency_data) {
      if ($currency_data['locale'] === $code) {
        $currency_code = $currency_data['alphabeticCode'];
      }
    }

    $form['currencies'] = [
      '#type' => 'textfield',
      '#default_value' => $currency_code,
      // '#disabled' => TRUE, // disables the field
      '#attributes' => [
        'readonly' => 'readonly',
      ],
      '#prefix' => '<div class="payment-details client-Layout-column-second">',
    ];

    $form['prepayment_amount'] = [
      '#type' => 'number',
      '#title' => 'Prepayment Amount',
      '#min' => 0,
      '#default_value' => 0.00,
     // '#step' => 0.001,
    ];

    $form['prepayment_balance_left'] = [
      '#type' => 'number',
      '#title' => 'Prepayment Balance Left',
      '#min' => 0,
      '#default_value' => 0.00,
     // '#step' => 0.001,
    ];

    $form['prepayment_balance_used'] = [
      '#type' => 'number',
      '#title' => 'Prepayment Balance Used',
      '#min' => 0,
      '#default_value' => 0.00,
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

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'api_attributes')
      ->sort('field_attribute_weight', 'ASC')
      ->accessCheck()
      ->execute();
    $contents = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);

    if (!empty($contents)) {
      $attribute_no = 1;
      foreach ($contents as $content) {
        $nids[] = $content->id();
        // Checkbox to enable/select this attribute
        $form['api_agreement_covers']['attribute_' . $content->id()]= [
          '#type' => 'checkbox',
          '#title' => Markup::create("<span class='attribute-no'>$attribute_no. </span>") .$content->label(),
          '#default_value' => FALSE,
          '#attributes' => [
            'class' => ['toggle-checkbox'],
          ],
        ];
        $attribute_no = $attribute_no + 1;
      }
    }

    $form['selected_rate_sheets'] = [
      '#type' => 'fieldset',
      '#title' => 'Selected Rate Sheets',
      '#attributes' => [
        'class' => ['custom-fieldset'],
      ],
      '#prefix' => '<div class="selected-rate-sheets">',
    ];

    // Load approved rate sheets from the database
    $database = \Drupal::database();
    $rate_sheet_status_lookup = $database->select('rate_sheet_status_lookup', 'rssl')
      ->fields('rssl', ['id'])
      ->condition('status_name', 'Approved')
      ->execute()
      ->fetchField();

    if ($rate_sheet_status_lookup) {
      $rate_sheets_query = $database->select('rate_sheet', 'rs')
        ->fields('rs', ['id', 'name', 'effective_date'])
        ->condition('rate_sheet_status_id', $rate_sheet_status_lookup)
        ->orderBy('effective_date', 'DESC');
      $rate_sheets = $rate_sheets_query->execute()->fetchAll();

      if (!empty($rate_sheets)) {
        $rate_sheet_no = 1;
        $rate_sheet_ids = [];
        foreach ($rate_sheets as $rate_sheet) {
          $rate_sheet_ids[] = $rate_sheet->id;
          $effective_date = date('M d, Y', intval($rate_sheet->effective_date));
          $form['selected_rate_sheets']['rate_sheet_' . $rate_sheet->id] = [
            '#type' => 'checkbox',
            '#title' => Markup::create("<span class='attribute-no'>$rate_sheet_no. </span>") . $rate_sheet->name . ' (' . $effective_date . ')',
            '#default_value' => FALSE,
            '#attributes' => [
              'class' => ['toggle-checkbox'],
            ],
          ];
          $rate_sheet_no++;
        }

        $form['selected_rate_sheets']['rate_sheet_nodes'] = [
          '#type' => 'hidden',
          '#value' => implode(",", $rate_sheet_ids),
        ];
      }
      else {
        $form['selected_rate_sheets']['no_rate_sheets'] = [
          '#markup' => '<p>No approved rate sheets available.</p>',
        ];
      }
    }
    else {
      $form['selected_rate_sheets']['no_status'] = [
        '#markup' => '<p>Approved status not found in the system.</p>',
      ];
    }

    $form['selected_rate_sheets']['#suffix'] = '</div>';

    $form['api_agreement_covers']['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(",", $nids),
      '#suffix' => '</div>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Invite Client'),
      ],
      '#attributes' => [
        'class' => ['zcs-submit'],
      ]
    ];

    $form['#attached']['library'][] = 'zcs_client_management/client-view-page';

    return $form;
  }



  public function validateClientajax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $values = $form_state->getValues();
    $client_name = $values['partner_name'];
    $contact_email = $values['contact_email'];
    $contact_name = $values['contact_name'];
    $message = '';
    if(!empty($client_name)) {
      $query = \Drupal::entityQuery('group')
        ->condition('label', $client_name)
        ->accessCheck(FALSE);
      $gids = $query->execute();   
      if ($gids) {
        $message = 'This Client Name is already registered or has an active invitation. Please verify their details and try again...!';
          // Disable the submit button
          $response->addCommand(new InvokeCommand(
            '[data-drupal-selector="edit-submit"]',
            'prop',
            ['disabled', true]
          ));
          $response->addCommand(new InvokeCommand(
            '[data-drupal-selector="edit-submit"]',
            'addClass',
            ['is-disabled']
          )); 


        $response->addCommand(new InvokeCommand(
          '#client_validation_message',
          'addClass',
          ['message']
        ));
      }
      else {  
        $response->addCommand(new InvokeCommand(
          '#client_validation_message',
          'removeClass',
          ['message']
        ));
        // Disable the submit button
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'prop',
          ['disabled', false]
        ));
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'removeClass',
          ['is-disabled']
        ));
      }
    }
    if(!empty($contact_email)) {
      $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $contact_email]);      
      if ($users) {
        $message = 'This Contact Email is already registered or has an active invitation. Please verify their details and try again..!';
        // Disable the submit button
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'prop',
          ['disabled', true]
        ));
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'addClass',
          ['is-disabled']
        )); 
        $response->addCommand(new InvokeCommand(
          '#client_validation_message',
          'addClass',
          ['message']
        ));
      }
      else {  
        // Disable the submit button
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'prop',
          ['disabled', false]
        ));
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'removeClass',
          ['is-disabled']
        ));
        $response->addCommand(new InvokeCommand(
          '#client_validation_message',
          'removeClass',
          ['message']
        ));
      }
    }

    if(!empty($contact_name)) {
      $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => $contact_name]);     
      if ($users) {
        $message = 'This Contact Name is already registered or has an active invitation. Please verify their details and try again...!';
        // Disable the submit button
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'prop',
          ['disabled', true]
        ));
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'addClass',
          ['is-disabled']
        )); 
        $response->addCommand(new InvokeCommand(
          '#client_validation_message',
          'addClass',
          ['message']
        ));
      }
      else {  
        // Disable the submit button
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'prop',
          ['disabled', false]
        ));
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'removeClass',
          ['is-disabled']
        ));
        $response->addCommand(new InvokeCommand(
          '#client_validation_message',
          'removeClass',
          ['message']
        ));
      }
    }
    

     


      // Update the message container dynamically
    $response->addCommand(new HtmlCommand('#client_validation_message', $message));
  
    return $response;
  }




  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
     // @todo Validate the form here.
    // @endcode
    parent::validateForm($form, $form_state);
    $user_email = $form_state->getValue('contact_email');
    $user_name = $form_state->getValue('contact_name');
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $user_email]);
    if ($users) {
      $form_state->setError($form['contact_email'], $this->t('This user is already registered or has an active invitation. Please verify their details and try again.'));
    }
    $username = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => $user_name]);
    if ($username) {
      $form_state->setError($form['contact_name'], $this->t('This username is already registered or has an active invitation. Please verify their details and try again.'));
    }
   }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $nids = explode(",", $values['nodes']);
    foreach ($nids as $nid) {
      $json[$nid] = $values['attribute_' . $nid];
    }
    $encoded_data = Json::encode($json);

    // Process selected rate sheets - we'll handle client_rate_sheet table separately
    $rate_sheet_json = [];
    $selected_rate_sheets = [];
    if (!empty($values['rate_sheet_nodes'])) {
      $rate_sheet_ids = explode(",", $values['rate_sheet_nodes']);
      foreach ($rate_sheet_ids as $rate_sheet_id) {
        $is_selected = $values['rate_sheet_' . $rate_sheet_id] ?? 0;
        $rate_sheet_json[$rate_sheet_id] = $is_selected;
        if ($is_selected) {
          $selected_rate_sheets[] = $rate_sheet_id;
        }
      }
    }
    $encoded_rate_sheets = Json::encode($rate_sheet_json);
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
    $pricing_type = $form_state->getValue('pricing_type');
    $prepayment_amount = $form_state->getValue('prepayment_amount');
    $prepayment_balance_left = $form_state->getValue('prepayment_balance_left');
    $prepayment_balance_used= $form_state->getValue('prepayment_balance_used');

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

    if (\Drupal::moduleHandler()->moduleExists('zcs_kong')) {
      // create consumer in kong:
      try {
        $response = \Drupal::service('zcs_kong.kong_gateway')->createConsumer($contact_name, $contact_email);
        if($response != 'error'){
          $status_code = $response->getStatusCode();
          if ($status_code == '201') {
            $group = Group::create([
              'type' => 'partner',
              'label' => $partner_name,
              'field_contact_name' => $contact_name,
              'field_contact_email' => $contact_email,
              'field_description' => $partner_description,
              'field_partner_status' => $partner_status,
              'field_partner_type' => $partner_type,
              'field_client_legal_contact' => $client_legal_contact,
              'field_client_point_of_contact' => $client_point_of_contact,
              'field_agreement_effective_date' => $agreement_effective_date,
              'field_prepayment_amount' => $prepayment_amount,
              'field_prepayment_balance_left' => $prepayment_balance_left,
              'field_prepayment_balance_used' => $prepayment_balance_used,
              'field_currency' => \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US',
              'field_industry' => $industry,
              'field_pricing_type' => $pricing_type,
              'field_apis_agreement_covers' => $encoded_data,
              'user_id' => \Drupal::currentUser()->id(),
              'created' => \Drupal::time()->getRequestTime(),
            ]);
            $group->save();
            $uid = \Drupal::currentUser()->id();
            $user = User::load($uid);
            $group->addMember($user, ['group_roles' => ['partner-admin']]);
            $group->save();

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
              "given_name" => $form_state->getValue('contact_name') ?? '',
              "additional_name" => $additional_name ?? '',
              "family_name" => $form_state->getValue('contact_name') ?? '',
            ]);
            $group->save();
            $user = User::create([
              'name' => $contact_name,
              'mail' => $contact_email,
              'status' => 0, //
              'roles' => 'authenticated',
            ]);
            $user->save();

            $token = $this->generateToken();
            $save_invitation = $this->saveInvitation($group->id(), $contact_name, $contact_email, 'partner-admin', $token);
            $send_email = $this->sendInvitationMail($group->id(), $contact_name, $contact_email, 'partner-admin', $token);

            $kong_response = $response->getBody()->getContents();
            $response = Json::decode($kong_response);
            $group->set('field_consumer_id', $response['id']);
            $group->save();
            
            // Handle client_rate_sheet relationships
            $this->handleClientRateSheetRelationships($group->id(), $selected_rate_sheets);
            
            $client_billing_profile = \Drupal::service('zcs_client_management.client_management')->createUpdateClientBilling($group);
            $this->messenger()->addMessage($this->t('Client is invited successfully.'));
            $form_state->setRedirectUrl(Url::fromRoute('view.client_details.page_1'));
          }
          else {
            // logger
          }
        }

      } catch (RequestException $e) {
        if ($e->hasResponse()) {
          $error_response = $e->getResponse();
          if ($error_response->getStatusCode() == '409') {
            $this->messenger()->addError($this->t('Unique constraint violation detected on Partner Name  or Contact Email.'));
          }
        } else {
          $this->messenger()->addError($this->t('Request Error: ' . $e->getMessage()));
        }
      }
    }
    else {
      $group = Group::create([
        'type' => 'partner',
        'label' => $partner_name,
        'field_contact_name' => $contact_name,
        'field_contact_email' => $contact_email,
        'field_description' => $partner_description,
        'field_partner_status' => $partner_status,
        'field_partner_type' => $partner_type,
        'field_client_legal_contact' => $client_legal_contact,
        'field_client_point_of_contact' => $client_point_of_contact,
        'field_agreement_effective_date' => $agreement_effective_date,
        'field_prepayment_amount' => $prepayment_amount,
        'field_prepayment_balance_left' => $prepayment_balance_left,
        'field_prepayment_balance_used' => $prepayment_balance_used,
        'field_currency' => \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US',
        'field_industry' => $industry,
        'field_pricing_type' => $pricing_type,
        'field_apis_agreement_covers' => $encoded_data,
        'field_selected_rate_sheets' => $encoded_rate_sheets,
        'user_id' => \Drupal::currentUser()->id(),
        'created' => \Drupal::time()->getRequestTime(),
      ]);
      $group->save();

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
        "given_name" => $form_state->getValue('contact_name') ?? '',
        "additional_name" => $additional_name ?? '',
        "family_name" => $form_state->getValue('contact_name') ?? '',
      ]);
      $group->save();
      
      // Handle client_rate_sheet relationships
      $this->handleClientRateSheetRelationships($group->id(), $selected_rate_sheets);
      
      $client_billing_profile = \Drupal::service('zcs_client_management.client_management')->createUpdateClientBilling($group);
      $uid = \Drupal::currentUser()->id();
      $user = User::load($uid);
      $group->addMember($user, ['group_roles' => ['partner-admin']]);
      $group->save();
      $user = User::create([
        'name' => $contact_name,
        'mail' => $contact_email,
        'status' => 0, //
        'roles' => 'authenticated',
      ]);
      $user->save();
      $token = $this->generateToken();
      $save_invitation = $this->saveInvitation($group->id(), $contact_name, $contact_email, 'partner-admin', $token);
      $send_email = $this->sendInvitationMail($group->id(), $contact_name, $contact_email, 'partner-admin', $token);
      $this->messenger()->addMessage($this->t('Client is invited successfully.'));
      $form_state->setRedirectUrl(Url::fromRoute('view.client_details.page_1'));
    }
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
  public function sendInvitationMail($client_id, $user_name, $user_email, $partner_role, $token) {
    $invitation_url = Url::fromRoute('zcs_client_management.verify_invitation', [
      'token' => $token,
    ], ['absolute' => TRUE]);

    $attributes = [
      'target' => '_blank',
      'style' => 'display: inline-block; padding: 12px 24px; font-size: 16px; color: #ffffff; text-decoration: none; font-weight: bold; background:#007bff; border-radius: 5px;',
    ];

    $invitation_link = Link::fromTextAndUrl(t('Activate'), $invitation_url)
      ->toRenderable();
    $invitation_link['#attributes'] = $attributes;

    $rendered_link = \Drupal::service('renderer')->render($invitation_link);

    $site_name = \Drupal::config('system.site')->get('name');
    $email_subject = \Drupal::config('zcs_custom.portal_email_settings')->get('client_email_subject');
    $email_body = \Drupal::config('zcs_custom.portal_email_settings')->get('client_email_body');

    $user_name = $this->getUserNameUsingEmail($user_email);

    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'zcs_client_management';
    $key = 'client_member_invite';
    $to = $user_email;
    $params['subject'] = $email_subject;
    $params['message'] = \Drupal::token()->replace($email_body, ['user_name' => $user_name, 'user_invite_activation_url' => $rendered_link, 'site_name' => $site_name]);

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

  public function getUserNameUsingEmail($email) {
    $username = '';
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    if (!empty($users)) {
      $user = reset($users);
      $username = $user->getAccountName();
    }
    return $username;
  }

  /**
   * Handle client rate sheet relationships.
   */
  protected function handleClientRateSheetRelationships($client_id, array $selected_rate_sheets) {
    if (empty($selected_rate_sheets)) {
      return;
    }

    $database = \Drupal::database();
    $current_user_id = \Drupal::currentUser()->id();
    $current_date = date('Y-m-d H:i:s');

    // Get Pending status ID
    $pending_status_id = $database->select('rate_sheet_status_lookup', 'rssl')
      ->fields('rssl', ['id'])
      ->condition('status_name', 'Pending')
      ->execute()
      ->fetchField();

    foreach ($selected_rate_sheets as $rate_sheet_id) {
      // Check if relationship already exists
      $exists = $database->select('client_rate_sheet', 'crs')
        ->fields('crs', ['rate_sheet_id'])
        ->condition('rate_sheet_id', $rate_sheet_id)
        ->condition('client_id', $client_id)
        ->execute()
        ->fetchField();

      if (!$exists) {
        // Create new relationship with Pending status
        $database->insert('client_rate_sheet')
          ->fields([
            'rate_sheet_id' => $rate_sheet_id,
            'client_id' => $client_id,
            'created_by' => $current_user_id,
            'created_date' => $current_date,
            'active' => 0,
            'rate_sheet_client_status_id' => $pending_status_id,
          ])
          ->execute();
      }
    }
  }

}

