<?php

declare(strict_types=1);

namespace Drupal\zcs_consent\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Component\Serialization\Json;


/**
 * Provides a Zcs consent form.
 */
final class ConsentViewForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_consent_consent_view';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['action'] = [
      '#type' => 'select',
      '#title' => $this->t('Action'),
      '#options' => [
        'add' => 'Add',
        'delete' => 'Delete',
      ],
      '#required' => TRUE,
      '#weight' => 1,
    ];
    $form['grant_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Grant Type'),
      '#options' => [
        'true' => 'Allow',
        'false' => 'Deny',
      ],
      '#required' => TRUE,
      '#weight' => 2,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'class' => ['btn', 'btn-primary'],
      ],
      '#weight' => 3,
      '#prefix' => '<div class="consent-submit>',
      '#suffix' => '</div>',
    ];

    $form['msisdn'] = [
      '#type' => 'textarea',
      '#default_value' => '',
      '#placeholder' => 'Provide inputs with comma seperated (eg: +897871232144, +232142124124)',
      '#rows' => 5,
      '#cols' => 5,
      '#required' => TRUE,
      '#weight' => 4,
      '#attributes' => [
        'style' => 'width:600px;',
      ],
    ];
    $form['#theme'] = 'consent_theme';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $action = $form_state->getValue('action');
    $grant_type = $form_state->getValue('grant_type');
    $msisdn = $form_state->getValue('msisdn');
    $response = $this->consentApiCall($action, $grant_type, $msisdn);
    $display = '';
    foreach ($response as $message) {
      $display .= $message;
    }

    \Drupal::messenger()->addMessage(Markup::create($display), 'status');
    $form_state->setRedirect('zcs_consent.consent_view');
  }



  public function consentApicall($action, $grant_type, $msisdn){
    $msisdn_values = array_map('trim', explode(',', $msisdn));
    $endpoint = 'http://34.102.232.155/'.'consent/';
    foreach ($msisdn_values as $num) {
      $result[] = [
        'msisdn' => trim($num),
        'status' => $grant_type,
      ];
    }
    $responses = [];
    if($action == 'add') {
      foreach($result as $consent_data) {
        $request_body = json::encode($consent_data);
        try {
          $response = \Drupal::httpClient()->request('POST', $endpoint, [
            'headers' => [
              'content-type' => 'application/json',
            ],
            'verify' => FALSE,
            'json' => $consent_data,
          ]);
          if ($response->getStatusCode() == '201') {
            $add_response = json_decode($response->getBody()->getContents(), TRUE);
            $response = '<div class="consent-success">' . $add_response['msisdn'] . ' - ' . $add_response['message'] . '</div>';
          }
        }
        catch (\Exception $e) {
          \Drupal::logger('consent_error')->info('Error in creating consumer in kong gateway : @message', ['@message' => $e->getMessage()]);
          $error = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
          $data = json_decode($error, TRUE);      
          $msg = $data['detail'][0]['msg'] ?? '';
          $input = $data['detail'][0]['input'] ?? '';
          $response = "<div class='consent-error'>$msg input: $input</div>";
        }  
        $responses[] = $response;
      }
      return $responses;       
    }
    if($action == 'delete') {
      foreach ($msisdn_values as $num) {
        $delete_result[] = [
          'msisdn' => trim($num),
        ];
      }
      foreach($delete_result as $consent_data_delete) {
        try {
          $response = \Drupal::httpClient()->request('DELETE', $endpoint, [
            'headers' => [
            'content-type' => 'application/json',
            ],
            'json' => $consent_data_delete,
            'verify' => FALSE,
          ]);
          if ($response->getStatusCode() == '200') {
            $delete_response = json_decode($response->getBody()->getContents(), TRUE);
            $response = '<div class="consent-success">' .$consent_data_delete['msisdn'] .':'. $delete_response['message'] .'</div>';
          }
        }
        catch (\Exception $e) {
          \Drupal::logger('consent_error')->info('Error in creating consumer in kong gateway : @message', ['@message' => $e->getMessage()]);
          $error = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
          $data = json_decode($error, TRUE);      
          $msg = $data['detail'][0]['msg'] ?? '';
          $input = $data['detail'][0]['input'] ?? '';
          $response = "<div class='consent-error'>$msg input: $input</div>";
        }
        $responses[] = $response; 
      }
      return $responses;
    }
  }

}
