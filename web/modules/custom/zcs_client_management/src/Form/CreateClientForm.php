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

    
    $lists = require __DIR__ . '/../../resources/currencies.php';
    // to fetch currencies.
    $currencies = [];
    foreach ($lists as $list) {
      if (!empty($list['locale'])) {
        $currencies[$list['locale']] = $list['currency'] .' ('. $list['alphabeticCode'] .')';
      }
    }
    $defaultCurrency = 'en_US';
 

    $form['partner_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Name'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],
      '#maxlength' => 20,        
    ];

    $form['contact_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Name'),
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],        
    ];
    $form['contact_email'] = [
      '#type' => 'email',
      '#title' => 'Contact Email',
      '#required' => TRUE,
      '#attributes' => [
        'autocomplete' => 'off'
      ],
    ];
    $form['partner_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#required' => TRUE,
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

    $form['partner_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
         'demandpartner' => 'Demand Partner',
         'enterprise' => 'Enterprise',
        ],
      '#required' => TRUE,
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
 

    $form['address_info']['address'] = [
      '#type' => 'address',
      '#title' => $this->t('Address'),
      '#required' => TRUE,      
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

    return $form;
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
    $partner_name = $form_state->getValue('partner_name'); 
    $contact_name = $form_state->getValue('contact_name'); 
    $contact_email = $form_state->getValue('contact_email'); 
    $partner_description = $form_state->getValue('partner_description'); 
    $partner_status = $form_state->getValue('partner_status'); 
    $partner_type = $form_state->getValue('partner_type'); 
    // create consumer in kong: 
    if (\Drupal::moduleHandler()->moduleExists('zcs_aws')) {
      $group = Group::create([
        'type' => 'partner',
        'label' => $partner_name,
        'field_contact_name' => $contact_name,
        'field_contact_email' => $contact_email,
        'field_description' => $partner_description,
        'field_partner_status' => $partner_status,
        'field_partner_type' => $partner_type,
        'user_id' => \Drupal::currentUser()->id(),
        'created' => \Drupal::time()->getRequestTime(),
      ]);
      $group->save(); 
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
    else {
      try {
        $response = \Drupal::service('zcs_kong.kong_gateway')->createConsumer($contact_name, $contact_email);
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
            'user_id' => \Drupal::currentUser()->id(),
            'created' => \Drupal::time()->getRequestTime(),
          ]);
          $group->save(); 
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
        
          $kong_response = $response->getBody()->getContents();
          $response = Json::decode($kong_response);
          $group->set('field_consumer_id', $response['id']);
          $group->save();   
          $this->messenger()->addMessage($this->t('Client is invited successfully.'));
          $form_state->setRedirectUrl(Url::fromRoute('view.client_details.page_1'));
        }
        else {
          // logger
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
    $invitation_link = Link::fromTextAndUrl(t('here'), $invitation_url)->toString();

    $email_body = 'Click below link to activate your account and you have been added to the partner.<br>After activation you will be receiving further emails for onboarding process.';
    $email_body .= '<br>link:' .  $invitation_link;


    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'zcs_client_management';
    $key = 'client_member_invite';
    $to = $user_email;
    $params['subject'] = 'Partner Onboarding - Account Activation';
    $params['message'] = $email_body;
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

}
