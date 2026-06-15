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

    $groups = Group::loadMultiple();
    $kong_service = \Drupal::service('zcs_kong.kong_gateway');
    if (!empty($groups)) {
      foreach ($groups as $group) {
        $status = 'Not in Sync';
        $consumer_id = $group->get('field_consumer_id')->value ?? '';
        if (!empty($consumer_id)) {
              $contact_email = $kong_service->getContactEmailUsingConsumerId($consumer_id);
               if (!empty($contact_email)) {
                 $response = $kong_service->getConsumer($contact_email);
                       if ($response !== 'error') {
                         $body = Json::decode($response->getBody()->getContents());
                             if (!empty($body['data'])) {
                                $status = [
                                  'data' => [
                                    '#type' => 'html_tag',
                                    '#tag' => 'span',
                                    '#value' => $this->t('Synced'),
                                    '#attributes' => [
                                      'class' => ['badge', 'badge--status'],
                                      'style' => 'color:green;font-weight:bold;',
                                    ],
                                  ],
                                ];
                            }
                       }
               }
        }
        $rows[$group->id()] = [
          'gid'   => $group->id(),
          'name'  => $group->label(),
          'type'  => $group->bundle(),
          'sync_status' => $status,
        ];
      }
    }

    $form['groups'] = [
      '#type'    => 'tableselect',
      '#header'  => [
        'gid'  => $this->t('Group ID'),
        'name' => $this->t('Group Name'),
        'type' => $this->t('Group Type'),
        'sync_status' => $this->t('Sync Status'),
      ],
      '#options' => $rows,
      '#empty'   => $this->t('No groups available.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Run Batch Process'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
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
      'title'            => $this->t('Processing Groups'),
      'operations'       => $operations,
      'finished'         => [static::class, 'batchFinished'],
      'init_message'     => $this->t('Starting batch process...'),
      'progress_message' => $this->t('Processed @current out of @total'),
      'error_message'    => $this->t('Batch process failed.'),
    ];

    batch_set($batch);
  }

  /**
   * Batch operation callback.
   *
   * @param int   $gid     Group ID to process.
   * @param array $context Batch context passed by reference.
   */
  public static function processGroup($gid, array &$context) {

    // Initialise sandbox on first run (best practice).
    if (empty($context['sandbox'])) {
      $context['sandbox'] = [];
    }

    // ------------------------------------------------------------------ //
    // 1. Load group.
    // ------------------------------------------------------------------ //
    $group = Group::load($gid);
    if (!$group) {
      \Drupal::logger('zcs_kong')->warning(
        'Group @gid could not be loaded; skipping.', ['@gid' => $gid]
      );
      return;
    }

    // ------------------------------------------------------------------ //
    // 2. Guard: make sure the consumer ID field is not empty.
    // ------------------------------------------------------------------ //
    $consumer_id_field = $group->get('field_consumer_id')->getValue();
    if (empty($consumer_id_field[0]['value'])) {
      \Drupal::logger('zcs_kong')->warning(
        'Group @gid has no field_consumer_id; skipping.', ['@gid' => $gid]
      );
      return;
    }
    $consumer_id = $consumer_id_field[0]['value'];


    // ------------------------------------------------------------------ //
    // 3. Collect eligible app nodes (field_gateway is empty).
    // ------------------------------------------------------------------ //
    $relationships = \Drupal::entityTypeManager()
      ->getStorage('group_relationship')
      ->loadByProperties(['gid' => $gid]);

    $app_nodes = [];
    foreach ($relationships as $relationship) {
      $entity = $relationship->getEntity();
      if (
        $entity &&
        $entity->getEntityTypeId() === 'node' &&
        $entity->bundle() === 'app' &&
        $entity->get('field_gateway')->isEmpty()
      ) {
        $app_nodes[] = $entity;
        \Drupal::logger('zcs_kong')->notice(
          'Eligible App Node: @title (NID: @nid)',
          ['@title' => $entity->label(), '@nid' => $entity->id()]
        );
      }
    }

    // Nothing to sync for this group.
    if (empty($app_nodes)) {
      $context['results'][] = $group->label();
      $context['message']   = t('Processed group: @group (no eligible apps)', ['@group' => $group->label()]);
      return;
    }

    // ------------------------------------------------------------------ //
    // 4. Resolve or create Kong consumer.
    // ------------------------------------------------------------------ //
    $kong_service    = \Drupal::service('zcs_kong.kong_gateway');
    $contact_email   = $kong_service->getContactEmailUsingConsumerId($consumer_id);
    $user_name       = $kong_service->getContactNameUsingConsumerId($consumer_id);

    if (empty($contact_email) || empty($user_name)) {
      \Drupal::logger('zcs_kong')->error(
        'Could not resolve contact info for consumer @id in group @gid; skipping.',
        ['@id' => $consumer_id, '@gid' => $gid]
      );
      return;
    }

    $consumer_id_to_use  = NULL;
    $get_consumer_response = $kong_service->getConsumer($contact_email);

    if ($get_consumer_response === 'error') {
      \Drupal::logger('zcs_kong')->error(
        'getConsumer() failed for email @email; skipping group @gid.',
        ['@email' => $contact_email, '@gid' => $gid]
      );
      return;
    }

    $kong_response = $get_consumer_response->getBody()->getContents();
    $response      = Json::decode($kong_response);
 

    if (!empty($response['data'])) {
      // Consumer already exists in Kong.
      $consumer_id_to_use = $response['data'][0]['id'];
    }
    else {
      // Create a new consumer.
      $create_consumer_response = $kong_service->createConsumerSync($user_name, $consumer_id, $contact_email);

      if (
        $create_consumer_response !== 'error' &&
        $create_consumer_response->getStatusCode() === 201   // FIX: int, not string
      ) {
        $create_consumer_body          = $create_consumer_response->getBody()->getContents();
        $kong_create_consumer_response = Json::decode($create_consumer_body);
        $consumer_id_to_use            = $kong_create_consumer_response['id'] ?? NULL;
      }
      else {
        \Drupal::logger('zcs_kong')->error(
          'createConsumer() failed for @email; skipping group @gid.',
          ['@email' => $contact_email, '@gid' => $gid]
        );
      }
    }

    if (empty($consumer_id_to_use)) {
      return;
    }

    // ------------------------------------------------------------------ //
    // 5. Sync each eligible app node.
    // ------------------------------------------------------------------ //
    foreach ($app_nodes as $node) {

      // FIX: redirect_uris must be an array for Kong.
      $redirect_uri_value = $node->field_redirect_url->value;
      $redirect_uris      = !empty($redirect_uri_value)
        ? (array) $redirect_uri_value
        : [];

      $kong_app = [
        'name'          => $node->getTitle(),
        'client_id'     => $node->field_client_id->value,
        'client_secret' => $node->field_client_secret->value,
        'created_at'    => time(),
        'tags'          => !empty($node->field_tag->value) ? [$node->field_tag->value] : [],
        'redirect_uris' => $redirect_uris,  // FIX: always an array
      ];

      $sync_apps_response = $kong_service->syncAppByNewConsumerId($kong_app, $user_name);

      if ($sync_apps_response === 'error') {
        \Drupal::logger('zcs_kong')->error(
          'syncAppByNewConsumerId() failed for app @nid.', ['@nid' => $node->id()]
        );
        continue;
      }

      // FIX: compare status code as integer.
      if ($sync_apps_response->getStatusCode() !== 201) {
        \Drupal::logger('zcs_kong')->warning(
          'Unexpected status @code syncing app @nid.',
          ['@code' => $sync_apps_response->getStatusCode(), '@nid' => $node->id()]
        );
        continue;
      }

      $response_body = (string) $sync_apps_response->getBody();


      $jwt_id = $node->field_jwt->value;
      $jwt_key = $node->field_jwt_key->value;
      $jwt_secret = 'strongpassword';
      //$consumer_username = $node->getTitle();
      $tags = !empty($node->field_tag->value) ? [$node->field_tag->value] : [];

      $create_jwt_token_response = $kong_service->createJwtTokenSync($user_name, $jwt_id, $jwt_key, $jwt_secret, $tags);

      if (empty($create_jwt_token_response)) {
        \Drupal::logger('zcs_kong')->error(
          'createJwtToken() returned empty for app @nid.', ['@nid' => $node->id()]
        );
        continue;
      }

      if($create_jwt_token_response != 'error') {
        \Drupal::logger('zcs_kong')->error(
                'createJwtToken() returned empty for app @nid.', ['@nid' => $node->id()]
              );
        continue;
      }

      // FIX: compare status code as integer.
      if ($create_jwt_token_response->getStatusCode() !== 201) {
        \Drupal::logger('zcs_kong')->warning(
          'Unexpected JWT status @code for app @nid.',
          ['@code' => $create_jwt_token_response->getStatusCode(), '@nid' => $node->id()]
        );
        continue;
      }

      $jwt_response_body = (string) $create_jwt_token_response->getBody();

      // FIX: log the result of updateSyncApp so failures are not silent.
     // $update_result = $kong_service->updateSyncApp($node, $consumer_id_to_use, $jwt_response_body, $group);

      if (empty($update_result)) {
        \Drupal::logger('zcs_kong')->error(
          'updateSyncApp() failed for app @nid in group @gid.',
          ['@nid' => $node->id(), '@gid' => $gid]
        );
      }
      else {
        \Drupal::logger('zcs_kong')->notice(
          'Successfully synced app @nid in group @gid.',
          ['@nid' => $node->id(), '@gid' => $gid]
        );
      }
    }

    // ------------------------------------------------------------------ //
    // 6. Mark group as processed in batch context.
    // ------------------------------------------------------------------ //
    $context['results'][] = $group->label();
    $context['message']   = t(
      'Processed group: @group',
      ['@group' => $group->label()]
    );
  }

  /**
   * Batch finished callback.
   *
   * @param bool  $success    Whether the batch completed without errors.
   * @param array $results    Accumulated results from each operation.
   * @param array $operations Unprocessed operations (if any).
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