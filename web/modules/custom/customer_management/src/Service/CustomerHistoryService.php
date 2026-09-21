<?php

namespace Drupal\customer_management\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;

/**
 * Customer history service backed by action_log.
 */
class CustomerHistoryService {

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
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs the service.
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    DateFormatterInterface $date_formatter
  ) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Writes a customer action to action_log.
   */
  public function log(int $customer_nid, string $action_type, int $uid, array $log_data = []): void {
    $this->database->insert('action_log')
      ->fields([
        'action_type' => $action_type,
        'entity_target_type' => 'customer',
        'entity_target_id' => $customer_nid,
        'created_by' => $uid,
        'created_date' => time(),
        'log_data' => json_encode($log_data),
        'solved' => 0,
      ])
      ->execute();
  }

  /**
   * Loads customer history rows.
   */
  public function loadHistory(int $customer_nid): array {
    $query = $this->database->select('action_log', 'al')
      ->fields('al')
      ->condition('entity_target_type', 'customer')
      ->condition('entity_target_id', $customer_nid)
      ->orderBy('created_date', 'DESC');

    $records = $query->execute()->fetchAllAssoc('id');

    if (!$records) {
      return [];
    }

    $uids = [];
    foreach ($records as $record) {
      if (!empty($record->created_by)) {
        $uids[] = (int) $record->created_by;
      }
    }

    $users = $uids ? $this->entityTypeManager->getStorage('user')->loadMultiple(array_unique($uids)) : [];
    $rows = [];

    foreach ($records as $record) {
      $username = '—';
      if (!empty($record->created_by) && isset($users[(int) $record->created_by])) {
        $username = $users[(int) $record->created_by]->getDisplayName();
      }

      $log_data = [];
      if (!empty($record->log_data)) {
        $decoded = json_decode($record->log_data, TRUE);
        if (is_array($decoded)) {
          $log_data = $decoded;
        }
      }

      $rows[] = [
        'date' => $this->dateFormatter->format((int) $record->created_date, 'short'),
        'action' => $this->getActionLabel((string) $record->action_type),
        'performed_by' => $username,
        'details' => $this->buildDetailsText((string) $record->action_type, $log_data),
      ];
    }

    return $rows;
  }

  /**
   * Builds a render array table for history.
   */
  public function buildHistoryTable(int $customer_nid): array {
    $rows = $this->loadHistory($customer_nid);

    if (!$rows) {
      return [
        '#markup' => (string) t('No history is available for this customer yet.'),
      ];
    }

    $table_rows = [];
    foreach ($rows as $row) {
      $table_rows[] = [
        'data' => [
          $row['date'],
          $row['action'],
          $row['performed_by'],
          $row['details'],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        (string) t('Date'),
        (string) t('Action'),
        (string) t('Performed By'),
        (string) t('Details'),
      ],
      '#rows' => $table_rows,
      '#attributes' => [
        'class' => ['attributes-table', 'customer-history-table'],
      ],
    ];
  }

  /**
   * Converts machine action name to human label.
   */
  protected function getActionLabel(string $action_type): string {
    return match ($action_type) {
      'customer_created' => 'Customer Created',
      'customer_updated' => 'Customer Updated',
      'hashed_key_reset' => 'Hashed Key Reset',
      default => ucfirst(str_replace('_', ' ', $action_type)),
    };
  }

  /**
   * Builds a human-readable details string.
   */
  protected function buildDetailsText(string $action_type, array $log_data): string {
    if ($action_type === 'customer_updated' && !empty($log_data['changed_fields']) && is_array($log_data['changed_fields'])) {
      return 'Changed fields: ' . implode(', ', $log_data['changed_fields']);
    }

    if ($action_type === 'hashed_key_reset' && !empty($log_data['email_sent_to'])) {
      return 'New key emailed to ' . $log_data['email_sent_to'];
    }

    if ($action_type === 'customer_created' && !empty($log_data['email_sent_to'])) {
      return 'Credentials emailed to ' . $log_data['email_sent_to'];
    }

    return '—';
  }

}
