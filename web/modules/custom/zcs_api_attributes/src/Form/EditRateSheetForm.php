<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\zcs_api_attributes\Service\RateSheetService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Access\AccessResult;

/**
 * Provides the edit rate sheet form.
 */
class EditRateSheetForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The currency list.
   *
   * @var array
   */
  protected $list;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The rate sheet service.
   *
   * @var \Drupal\zcs_api_attributes\Service\RateSheetService
   */
  protected $rateSheetService;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs an EditRateSheetForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\zcs_api_attributes\Service\RateSheetService $rate_sheet_service
   *   The rate sheet service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $date_formatter,
    RateSheetService $rate_sheet_service,
    MessengerInterface $messenger,
    Connection $database,
    AccountProxyInterface $current_user
  ) {
    $this->list = require __DIR__ . '/../../resources/currencies.php';
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->rateSheetService = $rate_sheet_service;
    $this->messenger = $messenger;
    $this->database = $database;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('zcs_api_attributes.rate_sheet_service'),
      $container->get('messenger'),
      $container->get('database'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_rate_sheet';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = 0) {

    $current_user_roles = \Drupal::currentUser()->getRoles();
    $rate_sheet_admin_roles = ['client_rate_sheet_admin', 'finance_admin'];
    $has_admin_rate_sheets_roles = !empty(array_intersect($rate_sheet_admin_roles, $current_user_roles));

    // Load the rate sheet.
    $rate_sheet = $this->database->select('rate_sheet', 'rs')
      ->fields('rs', ['id', 'name', 'currency', 'markup_retail', 'effective_date', 'created_by'])
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$rate_sheet) {
      $this->messenger->addError($this->t('Rate sheet not found.'));
      return $form;
    }

    if (!$has_admin_rate_sheets_roles) {
      $this->messenger->addError($this->t('You do not have permission to view this rate sheet.'));
      return $form;
    }

    // Check if rate sheet is cancelled or approved
    $status = $this->rateSheetService->getRateSheetStatus($id);
    $is_cancelled = strtolower($status) === 'cancelled';
    $is_approved = strtolower($status) === 'approved';

    if ($is_cancelled) {
      $this->messenger->addWarning($this->t('This rate sheet has been cancelled and cannot be edited.'));
    }

    if ($is_approved) {
      $this->messenger->addStatus($this->t('This rate sheet is approved. You can only add or remove clients.'));
    }

    // Check if there are unresolved reject comments
    $can_edit = !$is_cancelled && $this->rateSheetService->hasUnresolvedComments($id);
    
    // If approved, allow editing only for client management
    $can_edit_clients_only = $is_approved && !$is_cancelled;

    $config = $this->configFactory->get('zcs_custom.settings');
    $defaultCurrency = $config->get('currency') ?? 'en_US';
    $number = new \NumberFormatter($defaultCurrency, \NumberFormatter::CURRENCY);
    $symbol = $number->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

    // To fetch currencies.
    $currencies = [];
    foreach ($this->list as $list) {
      if (!empty($list['locale'])) {
        $currencies[$list['locale']] = $list['currency'] . ' (' . $list['alphabeticCode'] . ')';
      }
    }

    $currency_label = $rate_sheet->currency;
    foreach ($this->list as $currency) {
      if (!empty($currency['locale']) && $currency['locale'] === $rate_sheet->currency) {
        $currency_label = $currency['currency'] . ' (' . $currency['alphabeticCode'] . ')';
        break;
      }
    }

    $form['rate_sheet_id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    // Rate sheet name.
    $form['name'] = [
      '#type' => 'textfield',
      '#default_value' => $rate_sheet->name,
      '#description' => $this->t('The rate sheet name.'),
      '#required' => TRUE,
      '#disabled' => $can_edit_clients_only,
    ];

    // Currencies form select.
    $form['currencies'] = [
      '#type' => 'textfield',
      '#default_value' => $currency_label,
      '#disabled' => TRUE,
      '#weight' => 0,
    ];

    // Effective date.
    $form['attribute_date'] = [
      '#type' => 'date',
      '#default_value' => date('Y-m-d', $rate_sheet->effective_date),
      '#weight' => 1,
      '#attributes' => [
        'min' => date('Y-m-d'),
      ],
      '#disabled' => $can_edit_clients_only,
    ];

    // Markup retail.
    $form['retail_markup_percentage'] = [
      '#type' => 'number',
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
      '#default_value' => $rate_sheet->markup_retail,
      '#disabled' => $can_edit_clients_only,
    ];

    // Load rate sheet items.
    $rate_sheet_items = $this->database->select('rate_sheet_item', 'rsi')
      ->fields('rsi', ['id', 'attribute_name', 'tiered_calculation', 'api_attribute_id'])
      ->condition('rate_sheet_id', $id)
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $ranges_by_item_id = [];
    $rate_sheet_item_ids = array_column($rate_sheet_items, 'id');

    if (!empty($rate_sheet_item_ids)) {
      $rate_sheet_item_ranges = $this->database->select('rate_sheet_item_range', 'rsir')
        ->fields('rsir', [
          'id',
          'rate_sheet_item_id',
          'from_range',
          'to_range',
          'partial_range',
          'success_rate',
        ])
        ->condition('rate_sheet_item_id', $rate_sheet_item_ids, 'IN')
        ->orderBy('rate_sheet_item_id', 'ASC')
        ->orderBy('id', 'ASC')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);

      foreach ($rate_sheet_item_ranges as $range) {
        $ranges_by_item_id[$range['rate_sheet_item_id']][] = $range;
      }
    }

    $nids = [];

    foreach ($rate_sheet_items as $item) {
      $attribute_id = $item['api_attribute_id'];
      $nids[] = $attribute_id;
    }

    $form['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(',', $nids),
    ];

    $form['range_currency_symbol'] = [
      '#type' => 'hidden',
      '#value' => $symbol,
    ];

    $form['rate_sheet_item_ranges_payload'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#attributes' => [
        'data-rate-sheet-ranges-payload' => '',
      ],
    ];

    // Client selection fields
    $all_clients = $this->rateSheetService->getAllClients(TRUE);
    $selected_client_ids = $this->rateSheetService->getRateSheetClientIds($id);
    
    $form['clients_data'] = [
      '#type' => 'hidden',
      '#value' => json_encode($all_clients),
      '#attributes' => [
        'data-rate-sheet-clients-data' => '',
      ],
    ];

    $form['selected_clients'] = [
      '#type' => 'hidden',
      '#default_value' => json_encode($selected_client_ids),
      '#attributes' => [
        'data-rate-sheet-selected-clients' => '',
      ],
    ];

    // Locked clients are those already linked (cannot be unlinked)
    $form['locked_clients'] = [
      '#type' => 'hidden',
      '#default_value' => json_encode($selected_client_ids),
      '#attributes' => [
        'data-rate-sheet-locked-clients' => '',
      ],
    ];

    // Get detailed client information for the table
    $linked_clients = [];
    if (!empty($selected_client_ids)) {
      foreach ($selected_client_ids as $client_id) {
        $client_data = $this->rateSheetService->getClientDetails($client_id, $id);
        if ($client_data) {
          $linked_clients[] = $client_data;
        }
      }
    }

    $form['linked_clients_data'] = [
      '#type' => 'value',
      '#value' => $linked_clients,
    ];

    // Get reject comments.
    $reject_comments = $this->rateSheetService->getRateSheetRejectComments($id);

    $form['reject_comments'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($reject_comments as $comment) {
      $checkbox = [
        '#type' => 'checkbox',
        '#title' => $this->t('@email (@date): @comment', [
          '@email' => $comment['created_by'],
          '@date' => date('M d, Y H:i', $comment['created_date']),
          '@comment' => $comment['comment'],
        ]),
        '#default_value' => $comment['solved'],
      ];

      // If the comment is already solved, make it readonly
      if ($comment['solved']) {
        $checkbox['#disabled'] = TRUE;
        $checkbox['#attributes']['class'][] = 'reject-comment-solved';
        $checkbox['#attributes']['onclick'] = 'return false;';
        $checkbox['#attributes']['readonly'] = 'readonly';
      }

      $form['reject_comments'][$comment['id']] = $checkbox;
    }

    $form['reject_comments_data'] = [
      '#type' => 'value',
      '#value' => $reject_comments,
    ];

    $form['can_edit'] = [
      '#type' => 'value',
      '#value' => $can_edit,
    ];

    $form['is_cancelled'] = [
      '#type' => 'value',
      '#value' => $is_cancelled,
    ];

    $form['is_approved'] = [
      '#type' => 'value',
      '#value' => $is_approved,
    ];

    $form['can_edit_clients_only'] = [
      '#type' => 'value',
      '#value' => $can_edit_clients_only,
    ];

    // Always show actions container for the owner
    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-actions d-flex gap-2']],
    ];

    // Show submit button if:
    // 1. User can edit (has unresolved comments) and not cancelled, OR
    // 2. Rate sheet is approved (for client management only)
    if (($can_edit && !$is_cancelled) || $can_edit_clients_only) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Update Rate Sheet'),
      ];
    }

    // Always show cancel button if not already cancelled and not approved
    if (!$is_cancelled && !$is_approved) {
      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancel Rate Sheet'),
        '#submit' => ['::cancelRateSheet'],
        '#attributes' => [
          'class' => ['button', 'button--danger'],
          'data-rate-sheet-cancel-button' => '',
        ],
      ];
    }

    $form['#theme'] = 'create_rate_sheet';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-ranges';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-number-format';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-cancel';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-reject-comments';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-clients';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $can_edit_clients_only = $values['can_edit_clients_only'] ?? FALSE;

    // If in clients-only mode, skip most validation
    if ($can_edit_clients_only) {
      return;
    }

    // Validate rate sheet name.
    if (empty(trim($values['name']))) {
      $form_state->setErrorByName('name', $this->t('Rate sheet name is required.'));
    }

    // Validate effective date.
    $effective_date = $values['attribute_date'] ?? NULL;
    if (empty($effective_date)) {
      $form_state->setErrorByName('attribute_date', $this->t('Effective date is required.'));
    }
    else {
      $date_timestamp = strtotime($effective_date);
      $today = strtotime(date('Y-m-d'));
      if ($date_timestamp < $today) {
        $form_state->setErrorByName('attribute_date', $this->t('Effective date cannot be in the past.'));
      }
    }

    // Validate JSON payload only - ignore form fields
    $payload = $values['rate_sheet_item_ranges_payload'] ?? '';
    if (empty($payload)) {
      $form_state->setErrorByName('rate_sheet_item_ranges_payload', $this->t('Range data is required.'));
      return;
    }

    $decoded = json_decode($payload, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $form_state->setErrorByName('rate_sheet_item_ranges_payload', $this->t('Invalid range data format.'));
      return;
    }

    if (!is_array($decoded)) {
      $form_state->setErrorByName('rate_sheet_item_ranges_payload', $this->t('Range data must be an array.'));
      return;
    }

    // Validate range values from the payload (which contains unformatted numbers).
    foreach ($decoded as $attribute_id => $ranges) {
      if (!is_array($ranges)) {
        continue;
      }
      foreach ($ranges as $range_index => $range) {
        if (!is_array($range)) {
          continue;
        }

        // Remove any formatting from the values
        $from = isset($range['from_range']) ? str_replace(',', '', $range['from_range']) : NULL;
        $to = isset($range['to_range']) ? str_replace(',', '', $range['to_range']) : NULL;

        if (!is_numeric($from) || $from < 0) {
          $form_state->setError($form, $this->t('Invalid "from" range value for attribute @id.', ['@id' => $attribute_id]));
        }

        if ($to != -1 && (!is_numeric($to) || floatval($to) < floatval($from))) {
          $form_state->setError($form, $this->t('Invalid "to" range value for attribute @id. Must be greater than "from" or -1 for unbounded.', ['@id' => $attribute_id]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $rate_sheet_id = $values['rate_sheet_id'];
    $can_edit_clients_only = $values['can_edit_clients_only'] ?? FALSE;
    
    // Parse selected clients
    $selected_clients = [];
    $selected_clients_json = $values['selected_clients'] ?? '[]';
    if (!empty($selected_clients_json)) {
      $decoded = json_decode($selected_clients_json, TRUE);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $selected_clients = array_filter($decoded, 'is_numeric');
      }
    }

    $transaction = $this->database->startTransaction();

    try {
      // If in clients-only mode (approved status), only update client relationships
      if ($can_edit_clients_only) {
        // Update client relationships only
        $this->rateSheetService->updateClientRateSheets($rate_sheet_id, $selected_clients, $this->currentUser->id());

        // Log the action
        $this->database->insert('action_log')
          ->fields([
            'action_type',
            'entity_target_type',
            'entity_target_id',
            'created_by',
            'created_date',
            'log_data',
            'solved',
          ])
          ->values([
            'CLIENT_UPDATE',
            'RATE_SHEET',
            $rate_sheet_id,
            $this->currentUser->id(),
            \Drupal::time()->getRequestTime(),
            "User {$this->currentUser->id()} updated clients for approved rate sheet {$rate_sheet_id}.",
            0,
          ])
          ->execute();

        unset($transaction);

        $this->messenger->addStatus($this->t('Rate sheet clients have been updated successfully.'));
        $form_state->setRedirect('zcs_api_attributes.rate_sheet_list');
        return;
      }

      // Normal edit mode - update all fields
      $payload = $values['rate_sheet_item_ranges_payload'] ?? '';
      $submitted_ranges = [];

      // Parse the JSON payload.
      if (!empty($payload)) {
        $decoded_payload = json_decode($payload, TRUE);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_payload)) {
          $submitted_ranges = $decoded_payload;
        }
      }

      // Clean all numeric values from formatting
      $cleaned_ranges = [];
      foreach ($submitted_ranges as $attribute_id => $ranges) {
        $cleaned_ranges[$attribute_id] = [];
        foreach ($ranges as $range_index => $range) {
          $cleaned_ranges[$attribute_id][$range_index] = [
            'from_range' => str_replace(',', '', $range['from_range'] ?? '0'),
            'to_range' => str_replace(',', '', $range['to_range'] ?? '0'),
            'partial_range' => str_replace(',', '', $range['partial_range'] ?? '0'),
            'success_rate' => str_replace(',', '', $range['success_rate'] ?? '0'),
          ];
        }
      }

      // Update rate sheet.
      $this->database->update('rate_sheet')
        ->fields([
          'name' => $values['name'],
          'markup_retail' => $values['retail_markup_percentage'],
          'effective_date' => strtotime($values['attribute_date']),
        ])
        ->condition('id', $rate_sheet_id)
        ->execute();

      // Update client relationships
      $this->rateSheetService->updateClientRateSheets($rate_sheet_id, $selected_clients, $this->currentUser->id());

      // Update rate sheet item ranges.
      $rate_sheet_items = $this->database->select('rate_sheet_item', 'rsi')
        ->fields('rsi', ['id', 'api_attribute_id'])
        ->condition('rate_sheet_id', $rate_sheet_id)
        ->execute()
        ->fetchAllKeyed(1, 0);

      foreach ($cleaned_ranges as $attribute_id => $ranges) {
        if (!isset($rate_sheet_items[$attribute_id])) {
          continue;
        }

        $rate_sheet_item_id = $rate_sheet_items[$attribute_id];

        // Delete existing ranges for this item.
        $this->database->delete('rate_sheet_item_range')
          ->condition('rate_sheet_item_id', $rate_sheet_item_id)
          ->execute();

        // Update tiered calculation.
        $tiered_calculation = count($ranges) > 1 ? 1 : 0;
        $this->database->update('rate_sheet_item')
          ->fields(['tiered_calculation' => $tiered_calculation])
          ->condition('id', $rate_sheet_item_id)
          ->execute();

        // Insert new ranges.
        $range_items = array_filter($ranges, 'is_array');
        ksort($range_items, SORT_NUMERIC);
        $last_range_key = !empty($range_items) ? array_key_last($range_items) : NULL;

        foreach ($range_items as $range_index => $range_item) {
          if (!is_array($range_item)) {
            continue;
          }

          $to_range = ((string) $range_index === (string) $last_range_key) ? -1 : ($range_item['to_range'] ?? 0);

          $this->database->insert('rate_sheet_item_range')
            ->fields([
              'rate_sheet_item_id',
              'from_range',
              'to_range',
              'success_rate',
              'partial_range',
            ])
            ->values([
              $rate_sheet_item_id,
              $range_item['from_range'] ?? 0,
              $to_range,
              $range_item['success_rate'] ?? 0,
              $range_item['partial_range'] ?? 0,
            ])
            ->execute();
        }
      }

      // Update solved status for reject comments.
      // Only allow marking as solved, not unsolved (once solved, always solved).
      $reject_comments_values = $values['reject_comments'] ?? [];
      $reject_comments_data = $values['reject_comments_data'] ?? [];
      $any_comment_resolved = false;
      
      foreach ($reject_comments_values as $comment_id => $solved) {
        // Find the original comment data
        $original_comment = null;
        foreach ($reject_comments_data as $comment) {
          if ($comment['id'] == $comment_id) {
            $original_comment = $comment;
            break;
          }
        }
        
        // Only update if:
        // 1. The comment was not previously solved, OR
        // 2. We're marking it as solved (can't unsolve)
        if ($original_comment && (!$original_comment['solved'] || $solved)) {
          $this->database->update('action_log')
            ->fields(['solved' => $solved ? 1 : 0])
            ->condition('id', $comment_id)
            ->execute();
          
          // Track if we resolved a previously unresolved comment
          if (!$original_comment['solved'] && $solved) {
            $any_comment_resolved = true;
          }
        }
      }

      // If any comments were resolved, check if all comments are now resolved
      // and update the rate sheet status back to Pending
      if ($any_comment_resolved) {
        $still_has_unresolved = $this->rateSheetService->hasUnresolvedComments($rate_sheet_id);
        
        if (!$still_has_unresolved) {
          // All comments resolved - set status back to Pending
          $pending_status_id = $this->rateSheetService->getRateStatusId('Pending');
          
          if ($pending_status_id !== NULL) {
            $this->database->update('rate_sheet')
              ->fields(['rate_sheet_status_id' => $pending_status_id])
              ->condition('id', $rate_sheet_id)
              ->execute();

            // Insert a status record
            $this->database->insert('rate_sheet_status')
              ->fields([
                'rate_sheet_id' => $rate_sheet_id,
                'status_name' => 'Pending',
                'created_by' => $this->currentUser->id(),
                'date' => \Drupal::time()->getRequestTime(),
              ])
              ->execute();

            // Log the action
            $this->database->insert('action_log')
              ->fields([
                'action_type',
                'entity_target_type',
                'entity_target_id',
                'created_by',
                'created_date',
                'log_data',
                'solved',
              ])
              ->values([
                'STATUS_UPDATE',
                'RATE_SHEET',
                $rate_sheet_id,
                $this->currentUser->id(),
                \Drupal::time()->getRequestTime(),
                "User {$this->currentUser->id()} resolved all comments and rate sheet {$rate_sheet_id} returned to Pending status.",
                0,
              ])
              ->execute();
          }
        }
      }

      // Log the action.
      $this->database->insert('action_log')
        ->fields([
          'action_type',
          'entity_target_type',
          'entity_target_id',
          'created_by',
          'created_date',
          'log_data',
          'solved',
        ])
        ->values([
          'STATUS_UPDATE',
          'RATE_SHEET',
          $rate_sheet_id,
          $this->currentUser->id(),
          \Drupal::time()->getRequestTime(),
          "User {$this->currentUser->id()} updated rate sheet {$rate_sheet_id}.",
          0,
        ])
        ->execute();

      // Send email notification to approvers.
      $this->sendUpdateEmail($rate_sheet_id, $values);

      unset($transaction);

      $this->messenger->addStatus($this->t('Rate sheet "@name" has been updated successfully.', [
        '@name' => $values['name'],
      ]));

      $form_state->setRedirect('zcs_api_attributes.rate_sheet_list');
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }

      $this->messenger->addError($this->t('An error occurred while updating the rate sheet: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Form submission handler for cancelling a rate sheet.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function cancelRateSheet(array &$form, FormStateInterface $form_state) {
    $rate_sheet_id = $form_state->getValue('rate_sheet_id');

    try {
      $this->rateSheetService->cancelRateSheet($rate_sheet_id, $this->currentUser->id());

      $this->messenger->addStatus($this->t('Rate sheet has been cancelled successfully. This action cannot be undone.'));

      $form_state->setRedirect('zcs_api_attributes.rate_sheet_list');
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('An error occurred while cancelling the rate sheet: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Sends an email notification to approvers about rate sheet update.
   *
   * @param int $rate_sheet_id
   *   The rate sheet ID.
   * @param array $values
   *   The form values.
   */
  protected function sendUpdateEmail($rate_sheet_id, array $values) {
    try {
      $userMails = [];
      $users = $this->entityTypeManager->getStorage('user')->loadByProperties(
        ['roles' => [
          'financial_rate_sheet_approval_level_1',
          'financial_rate_sheet_approval_level_2',
        ], 'status' => 1]
      );
      foreach ($users as $user) {
        if ($user) {
          $userMails[] = $user->mail->value;
        }
      }

      if (empty($userMails)) {
        return;
      }

      $mailManager = \Drupal::service('plugin.manager.mail');

      $modulePath = \Drupal::service('extension.path.resolver')->getPath('module', 'zcs_api_attributes');
      $path = $modulePath . '/templates/rate_sheet_update_mail.html.twig';

      // Check if template exists, otherwise use approval template.
      if (!file_exists($path)) {
        $path = $modulePath . '/templates/rate_sheet_approval_mail.html.twig';
      }

      $rendered = \Drupal::service('twig')->load($path)->render([
        'user' => $this->entityTypeManager->getStorage('user')->load($this->currentUser->id())->mail->value,
        'effective_date' => $values['attribute_date'],
        'rate_sheet_name' => $values['name'],
        'approval' => Link::createFromRoute('Review', 'zcs_api_attributes.rate_sheet_list')->toString(),
        'site_name' => $this->config('system.site')->get('name'),
      ]);

      $params['message'] = Markup::create(nl2br($rendered));
      $params['subject'] = $this->t('Rate Sheet "@name" has been updated', [
        '@name' => $values['name'],
      ]);
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = TRUE;

      foreach ($userMails as $mail) {
        $mailManager->mail('zcs_api_attributes', 'rate_sheet_update', $mail, $langcode, $params, NULL, $send);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('zcs_api_attributes')->error('Error sending update email: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
