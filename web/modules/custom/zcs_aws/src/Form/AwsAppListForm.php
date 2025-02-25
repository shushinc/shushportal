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
use Drupal\Component\Serialization\Json;
use Drupal\Core\Render\Markup;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupRelationship;
use Drupal\group\Entity\GroupContentType;
use Drupal\group\Entity\GroupMembership;

/**
 * Provides a User Management list form.
 */
final class AwsAppListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_aws_app_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $header = [
      'api_name' => $this->t('Client Name'),
      'description' => $this->t('Description'),
      'app' => $this->t('App name'),
      'created' => $this->t('Created'),
      'client_id' => $this->t('Client ID'),
      'client_secret' => $this->t('Client Secret'),
      'status' => $this->t('Status'),
    ];


    if (\Drupal::currentUser()->hasRole('carrier_admin') || \Drupal::currentUser()->hasRole('administrator')) {
      $header['operation'] = $this->t('Operations');
      $group_type = 'partner';
      $group_storage = \Drupal::entityTypeManager()->getStorage('group');
      $query = $group_storage->getQuery()->condition('type', $group_type);
      $group_ids = $query->accessCheck()->execute();
      $clients = $group_storage->loadMultiple($group_ids);
      foreach($clients as $group) {
          $group_content_ids = \Drupal::entityQuery('group_relationship')
            ->condition('gid', $group->id())
            ->condition('type', 'partner-group_node-app')  // Replace 'group_node:app' with your actual group content type.
            ->accessCheck()->execute();
          $group_contents = GroupRelationship::loadMultiple($group_content_ids);
          foreach ($group_contents as $group_content) {
            $app = $group_content->getEntity();
            $gateway_name = $app->get('field_gateway')->value;
            if ($gateway_name != 'aws') {
              continue;
            }
            $description = Markup::create($group->get('field_description')->getValue()[0]['value']);
            $app_status = $app->get('field_app_status')->value;
            if ($app_status == 'active') {
              $url = Url::fromRoute('zcs_aws.edit_key', ['id' => $app->id()]);
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
              $delete_link = Link::createFromRoute('Delete', 'zcs_aws.delete_key', ['id' => $app->id()]);
              $operation_link = Markup::create($update_link->toString() . ' | ' . $delete_link->toString());
            }
            else {
              $operation_link = '';
            }

            $created_time = $app->get('created')->value;
            $client_id = $app->get('field_client_id')->value ?? '';
            $secret_key = $app->get('field_client_secret')->value ?? '';
            $app_status = $app->get('field_app_status')->value;
            if ($app_status == 'active') {
              $app_status = Markup::create("<div class='key-active'>Active</div>");
            }
            else {
              $app_status = Markup::create("<div class='key-inactive'>Inactive</div>");
            }
            $apps[] = [
              'api_name' => $app->getTitle(),
              'description' => $description,
              'app' => $app->get('field_tag')->value ?? '',
              'created' => date('d M Y' , (int)$created_time),
              'client_id' => [
                'data' =>  Markup::create("<div class='client-key'>$client_id</div><div class='pwd-toggle'></div><div class='client-password'></div>"),
                'class' => 'api-keys',
              ],
              'client_secret' => [
                'data' =>  Markup::create("<div class='secret-key'>$secret_key</div><div class='pwd-toggle'></div><div class='secret-password'></div>"),
                'class' => 'api-keys',
              ],
              'status' => $app_status,
              'update_key' => [
                'data' => $operation_link ?? '',
                'class' => 'app-operations',
              ],
            ];
          }
      }
    }
    else {
      $response =  \Drupal::service('zcs_aws.aws_gateway')->checkUserAccessGeneratekey();
      if ($response !== "error") {
        $header['operation'] = $this->t('Operations');
      }

      $group_type = 'partner';
      $group_storage = \Drupal::entityTypeManager()->getStorage('group');
      $query = $group_storage->getQuery()->condition('type', $group_type);
      $group_ids = $query->accessCheck()->execute();
      $clients = $group_storage->loadMultiple($group_ids);
      foreach($clients as $group) {
          $group_content_ids = \Drupal::entityQuery('group_relationship')
            ->condition('gid', $group->id())
            ->condition('type', 'partner-group_node-app')  // Replace 'group_node:app' with your actual group content type.
            ->accessCheck()->execute();
          $group_contents = GroupRelationship::loadMultiple($group_content_ids);
          foreach ($group_contents as $group_content) {
            $app = $group_content->getEntity();
            $gateway_name = $app->get('field_gateway')->value;
            if ($gateway_name != 'aws') {
              continue;
            }
            $description = $group->get('field_description')->getValue()[0]['value'];
            $app_status = $app->get('field_app_status')->value;
            if ($app_status == 'active') {
              $update_link = Link::createFromRoute('Edit', 'zcs_aws.edit_key', ['id' => $app->id()]);
              $delete_link = Link::createFromRoute('Delete', 'zcs_aws.delete_key', ['id' => $app->id()]);
              $operation_link = Markup::create($update_link->toString() . ' | ' . $delete_link->toString());
            }
            else {
              $operation_link = '';
            }
            $created_time = $app->get('created')->value;
            $client_id = $app->get('field_client_id')->value ?? '';
            $secret_key = $app->get('field_client_secret')->value ?? '';

            $app_status = $app->get('field_app_status')->value;
            if ($app_status == 'active') {
              $app_status = Markup::create("<div class='key-active'>Active</div>");
            }
            else {
              $app_status = Markup::create("<div class='key-inactive'>Inactive</div>");
            }
            $apps[] = [
              'api_name' => $app->getTitle(),
              'description' => $description,
              'tag' => $app->get('field_tag')->value ?? '',
              'created' => date('d M Y' , (int)$created_time),
              'client_id' => [
                'data' =>  Markup::create("<div class='client-key''>$client_id</div><div class='pwd-toggle'></div><div class='client-password'></div>"),
                'class' => 'api-keys',
              ],
              'client_secret' => [
                'data' =>  Markup::create("<div class='secret-key'>$secret_key</div><div class='pwd-toggle'></div><div class='secret-password'></div>"),
                'class' => 'api-keys',
              ],
              'status' => $app_status,
            ];

            // Condition to check if `update_key` should be added.
            if ($response !== "error") {
               $apps[count($apps) - 1]['update_key'] = [
                'data' => $operation_link ?? '',
                'class' => 'app-operations',
               ];
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
