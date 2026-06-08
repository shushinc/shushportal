<?php

namespace Drupal\zcs_api_attributes\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Psr\Log\LoggerInterface;

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
     *
     * @param int $rate_sheet_id
     *   The Rate Sheet ID.
     *
     * @return string
     *   HTML markup.
     */
  public function getRateSheetApprovers(int $rate_sheet_id): string {

    $query = $this->database->select('rate_sheet_status', 'rss')
      ->fields('rss', ['status_name', 'created_by'])
      ->condition('rate_sheet_id', $rate_sheet_id)
      ->orderBy('date', 'ASC');

    $rows = $query->execute()->fetchAll();

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
     * Returns the current Rate Sheet status based on the last two records.
     *
     * Rules:
     * - 2 APPROVED > APPROVED
     * - 2 DENIED > DENIED
     * - APPROVED + DENIED > PENDING
     * - Any PENDING > PENDING
     * - Only one record (initial state) > PENDING
     *
     * @param int $rate_sheet_id
     *   The Rate Sheet ID.
     *
     * @return string
     *   One of: 'PENDING', 'APPROVED', or 'DENIED'.
     */
  public function getRateSheetStatus(int $rate_sheet_id): string {

    $query = $this->database->select('rate_sheet_status', 'rss')
      ->fields('rss', ['status_name'])
      ->condition('rate_sheet_id', $rate_sheet_id)
      ->orderBy('date', 'DESC')
      ->range(0, 2);

      $statuses = $query->execute()->fetchCol();

      if (empty($statuses)) {
        return 'Pending';
      }

        // Only one status (initial state)
      if (count($statuses) === 1) {
        return 'Pending';
      }

      $first = $statuses[0];
      $second = $statuses[1];

      // If any is pending > still pending
      if ($first === 'Pending' || $second === 'Pending') {
        return 'Pending';
      }

        // Both equal > final decision
      if ($first === $second) {
        return $first; // APPROVED or DENIED
      }

      // Conflict (APPROVED vs DENIED)
      return 'Pending';
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
   *
   * @return int|false
   *   The new rate sheet ID or FALSE on failure.
   *
   * @throws \Exception
   */
  public function createRateSheet(array $data) {
    $transaction = $this->database->startTransaction();

    try {
      // Insert the rate sheet.
      $new_rate_sheet_id = $this->database->insert('rate_sheet')
        ->fields([
          'name',
          'currency',
          'created_by',
          'markup_retail',
          'created_date',
          'effective_date',
        ])
        ->values([
          $data['name'],
          $data['currency'],
          $this->currentUser->id(),
          $data['markup_retail'],
          \Drupal::time()->getRequestTime(),
          $data['effective_date'],
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
     */
    public function insertRateSheetStatus(int $rate_sheet_id, int $status, int $user_id, string $reject_comment = NULL) {
        $transaction = $this->database->startTransaction();

        try {
            $status_name = $status === 2 ? 'Approved' : 'Rejected';

            $this->database->insert('rate_sheet_status')
                ->fields([
                    'rate_sheet_id' => $rate_sheet_id,
                    'status_name' => $status_name,
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
                ])
                ->values([
                    'STATUS_UPDATE',
                    'RATE_SHEET',
                    $rate_sheet_id,
                    $user_id,
                    \Drupal::time()->getRequestTime(),
                    "User {$user_id} changed the status of rate sheet {$rate_sheet_id} to {$status_name}.",
                ])
                ->execute();

            // Insert reject comment if status is rejected and comment is provided.
            if ($status === 3 && !empty($reject_comment)) {
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
                        'REJECT_COMMENT',
                        'RATE_SHEET',
                        $rate_sheet_id,
                        $user_id,
                        \Drupal::time()->getRequestTime(),
                        $reject_comment,
                        0,
                    ])
                    ->execute();
            }
        }
        catch (\Exception $e) {
            $transaction->rollBack();
            \Drupal::logger('zcs_api_attributes')->error('Failed to insert rate sheet status: @message', ['@message' => $e->getMessage()]);
            throw $e;
        }
    }

}
