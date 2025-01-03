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
use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\Markup;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupRelationship;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\Entity\GroupMembership;

/**
 * Provides a User Management list form.
 */
final class AppListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_kong_app_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $header = [
      'api_name' => $this->t('Client Name'),
      'description' => $this->t('Description'),
      'tag' => $this->t('Tag'),
      'created' => $this->t('Created'),
      'renewal' => $this->t('Renewal'),
      'expiry' => $this->t('Expiry'),
      'api_key' => $this->t('API Key'),
    ];
    
    
    if (\Drupal::currentUser()->hasRole('carrier_admin') || \Drupal::currentUser()->hasRole('administrator')) {
      $header['operation'] = $this->t('Operations');
      $group_type = 'partner';
      $group_storage = \Drupal::entityTypeManager()->getStorage('group');
      $query = $group_storage->getQuery()->condition('type', $group_type); 
      $group_ids = $query->accessCheck()->execute();
      $clients = $group_storage->loadMultiple($group_ids);
      foreach($clients as $group) {
        if (isset($group->get('field_consumer_id')->getValue()[0])) {
          $group_content_ids = \Drupal::entityQuery('group_relationship')
            ->condition('gid', $group->id())
            ->condition('type', 'partner-group_node-app')  // Replace 'group_node:app' with your actual group content type.
            ->accessCheck()->execute();  

          $group_contents = GroupRelationship::loadMultiple($group_content_ids);
          foreach ($group_contents as $group_content) {
            $app = $group_content->getEntity();     
            $key_id = $app->get('field_app_id')->value;
            $consumer_id = $app->get('field_consumer_id')->value;
            $description = $group->get('field_description')->getValue()[0]['value'];
            $app_status = $app->get('field_app_status')->value;
            if ($app_status == 'active') {
              $url = Url::fromRoute('zcs_kong.edit_key', ['id' => $app->id()]);
              $url->setOptions([
                'attributes' => [
                  'class' => ['use-ajax'], // Enables AJAX
                  'data-dialog-type' => 'modal', // Opens in a modal
                  'data-dialog-options' => json_encode([
                    'width' => 400,
                  ]),
                ],
              ]);
              
              $update_link = Link::fromTextAndUrl('Edit', $url);
              $delete_link = Link::createFromRoute('Delete', 'zcs_kong.delete_key', ['id' => $app->id()]);
              $operation_link = Markup::create($update_link->toString() . ' | ' . $delete_link->toString());
            }
                  
     
            $created_time = $app->get('created')->value;
            $updated_time = $app->get('changed')->value;
            $expiry_time = $app->get('field_expiry_date')->value;
            if ($updated_time > $created_time) {
               $renewal_date =  date('d M Y' , (int)$app->get('changed')->value);
            }


            if ($app->get('field_ttl')->value == 'never_expires') {
              $ttl = 'Never Expires';
              $expiry_time = '-';
             
            }
            else {
              $ttl = $app->get('field_ttl')->value;
              if($expiry_time > $created_time) {
                $expiry_time = date('d M Y' , (int)$app->get('field_expiry_date')->value);
              }
            }        
            $key = $app->get('field_app_key')->value ?? '';
            $apps[] = [
              'api_name' => $app->getTitle(),
              'description' => $description,
              'tag' => $app->get('field_tag')->value ?? '',
              'created' => date('d M Y' , (int)$created_time),
              'renewal' => $renewal_date ?? '-',
              'expiry' => $expiry_time ?? '-',    
              'api_key' => [
                'data' =>  Markup::create("<div class='kong-key'>$key</div><div class='pwd-toggle'></div><div class='pwd-copy'></div>"),
                'class' => 'api-keys',
              ],
              'update_key' => [
                'data' => $operation_link ?? '',
                'class' => 'app-operations',
              ],
            ]; 
          }        
        }
      }
    }
    else {
      $response =  \Drupal::service('zcs_kong.kong_gateway')->checkUserAccessGeneratekey(); 
      if($response != "error") {
        $header['operation'] = $this->t('Operations');
      }
     
      $group_type = 'partner';
      $group_storage = \Drupal::entityTypeManager()->getStorage('group');
      $query = $group_storage->getQuery()->condition('type', $group_type); 
      $group_ids = $query->accessCheck()->execute();
      $clients = $group_storage->loadMultiple($group_ids);
      foreach($clients as $group) {
        if (isset($group->get('field_consumer_id')->getValue()[0])) {
          $group_content_ids = \Drupal::entityQuery('group_relationship')
            ->condition('gid', $group->id())
            ->condition('type', 'partner-group_node-app')  // Replace 'group_node:app' with your actual group content type.
            ->accessCheck()->execute();  

          $group_contents = GroupRelationship::loadMultiple($group_content_ids);
          foreach ($group_contents as $group_content) {
            $app = $group_content->getEntity();     
            $key_id = $app->get('field_app_id')->value;
            $consumer_id = $app->get('field_consumer_id')->value;
            $description = $group->get('field_description')->getValue()[0]['value'];
            $app_status = $app->get('field_app_status')->value;
            if ($app_status == 'active') {
              $update_link = Link::createFromRoute('Edit', 'zcs_kong.edit_key', ['id' => $app->id()]);
              $delete_link = Link::createFromRoute('Delete', 'zcs_kong.delete_key', ['id' => $app->id()]);
              $operation_link = Markup::create($update_link->toString() . ' | ' . $delete_link->toString());
            }  
            $created_time = $app->get('created')->value;
            $updated_time = $app->get('changed')->value;
            $expiry_time = $app->get('field_expiry_date')->value;
            if ($updated_time > $created_time) {
               $renewal_date =  date('d M Y' , (int)$app->get('changed')->value);
            }
            if ($app->get('field_ttl')->value == 'never_expires') {
              $ttl = 'Never Expires';
              $expiry_time = '-';           
            }
            else {
              $ttl = $app->get('field_ttl')->value;
              if($expiry_time > $created_time) {
                $expiry_time = date('d M Y' , (int)$app->get('field_expiry_date')->value);
              }
            } 
            $key = $app->get('field_app_key')->value ?? '';
            $apps[] = [
              
              'api_name' => $app->getTitle(),
              'description' => $description,
              'tag' => $app->get('field_tag')->value ?? '',
              // 'ttl' => $app->get('field_ttl')->value ?? '',
              'created' => date('d M Y' , (int)$created_time),
              'renewal' => $renewal_date ?? '-',
              'expiry' => $expiry_time ?? '-',
              'api_key' => [
                'data' =>  Markup::create("<div class='kong-key'>$key</div><div class='pwd-toggle'></div><div class='pwd-copy'></div>"),
                'class' => 'api-keys',
              ], 
            ]; 

            // Condition to check if `update_key` should be added.
            if ($response != "error") {
               $apps[count($apps) - 1]['update_key'] = [
                'data' => $operation_link ?? '',
                'class' => 'app-operations',
               ]; 
            }
          }        
        }
      }     
    } 
      
  
    if (empty($apps)){
      $form['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => [],
        '#empty' => $this->t('No Data Found'),
      ];
      return $form;
    }
    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $apps,
      '#empty' => $this->t('No data available.'),
    ];
    $form['pager'] = [
      '#type' => 'pager',
    ];
  //  $form['#attached']['library'][] = 'zcs_kong/dialog';


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
   
  }
 
}
