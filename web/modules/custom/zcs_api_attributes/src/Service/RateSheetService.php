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
   * Gets the approvers for a rate sheet.
   *
   * @param int $rate_sheet_id
   *   The rate sheet ID.
   *
   * @return array
   *   Array of approver information.
   */
  public function getRateSheetApprovers($rate_sheet_id) {
    // Query to get approvers from rate_sheet_approval table or similar.
    // This is a placeholder - adjust based on your actual schema.
    $query = $this->database->select('rate_sheet_approval', 'rsa')
      ->fields('rsa')
      ->condition('rsa.rate_sheet_id', $rate_sheet_id)
      ->execute();

    $approvers = [];
    foreach ($query as $row) {
      $approvers[] = [
        'uid' => $row->approver_uid ?? NULL,
        'status' => $row->status ?? NULL,
        'date' => $row->approval_date ?? NULL,
      ];
    }

    return $approvers;
  }

  /**
   * Gets the current status of a rate sheet.
   *
   * @param int $rate_sheet_id
   *   The rate sheet ID.
   *
   * @return string
   *   The status name.
   */
  public function getRateSheetStatus($rate_sheet_id) {
    $status = $this->database->select('rate_sheet_status', 'rss')
      ->fields('rss', ['status_name'])
      ->condition('rss.rate_sheet_id', $rate_sheet_id)
      ->orderBy('rss.date', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $status ?: 'Unknown';
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

}
