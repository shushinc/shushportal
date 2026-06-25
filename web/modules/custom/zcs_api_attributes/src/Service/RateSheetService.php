<?php

namespace Drupal\zcs_api_attributes\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Service for managing rate sheets.
 */
class RateSheetService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a RateSheetService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    LoggerInterface $logger
  ) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->logger = $logger;
  }

  /**
     * Returns approvers HTML for a Rate Sheet.
     *
     * Output format:
     * - Always 2 lines (2 approvers)
     * - Shows email when action already taken
     * - Shows status label (approved, denied, pending)
     *
     * Rules:
     * - No records or only 1 pending > both pending
     * - One decision + one pending > show decision + pending
     * - Two equal decisions > show both
     * - Approved + Denied > both shown
     * - Rejected statuses with resolved comments are filtered out
     *
     * @param int $rate_sheet_id
     *   The Rate Sheet ID.
     *
     * @return string
     *   HTML markup.
     */
  public function getRateSheetApprovers(int $rate_sheet_id): string {

    $query = $this->database->select('rate_sheet_status', 'rss')
      ->fields('rss', ['id', 'status_name', 'created_by', 'action_log_id'])
      ->condition('rate_sheet_id', $rate_sheet_id)
      ->orderBy('date', 'ASC');

    $rows = $query->execute()->fetchAll();

    if (empty($rows)) {
      return '<span class="pending">Pending</span><br><span class="pending">Pending</span>';
    }

    // Filter out rejected statuses where the associated comment has been resolved
    $filtered_rows = [];
    foreach ($rows as $row) {
      // If it's a rejected status with an action_log_id, check if the comment is resolved
      if (strtoupper($row->status_name) === 'REJECTED' && !empty($row->action_log_id)) {
        // Check if the associated action_log comment is resolved
        $comment_solved = $this->database->select('action_log', 'al')
          ->fields('al', ['solved'])
          ->condition('id', $row->action_log_id)
          ->execute()
          ->fetchField();
        
        // Skip this rejected status if the comment has been resolved
        if ($comment_solved == 1) {
          continue;
        }
      }
      
      $filtered_rows[] = $row;
    }

    // Use filtered rows from here on
    $rows = $filtered_rows;

    if (empty($rows)) {
      return '<span class="pending">Pending</span><br><span class="pending">Pending</span>';
    }

    // Only creation record
    if (count($rows) === 1 && strtoupper($rows[0]->status_name) === 'PENDING') {
      return '<span class="pending">Pending</span><br><span class="pending">Pending</span>';
    }

    // Remove initial PENDING
    if (strtoupper($rows[0]->status_name) === 'PENDING') {
      array_shift($rows);
    }

    // Last two relevant
    $rows = array_slice(array_reverse($rows), 0, 2);

    $statuses = [];

    foreach ($rows as $row) {
      $statuses[] = [
        'status' => strtoupper($row->status_name),
        'uid' => $row->created_by,
      ];
    }

    while (count($statuses) < 2) {
      $statuses[] = [
        'status' => 'PENDING',
        'uid' => NULL,
      ];
    }

    $output = [];

    foreach ($statuses as $item) {
      $status = $item['status'];
      $uid = $item['uid'];

      $email = '';

      if ($status !== 'PENDING' && !empty($uid)) {
        $user = \Drupal\user\Entity\User::load($uid);
        if ($user) {
          $email = $user->getEmail();
        }
      }

      $line = '';

      if ($email) {
        $line .= $email;
      }

      $class = strtolower($status);

      if ($status === 'PENDING') {
        $line .= '<span class="pending">Pending</span>';
      }
      else {
        $line .= '<span class="' . $class . '"></span>';
      }

      $output[] = $line;
    }

    return implode('<br>', $output);
  }

  /**
   * Returns the current Rate Sheet status based on rate_sheet_status_id.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   *
   * @return string
   *   The status name (e.g., 'Pending', 'Approved', 'Draft', 'Rejected').
   */
  public function getRateSheetStatus(int $rate_sheet_id): string {
    // Get the rate_sheet_status_id from the rate_sheet table.
    $status_id = $this->database->select('rate_sheet', 'rs')
      ->fields('rs', ['rate_sheet_status_id'])
      ->condition('id', $rate_sheet_id)
      ->execute()
      ->fetchField();

    // If no status_id is set, return 'Pending' as default.
    if (!$status_id) {
      return 'Pending';
    }

    // Look up the status name from the lookup table.
    $status_name = $this->database->select('rate_sheet_status_lookup', 'rssl')
      ->fields('rssl', ['status_name'])
      ->condition('id', $status_id)
      ->execute()
      ->fetchField();

    // Return the status name, or 'Pending' if not found.
    return $status_name ?: 'Pending';
  }

  /**
   * Gets the rate sheet status ID by status name.
   *
   * @param string $status_name
   *   The status name (e.g., 'Pending', 'Approved', 'Draft', 'Rejected').
   *
   * @return int|null
   *   The status ID or NULL if not found.
   */
  public function getRateStatusId(string $status_name): ?int {
    $status_id = $this->database->select('rate_sheet_status_lookup', 'rssl')
      ->fields('rssl', ['id'])
      ->condition('status_name', $status_name)
      ->execute()
      ->fetchField();

    return $status_id !== FALSE ? (int) $status_id : NULL;
  }

  /**
   * Checks if a rate sheet is approved.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   *
   * @return bool
   *   TRUE if the rate sheet is approved, FALSE otherwise.
   */
  public function isRateSheetApproved(int $rate_sheet_id): bool {
    $status = $this->getRateSheetStatus($rate_sheet_id);
    return strtolower($status) === 'approved';
  }

  /**
   * Gets all active clients (groups of type 'partner').
   *
   * @return array
   *   Array of clients with id and label.
   */
  public function getAllClients(): array {
    $query = $this->database->select('groups_field_data', 'gfd')
      ->fields('gfd', ['id', 'label'])
      ->condition('type', 'partner')
      ->condition('status', 1)
      ->orderBy('label', 'ASC');

    $results = $query->execute()->fetchAll();

    $clients = [];
    foreach ($results as $result) {
      $clients[] = [
        'id' => $result->id,
        'label' => $result->label,
      ];
    }

    return $clients;
  }

  /**
   * Creates client rate sheet relationships.
   *
   * @param int $rate_sheet_id
   *   The rate sheet ID.
   * @param array $client_ids
   *   Array of client IDs.
   * @param int $user_id
   *   The user ID creating the relationships.
   *
   * @throws \Exception
   */
  public function createClientRateSheets(int $rate_sheet_id, array $client_ids, int $user_id) {
    if (empty($client_ids)) {
      return;
    }

    $transaction = $this->database->startTransaction();

    try {
      $pending_status_id = $this->getRateStatusId('Pending');
      $current_time = \Drupal::time()->getRequestTime();

      foreach ($client_ids as $client_id) {
        // Get client name
        $client_name = $this->database->select('groups_field_data', 'gfd')
          ->fields('gfd', ['label'])
          ->condition('id', $client_id)
          ->execute()
          ->fetchField();

        // Insert client rate sheet relationship
        $this->database->insert('client_rate_sheet')
          ->fields([
            'rate_sheet_id' => $rate_sheet_id,
            'client_id' => $client_id,
            'created_by' => $user_id,
            'created_date' => $current_time,
            'active' => 1,
            'rate_sheet_client_status_id' => $pending_status_id,
          ])
          ->execute();

        // Log the action
        $this->database->insert('action_log')
          ->fields([
            'action_type' => 'STATUS_UPDATE_PENDING',
            'entity_target_type' => 'CLIENT_RATE_SHEET',
            'entity_target_id' => $rate_sheet_id,
            'created_by' => $user_id,
            'created_date' => $current_time,
            'log_data' => "User {$user_id} created a new relationship between {$client_name} and rate sheet {$rate_sheet_id}",
            'solved' => 0,
          ])
          ->execute();
      }

      unset($transaction);
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      $this->logger->error('Failed to create client rate sheets: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Gets client IDs associated with a rate sheet.
   *
   * @param int $rate_sheet_id
   *   The rate sheet ID.
   *
   * @return array
   *   Array of client IDs.
   */
  public function getRateSheetClientIds(int $rate_sheet_id): array {
    $query = $this->database->select('client_rate_sheet', 'crs')
      ->fields('crs', ['client_id'])
      ->condition('rate_sheet_id', $rate_sheet_id);

    return $query->execute()->fetchCol();
  }

  /**
   * Gets detailed information about a client and their rate sheet linking status.
   *
   * @param int $client_id
   *   The client (group) ID.
   * @param int $rate_sheet_id
   *   The rate sheet ID.
   *
   * @return array|null
   *   Array with client details or NULL if not found.
   */
  public function getClientDetails(int $client_id, int $rate_sheet_id): ?array {
    // Get client basic info from groups_field_data
    $client = $this->database->select('groups_field_data', 'gfd')
      ->fields('gfd', ['id', 'label'])
      ->condition('id', $client_id)
      ->condition('type', 'partner')
      ->execute()
      ->fetchObject();

    if (!$client) {
      return NULL;
    }

    // Get client type from group__field_partner_type
    $type_value = $this->database->select('group__field_partner_type', 'gfpt')
      ->fields('gfpt', ['field_partner_type_value'])
      ->condition('entity_id', $client_id)
      ->execute()
      ->fetchField();

    // Get client industry from group__field_industry
    $industry_value = $this->database->select('group__field_industry', 'gfi')
      ->fields('gfi', ['field_industry_value'])
      ->condition('entity_id', $client_id)
      ->execute()
      ->fetchField();

    // Get linking status from client_rate_sheet
    $linking = $this->database->select('client_rate_sheet', 'crs')
      ->fields('crs', ['rate_sheet_client_status_id'])
      ->condition('rate_sheet_id', $rate_sheet_id)
      ->condition('client_id', $client_id)
      ->execute()
      ->fetchObject();

    $status_name = 'Unknown';
    if ($linking && $linking->rate_sheet_client_status_id) {
      $status_name = $this->database->select('rate_sheet_status_lookup', 'rssl')
        ->fields('rssl', ['status_name'])
        ->condition('id', $linking->rate_sheet_client_status_id)
        ->execute()
        ->fetchField();
    }

    // Get field labels from field config
    $type_label = $type_value ?: 'N/A';
    $industry_label = $industry_value ?: 'N/A';

    // Try to get human-readable labels from field config
    try {
      $field_config_type = \Drupal\field\Entity\FieldConfig::load('group.partner.field_partner_type');
      if ($field_config_type && $type_value) {
        $allowed_values = $field_config_type->getSetting('allowed_values');
        $type_label = $allowed_values[$type_value] ?? $type_value;
      }

      $field_config_industry = \Drupal\field\Entity\FieldConfig::load('group.partner.field_industry');
      if ($field_config_industry && $industry_value) {
        $allowed_values = $field_config_industry->getSetting('allowed_values');
        $industry_label = $allowed_values[$industry_value] ?? $industry_value;
      }
    }
    catch (\Exception $e) {
      // Use raw values if field config can't be loaded
    }

    return [
      'id' => $client->id,
      'name' => $client->label,
      'type' => $type_label,
      'industry' => $industry_label,
      'status' => $status_name ?: 'Pending',
    ];
  }

  /**
   * Updates client rate sheet relationships.
   *
   * Business rule: Once a client is linked to a rate sheet, it cannot be unlinked.
   * Only new clients can be added.
   *
   * @param int $rate_sheet_id
   *   The rate sheet ID.
   * @param array $new_client_ids
   *   Array of new client IDs.
   * @param int $user_id
   *   The user ID making the update.
   *
   * @throws \Exception
   */
  public function updateClientRateSheets(int $rate_sheet_id, array $new_client_ids, int $user_id) {
    $transaction = $this->database->startTransaction();

    try {
      $existing_client_ids = $this->getRateSheetClientIds($rate_sheet_id);
      
      // Business rule: Prevent unlinking existing clients
      // Check if any existing clients are being removed
      $clients_to_remove = array_diff($existing_client_ids, $new_client_ids);
      
      if (!empty($clients_to_remove)) {
        // Log the attempt
        $this->logger->warning('Attempt to unlink clients from rate sheet @rate_sheet_id by user @user_id. Clients: @clients', [
          '@rate_sheet_id' => $rate_sheet_id,
          '@user_id' => $user_id,
          '@clients' => implode(', ', $clients_to_remove),
        ]);
        
        throw new \Exception('Cannot unlink clients from a rate sheet. Once linked, clients cannot be removed.');
      }
      
      // Find clients to add (only new ones)
      $clients_to_add = array_diff($new_client_ids, $existing_client_ids);

      // Add new relationships
      if (!empty($clients_to_add)) {
        $this->createClientRateSheets($rate_sheet_id, $clients_to_add, $user_id);
      }

      unset($transaction);
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      $this->logger->error('Failed to update client rate sheets: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Creates a new rate sheet with items and ranges.
   *
   * @param array $data
   *   The rate sheet data containing:
   *   - name: The rate sheet name.
   *   - currency: The currency locale.
   *   - markup_retail: The retail markup percentage.
   *   - effective_date: The effective date timestamp.
   *   - attribute_ids: Array of attribute node IDs.
   *   - ranges: Array of ranges keyed by attribute ID.
   *   - client_ids: (optional) Array of client IDs.
   *
   * @return int|false
   *   The new rate sheet ID or FALSE on failure.
   *
   * @throws \Exception
   */
  public function createRateSheet(array $data) {
    $transaction = $this->database->startTransaction();

    try {
      // Fetch the "Pending" status ID from the lookup table.
      $pending_status_id = $this->getRateStatusId('Pending');

      // Insert the rate sheet.
      $new_rate_sheet_id = $this->database->insert('rate_sheet')
        ->fields([
          'name',
          'currency',
          'created_by',
          'markup_retail',
          'created_date',
          'effective_date',
          'rate_sheet_status_id'
        ])
        ->values([
          $data['name'],
          $data['currency'],
          $this->currentUser->id(),
          $data['markup_retail'],
          \Drupal::time()->getRequestTime(),
          $data['effective_date'],
          $pending_status_id,
        ])
        ->execute();

      // Create the default status.
      $this->database->insert('rate_sheet_status')
        ->fields([
          'rate_sheet_id',
          'status_name',
          'date',
          'created_by',
        ])
        ->values([
          $new_rate_sheet_id,
          'Pending',
          \Drupal::time()->getRequestTime(),
          $this->currentUser->id(),
        ])
        ->execute();

      // Log the action.
      $this->database->insert('action_log')
        ->fields([
          'action_type',
          'entity_target_type',
          'entity_target_id',
          'created_by',
          'created_date',
          'log_data',
        ])
        ->values([
          'CREATING',
          'RATE_SHEET',
          $new_rate_sheet_id,
          $this->currentUser->id(),
          \Drupal::time()->getRequestTime(),
          '',
        ])
        ->execute();

      // Create rate sheet items and ranges.
      foreach ($data['attribute_ids'] as $nid) {
        $range_items = $data['ranges'][$nid] ?? [];

        if (!is_array($range_items)) {
          continue;
        }

        $range_items = array_filter($range_items, 'is_array');
        ksort($range_items, SORT_NUMERIC);

        $tiered_calculation = count($range_items) > 1 ? 1 : 0;

        $node = Node::load($nid);
        if (!$node) {
          $this->logger->warning('Rate sheet creation: API attribute node @nid not found', ['@nid' => $nid]);
          continue;
        }

        $rate_sheet_item_id = $this->database->insert('rate_sheet_item')
          ->fields([
            'rate_sheet_id',
            'api_attribute_id',
            'tiered_calculation',
            'attribute_name',
          ])
          ->values([
            $new_rate_sheet_id,
            $nid,
            $tiered_calculation,
            $node->getTitle(),
          ])
          ->execute();

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

      // Commit the transaction.
      unset($transaction);

      $this->logger->info('Rate sheet @id created successfully', ['@id' => $new_rate_sheet_id]);

      return $new_rate_sheet_id;
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }

      $this->logger->error('Failed to create rate sheet: @message', [
        '@message' => $e->getMessage(),
      ]);

      throw $e;
    }
  }

  /**
   * Checks if a rate sheet has unresolved reject comments.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   *
   * @return bool
   *   TRUE if there are unresolved reject comments, FALSE otherwise.
   */
  public function hasUnresolvedComments(int $rate_sheet_id): bool {
    $has_unresolved = $this->database->select('action_log', 'al')
      ->fields('al', ['id'])
      ->condition('action_type', 'REJECT_COMMENT')
      ->condition('entity_target_type', 'RATE_SHEET')
      ->condition('entity_target_id', $rate_sheet_id)
      ->condition('solved', 0)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return (bool) $has_unresolved;
  }

  /**
   * Checks if a specific user has unresolved reject comments for a rate sheet.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   * @param int $user_id
   *   The user ID.
   *
   * @return bool
   *   TRUE if the user has unresolved reject comments, FALSE otherwise.
   */
  public function userHasUnresolvedComments(int $rate_sheet_id, int $user_id): bool {
    $has_unresolved = $this->database->select('action_log', 'al')
      ->fields('al', ['id'])
      ->condition('action_type', 'REJECT_COMMENT')
      ->condition('entity_target_type', 'RATE_SHEET')
      ->condition('entity_target_id', $rate_sheet_id)
      ->condition('created_by', $user_id)
      ->condition('solved', 0)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return (bool) $has_unresolved;
  }

  /**
   * Gets reject comments for a rate sheet.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   *
   * @return array
   *   Array of reject comments with id, log_data, created_by, created_date, and solved status.
   */
  public function getRateSheetRejectComments(int $rate_sheet_id): array {
    $query = $this->database->select('action_log', 'al')
      ->fields('al', ['id', 'log_data', 'created_by', 'created_date', 'solved'])
      ->condition('action_type', 'REJECT_COMMENT')
      ->condition('entity_target_type', 'RATE_SHEET')
      ->condition('entity_target_id', $rate_sheet_id)
      ->orderBy('created_date', 'DESC');

    $results = $query->execute()->fetchAll();

    $comments = [];
    foreach ($results as $result) {
      $user = $this->entityTypeManager->getStorage('user')->load($result->created_by);
      $comments[] = [
        'id' => $result->id,
        'comment' => $result->log_data,
        'created_by' => $user ? $user->getEmail() : 'Unknown',
        'created_date' => $result->created_date,
        'solved' => (bool) $result->solved,
      ];
    }

    return $comments;
  }

  /**
   * Gets all client rate sheets with their details.
   *
   * @return array
   *   Array of client rate sheet data.
   */
  public function getClientRateSheets(): array {
    $query = $this->database->select('client_rate_sheet', 'crs')
      ->fields('crs', ['rate_sheet_id', 'client_id', 'created_by', 'created_date', 'active', 'rate_sheet_client_status_id']);
    
    $results = $query->execute()->fetchAll();

    $client_rate_sheets = [];
    foreach ($results as $result) {
      // Get rate sheet details
      $rate_sheet = $this->database->select('rate_sheet', 'rs')
        ->fields('rs', ['name', 'currency', 'effective_date', 'markup_retail'])
        ->condition('id', $result->rate_sheet_id)
        ->execute()
        ->fetchObject();

      // Get client name
      $client_name = $this->database->select('groups_field_data', 'gfd')
        ->fields('gfd', ['label'])
        ->condition('id', $result->client_id)
        ->execute()
        ->fetchField();

      // Get status name
      $status_name = $this->database->select('rate_sheet_status_lookup', 'rssl')
        ->fields('rssl', ['status_name'])
        ->condition('id', $result->rate_sheet_client_status_id)
        ->execute()
        ->fetchField();

      // Get approvers info
      $approvers = $this->getClientRateSheetApprovers($result->rate_sheet_id, $result->client_id);

      $client_rate_sheets[] = [
        'rate_sheet_id' => $result->rate_sheet_id,
        'client_id' => $result->client_id,
        'client_name' => $client_name ?: 'Unknown',
        'rate_sheet_name' => $rate_sheet ? $rate_sheet->name : 'Unknown',
        'currency' => $rate_sheet ? $rate_sheet->currency : '',
        'effective_date' => $rate_sheet ? $rate_sheet->effective_date : 0,
        'markup_retail' => $rate_sheet ? $rate_sheet->markup_retail : 0,
        'status' => $status_name ?: 'Pending',
        'approvers' => $approvers,
        'created_date' => $result->created_date,
      ];
    }

    return $client_rate_sheets;
  }

  /**
   * Gets approvers HTML for a Client Rate Sheet.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   * @param int $client_id
   *   The Client ID.
   *
   * @return string
   *   HTML markup showing approver status.
   */
  public function getClientRateSheetApprovers(int $rate_sheet_id, int $client_id): string {

    $client_rate_sheet_id = $this->database->select('client_rate_sheet', 'crs')
      ->fields('crs', ['id'])
      ->condition('client_id', $client_id)
      ->condition('rate_sheet_id', $rate_sheet_id)
      ->execute()
      ->fetchField();

    $query = $this->database->select('action_log', 'al')
      ->fields('al', ['action_type', 'created_by', 'created_date'])
      ->condition('entity_target_type', 'CLIENT_RATE_SHEET')
      ->condition('entity_target_id', $client_rate_sheet_id)
      ->condition(
        $this->database->condition('OR')
          ->condition('action_type', 'STATUS_UPDATE_APPROVE')
          ->condition('action_type', 'STATUS_UPDATE_REJECT')
      )
      ->orderBy('created_date', 'ASC');

    $results = $query->execute()->fetchAll();

    if (empty($results)) {
      return '<span class="pending">Pending</span><br><span class="pending">Pending</span>';
    }

    // Get last two actions
    $results = array_slice($results, -2);

    $statuses = [];
    foreach ($results as $result) {
      $user = $this->entityTypeManager->getStorage('user')->load($result->created_by);
      $email = $user ? $user->getEmail() : 'Unknown';
      
      $status = $result->action_type === 'STATUS_UPDATE_APPROVE' ? 'APPROVED' : 'REJECTED';
      
      $statuses[] = [
        'status' => $status,
        'email' => $email,
      ];
    }

    // Fill with pending if less than 2
    while (count($statuses) < 2) {
      $statuses[] = [
        'status' => 'PENDING',
        'email' => '',
      ];
    }

    $output = [];
    foreach ($statuses as $item) {
      $line = '';
      if ($item['email']) {
        $line .= $item['email'] . ' ';
      }

      $class = strtolower($item['status']);
      if ($item['status'] === 'PENDING') {
        $line .= '<span class="pending">Pending</span>';
      }
      else {
        $line .= '<span class="' . $class . '"></span>';
      }

      $output[] = $line;
    }

    return implode('<br>', $output);
  }


  /**
   * Approves client rate sheets in batch.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   * @param array $client_ids
   *   Array of Client IDs.
   * @param int $user_id
   *   The user ID performing the approval.
   * @param string $op
   *   The operation to be performed.
   *
   * @throws \Exception
   */

  public function statusClientRateSheetBatchOperation(int $rate_sheet_id, array $client_ids, int $user_id, string $op) {
    if (empty($client_ids)) {
      return;
    }

    try {
      foreach ($client_ids as $client_id) {
        switch($op) {
          case 'approve':
            $this->approveClientRateSheet($rate_sheet_id, $client_id, $user_id);
            break;
          case 'reject':
            $this->rejectClientRateSheet($rate_sheet_id, $client_id, $user_id);
            break;
          case 'disable':
            $this->disableClientRateSheet($rate_sheet_id, $client_id, $user_id);
            break;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to approve client rate sheets in batch: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }

  }

  /**
   * Approves a client rate sheet.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   * @param int $client_id
   *   The Client ID.
   * @param int $user_id
   *   The user ID performing the approval.
   *
   * @throws \Exception
   */
  public function approveClientRateSheet(int $rate_sheet_id, int $client_id, int $user_id) {
    $transaction = $this->database->startTransaction();

    $client_rate_sheet = $this->database->select('client_rate_sheet', 'crs')
      ->fields('crs', ['id', 'created_by'])
      ->condition('client_id', $client_id)
      ->condition('rate_sheet_id', $rate_sheet_id)
      ->execute()
      ->fetchAll();

    if ($client_rate_sheet[0]->id) {

      if ((int) $client_rate_sheet[0]->created_by === (int) $user_id) {
        throw new AccessDeniedHttpException(
          'You cannot approve a rate sheet linking that you created.'
        );
      }

      try {
        // Insert approval action log
        $this->database->insert('action_log')
          ->fields([
            'action_type' => 'STATUS_UPDATE_APPROVE',
            'entity_target_type' => 'CLIENT_RATE_SHEET',
            'entity_target_id' => $client_rate_sheet[0]->id,
            'created_by' => $user_id,
            'created_date' => \Drupal::time()->getRequestTime(),
            'log_data' => "User {$user_id} approved client rate sheet {$rate_sheet_id} for client {$client_id}.",
            'solved' => 0,
          ])
          ->execute();

        // Check if this is the second approval
        $approval_count = $this->database->select('action_log', 'al')
          ->fields('al', ['id'])
          ->condition('action_type', 'STATUS_UPDATE_APPROVE')
          ->condition('entity_target_type', 'CLIENT_RATE_SHEET')
          ->condition('entity_target_id', $client_rate_sheet[0]->id)
          ->countQuery()
          ->execute()
          ->fetchField();

        if ($approval_count >= 2) {
          // Update status to Approved
          $approved_status_id = $this->getRateStatusId('Approved');
          
          $this->database->update('client_rate_sheet')
            ->fields(['rate_sheet_client_status_id' => $approved_status_id])
            ->condition('rate_sheet_id', $rate_sheet_id)
            ->condition('client_id', $client_id)
            ->execute();

          // Update group field_selected_rate_sheets
          $this->updateClientSelectedRateSheets($client_id, $rate_sheet_id, TRUE);
        }

        unset($transaction);
      }
      catch (\Exception $e) {
        if (isset($transaction)) {
          $transaction->rollBack();
        }
        $this->logger->error('Failed to approve client rate sheet: @message', ['@message' => $e->getMessage()]);
        throw $e;
      }
    }
  }

  /**
   * Rejects a client rate sheet.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   * @param int $client_id
   *   The Client ID.
   * @param int $user_id
   *   The user ID performing the rejection.
   *
   * @throws \Exception
   */
  public function rejectClientRateSheet(int $rate_sheet_id, int $client_id, int $user_id) {
    $transaction = $this->database->startTransaction();

    $client_rate_sheet = $this->database->select('client_rate_sheet', 'crs')
      ->fields('crs', ['id', 'created_by'])
      ->condition('client_id', $client_id)
      ->condition('rate_sheet_id', $rate_sheet_id)
      ->execute()
      ->fetchAll();

    try {

      if ((int) $client_rate_sheet[0]->created_by === (int) $user_id) {
        throw new AccessDeniedHttpException(
          'You cannot reject a rate sheet linking that you created.'
        );
      }

      // Insert rejection action log
      $this->database->insert('action_log')
        ->fields([
          'action_type' => 'STATUS_UPDATE_REJECT',
          'entity_target_type' => 'CLIENT_RATE_SHEET',
          'entity_target_id' => $client_rate_sheet[0]->id,
          'created_by' => $user_id,
          'created_date' => \Drupal::time()->getRequestTime(),
          'log_data' => "User {$user_id} rejected client rate sheet {$rate_sheet_id} for client {$client_id}.",
          'solved' => 0,
        ])
        ->execute();

      // Update status to Rejected
      $rejected_status_id = $this->getRateStatusId('Rejected');
      
      $this->database->update('client_rate_sheet')
        ->fields(['rate_sheet_client_status_id' => $rejected_status_id])
        ->condition('rate_sheet_id', $rate_sheet_id)
        ->condition('client_id', $client_id)
        ->execute();

      unset($transaction);
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      $this->logger->error('Failed to reject client rate sheet: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  public function disableClientRateSheet(int $rate_sheet_id, int $client_id, int $user_id) {
    $transaction = $this->database->startTransaction();

    $client_rate_sheet = $this->database->select('client_rate_sheet', 'crs')
      ->fields('crs', ['id', 'created_by'])
      ->condition('client_id', $client_id)
      ->condition('rate_sheet_id', $rate_sheet_id)
      ->execute()
      ->fetchAll();

    try {

      if ((int) $client_rate_sheet[0]->created_by === (int) $user_id) {
        throw new AccessDeniedHttpException(
          'You cannot disable a rate sheet linking that you created.'
        );
      }

      // Insert rejection action log
      $this->database->insert('action_log')
        ->fields([
          'action_type' => 'STATUS_UPDATE_DISABLE',
          'entity_target_type' => 'CLIENT_RATE_SHEET',
          'entity_target_id' => $client_rate_sheet[0]->id,
          'created_by' => $user_id,
          'created_date' => \Drupal::time()->getRequestTime(),
          'log_data' => "User {$user_id} disabled client rate sheet {$rate_sheet_id} for client {$client_id}.",
          'solved' => 0,
        ])
        ->execute();

      // Update status to Rejected
      $cancelled_status_id = $this->getRateStatusId('Cancelled');
      
      $this->database->update('client_rate_sheet')
        ->fields(
          [
            'rate_sheet_client_status_id' => $cancelled_status_id,
            'active' => 0
          ]
        )
        ->condition('rate_sheet_id', $rate_sheet_id)
        ->condition('client_id', $client_id)
        ->execute();

      unset($transaction);
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      $this->logger->error('Failed to disable client rate sheet: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Updates the client's selected rate sheets field.
   *
   * @param int $client_id
   *   The Client (group) ID.
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   * @param bool $add
   *   TRUE to add, FALSE to remove.
   */
  protected function updateClientSelectedRateSheets(int $client_id, int $rate_sheet_id, bool $add = TRUE) {
    // Get current value
    $current_value = $this->database->select('group__field_selected_rate_sheets', 'gfsr')
      ->fields('gfsr', ['field_selected_rate_sheets_value'])
      ->condition('entity_id', $client_id)
      ->execute()
      ->fetchField();

    $rate_sheets = [];
    if ($current_value) {
      $rate_sheets = json_decode($current_value, TRUE) ?: [];
    }

    if ($add) {
      $rate_sheets[(string) $rate_sheet_id] = 1;
    }
    else {
      unset($rate_sheets[(string) $rate_sheet_id]);
    }

    $new_value = json_encode($rate_sheets);

    // Check if record exists
    $exists = $this->database->select('group__field_selected_rate_sheets', 'gfsr')
      ->fields('gfsr', ['entity_id'])
      ->condition('entity_id', $client_id)
      ->execute()
      ->fetchField();

    if ($exists) {
      $this->database->update('group__field_selected_rate_sheets')
        ->fields(['field_selected_rate_sheets_value' => $new_value])
        ->condition('entity_id', $client_id)
        ->execute();
    }
    else {
      // Get the latest revision_id for this group
      $revision_id = $this->database->select('groups', 'g')
        ->fields('g', ['revision_id'])
        ->condition('id', $client_id)
        ->execute()
        ->fetchField();

      $this->database->insert('group__field_selected_rate_sheets')
        ->fields([
          'bundle' => 'partner',
          'deleted' => 0,
          'entity_id' => $client_id,
          'revision_id' => $revision_id,
          'langcode' => 'en',
          'delta' => 0,
          'field_selected_rate_sheets_value' => $new_value,
        ])
        ->execute();
    }
  }

  /**
   * Inserts a new status for a rate sheet.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   * @param int $status
   *   The status to insert (2 for Approve, 3 for Reject).
   * @param int $user_id
   *   The ID of the user submitting the status.
   * @param string|null $reject_comment
   *   Optional comment when rejecting a rate sheet.
   *
   * @throws \Exception
   */
  public function insertRateSheetStatus(int $rate_sheet_id, int $status, int $user_id, string $reject_comment = NULL) {
    $transaction = $this->database->startTransaction();

    try {
      // Check if there are unresolved reject comments
      if ($this->hasUnresolvedComments($rate_sheet_id)) {
        throw new \Exception('Cannot change status while there are unresolved reject comments.');
      }

      $status_name = $status === 2 ? 'Approved' : 'Rejected';

      $new_rate_sheet_status = $this->database->insert('rate_sheet_status')
        ->fields([
          'rate_sheet_id' => $rate_sheet_id,
          'status_name' => $status_name,
          'created_by' => $user_id,
          'date' => \Drupal::time()->getRequestTime(),
        ])
        ->execute();

      // Determine the new rate_sheet_status_id
      $new_status_id = NULL;

      if ($status === 3) {
        // Rejected - set to Rejected status
        $new_status_id = $this->getRateStatusId('Rejected');
      }
      elseif ($status === 2) {
        // Approved - check if this is the second approval
        $approval_count = $this->database->select('rate_sheet_status', 'rss')
          ->fields('rss', ['id'])
          ->condition('rate_sheet_id', $rate_sheet_id)
          ->condition('status_name', 'Approved')
          ->countQuery()
          ->execute()
          ->fetchField();

        if ($approval_count >= 2) {
          // Second approval - set to Approved status
          $new_status_id = $this->getRateStatusId('Approved');
        }
        else {
          // First approval - keep as Pending
          $new_status_id = $this->getRateStatusId('Pending');
        }
      }

      // Update the rate_sheet_status_id
      if ($new_status_id !== NULL) {
        $this->database->update('rate_sheet')
          ->fields(['rate_sheet_status_id' => $new_status_id])
          ->condition('id', $rate_sheet_id)
          ->execute();
      }

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
          $user_id,
          \Drupal::time()->getRequestTime(),
          "User {$user_id} changed the status of rate sheet {$rate_sheet_id} to {$status_name}.",
          0,
        ])
        ->execute();

      // Insert reject comment if status is rejected and comment is provided.
      if ($status === 3 && !empty($reject_comment)) {
        
        $new_action_log = $this->database->insert('action_log')
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
            'REJECT_COMMENT',
            'RATE_SHEET',
            $rate_sheet_id,
            $user_id,
            \Drupal::time()->getRequestTime(),
            $reject_comment,
            0,
          ])
          ->execute();
          
          $this->database->update('rate_sheet_status')
            ->fields([
              'action_log_id' => $new_action_log
            ])
            ->condition('id', $new_rate_sheet_status, '=')
            ->execute();
      }

      unset($transaction);
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      \Drupal::logger('zcs_api_attributes')->error('Failed to insert rate sheet status: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Cancels a rate sheet by setting its status to Cancelled.
   *
   * @param int $rate_sheet_id
   *   The Rate Sheet ID.
   * @param int $user_id
   *   The ID of the user cancelling the rate sheet.
   *
   * @throws \Exception
   */
  public function cancelRateSheet(int $rate_sheet_id, int $user_id) {
    $transaction = $this->database->startTransaction();

    try {
      // Get the Cancelled status ID
      $cancelled_status_id = $this->getRateStatusId('Cancelled');

      if ($cancelled_status_id === NULL) {
        throw new \Exception('Cancelled status not found in lookup table.');
      }

      // Update the rate_sheet_status_id to Cancelled
      $this->database->update('rate_sheet')
        ->fields(['rate_sheet_status_id' => $cancelled_status_id])
        ->condition('id', $rate_sheet_id)
        ->execute();

      // Insert a status record
      $this->database->insert('rate_sheet_status')
        ->fields([
          'rate_sheet_id' => $rate_sheet_id,
          'status_name' => 'Cancelled',
          'created_by' => $user_id,
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
          $user_id,
          \Drupal::time()->getRequestTime(),
          "User {$user_id} cancelled rate sheet {$rate_sheet_id}.",
          0,
        ])
        ->execute();

      unset($transaction);
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }
      \Drupal::logger('zcs_api_attributes')->error('Failed to cancel rate sheet: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

}
