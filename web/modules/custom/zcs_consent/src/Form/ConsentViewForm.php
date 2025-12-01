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
      '#weight' => 2,
      '#states' => [
        'invisible' => [
          ':input[name="action"]' => ['value' => 'delete'],
        ],
      ],
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
    $msisdn_values = array_map('trim', explode(',', $msisdn));
    foreach ($msisdn_values as $num) {
      $result[] = [
        'msisdn' => trim($num),
        'status' => $grant_type,
      ];
    }
    if(count($result) == 1 ) {
      if ($action == 'add') {
        $endpoint = \Drupal::config('zcs_custom.settings')->get('consent_endpoint') ?? '';
        $response = $this->addOperationForSingleCall($endpoint, $result);
        \Drupal::messenger()->addMessage(Markup::create($response), 'status');
      }
      if($action == 'delete') {
        $endpoint = \Drupal::config('zcs_custom.settings')->get('consent_endpoint') ?? '';
        $response = $this->deleteOperationForSingleCall($endpoint, $result);
        \Drupal::messenger()->addMessage(Markup::create($response), 'status');
      }
    }
    else {
      if ($action == 'add') {
        $endpoint = \Drupal::config('zcs_custom.settings')->get('consent_endpoint') ?? '';
        $response = $this->addOperationForbatch($endpoint, $result);
        \Drupal::messenger()->addMessage(Markup::create($response), 'status');
      }
      if ($action == 'delete') {
        $endpoint = \Drupal::config('zcs_custom.settings')->get('consent_endpoint') ?? '';
        $response = $this->deleteOperationForbatch($endpoint, $result);
        \Drupal::messenger()->addMessage(Markup::create($response), 'status');
      }

    }
  
    $form_state->setRedirect('zcs_consent.consent_view');
  }


  /**
   * {@inheritdoc}
   */
  public function deleteOperationForbatch($endpoint, $result){
    $endpoint = $endpoint .'/'. 'batch';

    $msisdn_values = [];
    foreach($result as $msisdn) {
       $msisdn_values[]  = $msisdn['msisdn'];
    }
    $params = [];
    foreach ($msisdn_values as $m) {
        $params[] = 'msisdns=' . urlencode($m);
    }
    $query = implode('&', $params);
    $url = $endpoint . '?' . $query;

    try {
      $response = \Drupal::httpClient()->request('DELETE',  $url, [
        'headers' => [
        'content-type' => 'application/json',
        ],
        'verify' => FALSE,
      ]);
      if ($response->getStatusCode() == '200') {
        $response = '<div class="consent-success">Successfully Deleted.</div>';
        return $response;
      }
    }
    catch (\Exception $e) {
      if ($e->getResponse()->getStatusCode() == '400') {
        \Drupal::logger('consent_error')->info('Error in deleting consent : @message', ['@message' => $e->getMessage()]);
        $error = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        $data = json_decode($error, TRUE);      
        $msg = $data['detail'];
        $response = "<div class='consent-error'>$msg</div>";
        return $response;
      }
      elseif ($e->getResponse()->getStatusCode() == '422') {
        \Drupal::logger('consent_error')->info('Error in deleting consent : @message', ['@message' => $e->getMessage()]);
        $error = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        $data = json_decode($error, TRUE);      
        $msg = $data['detail'];
        $response = "<div class='consent-error'>$msg</div>";
        return $response;
      }
      else {
        return $response = "<div class='consent-error'>Something went wrong...!</div>";
      }
    }
  }

 /**
   * {@inheritdoc}
   */

  public function addOperationForbatch($endpoint, $result){
    $endpoint = $endpoint .'/'. 'batch';
    $consents = [
      'consents' => $result,
    ];
    $request_body = json::encode($consents);
    try {
      $response = \Drupal::httpClient()->request('POST', $endpoint, [
        'headers' => [
          'content-type' => 'application/json',
        ],
        'verify' => FALSE,
        'body' => $request_body,
      ]);
      if ($response->getStatusCode() == '201') {
        $add_response = json_decode($response->getBody()->getContents(), TRUE);
        $response = '<div class="consent-success">Success</div>';
        return $response;
      }
    }
    catch (\Exception $e) {
      if ($e->getResponse()->getStatusCode() == '400') {
        \Drupal::logger('consent_error')->info('Error in creating consent : @message', ['@message' => $e->getMessage()]);
        $error = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        $data = json_decode($error, TRUE);      
        $msg =  $data['detail'][0]['msg'];
        return $response = "<div class='consent-error'>$msg</div>";
      }
      else if ($e->getResponse()->getStatusCode() == '422') {
        \Drupal::logger('consent_error')->info('Error in creating consent : @message', ['@message' => $e->getMessage()]);
        $error = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        $data = json_decode($error, TRUE);      
        $msg =  $data['detail'][0]['msg'];
        return $response = "<div class='consent-error'>$msg</div>";
      }
      else {
        return $response = "<div class='consent-error'>Something went wrong...!</div>";
      }
    }


  }


 /**
   * {@inheritdoc}
   */
  public function deleteOperationForSingleCall($endpoint, $result){
   $result = reset($result);
   $value = $result['msisdn'];
   $endpoint = $endpoint .'/'. $value;
    try {
      $response = \Drupal::httpClient()->request('DELETE', $endpoint, [
        'headers' => [
        'content-type' => 'application/json',
        ],
        'verify' => FALSE,
      ]);
      if ($response->getStatusCode() == '204') {
        $response = '<div class="consent-success">Successfully Deleted.</div>';
        return $response;
      }
    }
    catch (\Exception $e) {
      if ($e->getResponse()->getStatusCode() == '400') {
        \Drupal::logger('consent_error')->info('Error in creating consent : @message', ['@message' => $e->getMessage()]);
        $error = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        $data = json_decode($error, TRUE);      
        $msg = $data['detail'];
        $response = "<div class='consent-error'>$msg</div>";
        return $response;
      }
      elseif ($e->getResponse()->getStatusCode() == '404') {
        \Drupal::logger('consent_error')->info('Error in creating consent : @message', ['@message' => $e->getMessage()]);
        $error = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        $data = json_decode($error, TRUE);      
        $msg = $data['detail'];
        $response = "<div class='consent-error'>$msg</div>";
        return $response;
      }
      else {
        return $response = "<div class='consent-error'>Something went wrong...!</div>";
      }
    }

  }
  

  /**
   * {@inheritdoc}
   */
  public function addOperationForSingleCall($endpoint, $result){
    $result = reset($result);
    $request_body = json::encode($result);
    try {
      $response = \Drupal::httpClient()->request('POST', $endpoint, [
        'headers' => [
          'content-type' => 'application/json',
        ],
        'verify' => FALSE,
        'body' => $request_body,
      ]);
      if ($response->getStatusCode() == '201') {
        $add_response = json_decode($response->getBody()->getContents(), TRUE);
        $response = '<div class="consent-success">Success</div>';
        return $response;
      }
    }
    catch (\Exception $e) {
      if ($e->getResponse()->getStatusCode() == '409'){
        $error = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        $data = json_decode($error, TRUE);
        $msg = $data['detail'];;
        $response = "<div class='consent-error'>$msg</div>";
        return $response;
      }
      else if ($e->getResponse()->getStatusCode() == '422') {
        \Drupal::logger('consent_error')->info('Error in creating consent : @message', ['@message' => $e->getMessage()]);
        $error = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        $data = json_decode($error, TRUE);      
        $msg =  $data['detail'][0]['msg'];
        return $response = "<div class='consent-error'>$msg</div>";
      }
      else {
        return $response = "<div class='consent-error'>Something went wrong...!</div>";
      }
    }
  }
}
