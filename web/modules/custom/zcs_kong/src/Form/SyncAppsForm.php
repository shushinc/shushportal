<?php

namespace Drupal\zcs_kong\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\Group;
use Drupal\Component\Serialization\Json;

class SyncAppsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sync_kong_apps_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $rows = [];

    // Load groups safely.
    $groups = Group::loadMultiple();

    if (!empty($groups)) {

      foreach ($groups as $group) {

        $rows[$group->id()] = [
          'name' => $group->label(),
          'gid' => $group->id(),
          'type' => $group->bundle(),
        ];
      }
    }

    $form['groups'] = [
      '#type' => 'tableselect',
      '#header' => [
        'name' => $this->t('Group Name'),
        'gid' => $this->t('Group ID'),
        'type' => $this->t('Group Type'),
      ],
      '#options' => $rows,
      '#empty' => $this->t('No groups available.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Batch Process'),
    ];

    return $form;
  }

  /**
   * Submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $selected = array_filter($form_state->getValue('groups'));

    if (empty($selected)) {

      $this->messenger()->addError(
        $this->t('Please select at least one group.')
      );

      return;
    }

    $operations = [];

    foreach (array_keys($selected) as $gid) {

      $operations[] = [
        [static::class, 'processGroup'],
        [$gid],
      ];
    }

    $batch = [
      'title' => $this->t('Processing Groups'),
      'operations' => $operations,
      'finished' => [static::class, 'batchFinished'],
      'init_message' => $this->t('Starting batch process...'),
      'progress_message' => $this->t('Processed @current out of @total'),
      'error_message' => $this->t('Batch process failed.'),
    ];

    batch_set($batch);
  }


 /**
 * Batch operation callback.
 */

    /**
 * Batch operation callback.
 */
public static function processGroup($gid, array &$context) {

    $group = Group::load($gid);
    if (!$group) {
      return;
    }
    // Load group relationships.
    $relationships = \Drupal::entityTypeManager()
      ->getStorage('group_relationship')
      ->loadByProperties([
        'gid' => $gid,
      ]);
  
    $app_nodes = [];
    foreach ($relationships as $relationship) {
      // Get related entity.
      $entity = $relationship->getEntity();
      // Check entity exists and is node type app.
      if ($entity && $entity->getEntityTypeId() === 'node' && $entity->bundle() === 'app') {
        // field_gateway empty/null check.
        $gateway_empty = $entity->get('field_gateway')->isEmpty();
        if ($gateway_empty) {
            $app_nodes[] = $entity;  
            \Drupal::logger('zcs_kong')->notice(
            'Eligible App Node: @title (NID: @nid)',[
              '@title' => $entity->label(),
              '@nid' => $entity->id(),
            ]
          );
        }
      }
    }
  
    // processing.
    foreach ($app_nodes as $node) {
        $consumer_id = $node->get('field_consumer_id')->getValue()[0]['value'];
        $group_consumer_id = $group->get('field_consumer_id')->getValue()[0]['value'];
        if ($group_consumer_id == $consumer_id) {
          $contact_email =  \Drupal::service('zcs_kong.kong_gateway')->getContactEmailUsingConsumerId($consumer_id); 
          $get_consumer_response = \Drupal::service('zcs_kong.kong_gateway')->getConsumer($contact_email);
          if($get_consumer_response != 'error'){
            $kong_response = $get_consumer_response->getBody()->getContents();
            $response = Json::decode($kong_response);
            if(!$response['data']){
                $user_name = \Drupal::service('zcs_kong.kong_gateway')->getContactNameUsingConsumerId($consumer_id);
                $contact_email =  \Drupal::service('zcs_kong.kong_gateway')->getContactEmailUsingConsumerId($consumer_id);
                $create_consumer_response = \Drupal::service('zcs_kong.kong_gateway')->createConsumer($user_name, $contact_email);
                if($create_consumer_response != 'error') {
                    $status_code = $create_consumer_response->getStatusCode();
                    if ($status_code == '201') {
                        $create_consumer_response = $create_consumer_response->getBody()->getContents();
                        $kong_create_consumer_response = Json::decode($create_consumer_response);
                        $new_consumer_id =  $kong_create_consumer_response['id'];
                        // App details.
                        $kong_app =  [
                          'name' => $node->getTitle(),
                          'client_id' => $node->field_client_id->value,
                          'client_secret' => $node->field_client_secret->value,
                          "created_at" => time(),
                           "tags" => !empty($node->field_tag->value) ? [$node->field_tag->value]: [],
                          "redirect_uris" => $node->field_redirect_url->value,
                        ];
                        $sync_apps_response = \Drupal::service('zcs_kong.kong_gateway')->syncAppByNewConsumerId($kong_app, $user_name);
                        dump($sync_apps_response);
                        die;
                        if($sync_apps_response != 'error') {
                            $status_code = $sync_apps_response->getStatusCode();
                            if ($status_code == '201') {
                                $response_body  = (string) $sync_apps_response->getBody();
                                $create_jwt_token_response = \Drupal::service('zcs_kong.kong_gateway')->createJwtToken($user_name, $response_body);
                                if(!empty($create_jwt_token_response)) {
                                    $jwt_status_code = $create_jwt_token_response->getStatusCode();
                                    if ($jwt_status_code == '201') {
                                        $jwt_response_body  = (string) $create_jwt_token_response->getBody();
                                        $response_key_details = \Drupal::service('zcs_kong.kong_gateway')->updateSyncApp($node, $new_consumer_id, $jwt_response_body, $group);
                                    }
                                }
                            }
                            
                        }

                    }
                }            
            }
            else {
              // logger
            }
          }
        
        } 
        else {
            //logger
        } 

      \Drupal::logger('zcs_kong')->notice(
        'Processing app node: @title',
        [
          '@title' => $node->label(),
        ]
      );
    }
  
    $context['results'][] = $group->label();
  
    $context['message'] = t(
      'Processed group: @group',
      [
        '@group' => $group->label(),
      ]
    );
  }

  

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, array $results, array $operations) {

    if ($success) {

      \Drupal::messenger()->addStatus(
        t('Processed groups: @groups', [
          '@groups' => implode(', ', $results),
        ])
      );
    }
    else {

      \Drupal::messenger()->addError(
        t('Batch processing failed.')
      );
    }
  }

}