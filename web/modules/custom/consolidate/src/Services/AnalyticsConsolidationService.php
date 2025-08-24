<?php

namespace Drupal\consolidate\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Service to consolidate analytics data.
 */
class AnalyticsConsolidationService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new AnalyticsConsolidationService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ModuleHandlerInterface $module_handler,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('consolidate');
    $this->moduleHandler = $module_handler;
  }

  /**
   * Consolidates revenue data from analytics entities within a date range.
   *
   * @param string $start_date
   *   The start date in 'Y-m-d' format.
   * @param string $end_date
   *   The end date in 'Y-m-d' format.
   *
   * @return array
   *   An array containing:
   *   - 'total_revenue': The sum of field_est_revenue values
   *   - 'count': Number of entities processed
   *   - 'entities': Array of entity IDs included in the calculation
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAnalyticsInDateRange(string $filename, string $start_date, string $end_date): array {

    // Convert dates to timestamps for comparison.
    $start_timestamp = $start_date . 'T00:00:00';
    $end_timestamp = $end_date . 'T23:59:59';

    if (!$start_timestamp || !$end_timestamp) {
      throw new \InvalidArgumentException('Invalid date format. Use Y-m-d format.');
    }

    if ($start_timestamp > $end_timestamp) {
      throw new \InvalidArgumentException('Start date must be before or equal to end date.');
    }

    $query = $this->loadSqlFromFile($filename);

    $dashboardService = \Drupal::service('dashboard.query_service');
    $result = $dashboardService->executeQuery($query, [
      ':start_date' => $start_timestamp,
      ':end_date' => $end_timestamp
    ]);

    return $result;
  }

  /**
   * Loads SQL query from file.
   *
   * @param string $filename
   *   The SQL filename without extension.
   *
   * @return string|false
   *   The SQL query string or FALSE on failure.
   */
  protected function loadSqlFromFile($filename) {
    $module_path = $this->moduleHandler->getModule('consolidate')->getPath();
    $sql_file = DRUPAL_ROOT . '/' . $module_path . '/assets/sql/' . $filename . '.sql';

    if (file_exists($sql_file)) {
      return file_get_contents($sql_file);
    }

    return FALSE;
  }

  /**
   * Consolidate revenue from analytics entities within a date range.
   *
   * @param string $date
   *   The date in 'Y-m-d' format.
   * @param string $group
   *   The group to consolidate.
   *
   * @return array
   *   An array containing:
   *   - 'total': The sum of field_est_revenue values
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function consolidateDay(string $group, string $date) {
    $groups = ['revenue', 'volume', 'successful_api_calls', 'avg_api_latency', 'vol_from_silent_auth', 'top_10_customers', 'top_client'];

    if (in_array($group, $groups) === FALSE) {
      throw new \InvalidArgumentException('Invalid group: ' . $group);
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $result = $node_storage->getQuery()
      ->condition('type', 'consolidated_analytics')
      ->condition('field_range', 'day')
      ->condition('field_reference_date', $date)
      ->condition('field_data_group', $group)
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($result)) {
      return $node_storage->load(reset($result));
    }

    $result = $this->getAnalyticsInDateRange($group, $date, $date);
    $result = reset($result['rows']);

    $node = $node_storage->create([
      'title' => 'Consolidated Analytics - ' . $group . ' - ' . $date,
      'type' => 'consolidated_analytics',
      'field_range' => 'day',
      'field_reference_date' => $date,
      'field_data_group' => $group,
      'field_value' => $result['total'],
    ]);
    $node->save();
    return $node;
  }

}
