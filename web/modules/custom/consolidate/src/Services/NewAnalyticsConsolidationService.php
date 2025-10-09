<?php

namespace Drupal\consolidate\Services;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Service to consolidate analytics data.
 */
class NewAnalyticsConsolidationService {

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
  public function consolidateDay(string $group, string $date): int {
    $groups = ['revenue', 'volume', 'successful_api_calls', 'avg_api_latency', 'vol_from_silent_auth', 'top_10_customers', 'top_client'];

    if (in_array($group, $groups) === FALSE) {
      throw new \InvalidArgumentException('Invalid group: ' . $group);
    }

    $result = $this->getAnalyticsInDateRange($group, $date, $date);
    $result = reset($result['rows']);

    return $result['total'] ?? 0;
  }

  public function consolidateWeek(string $group, string $date): int {
    $referenceDateTime = new DrupalDateTime($date);
    $week = $referenceDateTime->format('W');
    $year = $referenceDateTime->format('Y');

    $firstDayOfTheWeek = new DrupalDateTime();
    $firstDayOfTheWeek->setISODate($year, $week, 0);
    $startDate = $firstDayOfTheWeek->format('Y-m-d');

    // Add one week
    $nextWeekFirstDate = clone $firstDayOfTheWeek;
    $nextWeekFirstDate->modify('+6 days');
    $nextWeek = $nextWeekFirstDate->format('Y-m-d');

    $groups = ['revenue', 'volume', 'successful_api_calls', 'avg_api_latency', 'vol_from_silent_auth', 'top_10_customers', 'top_client'];

    if (in_array($group, $groups) === FALSE) {
      throw new \InvalidArgumentException('Invalid group: ' . $group);
    }

    $result = $this->getAnalyticsInDateRange($group, $startDate, $nextWeek);
    $result = reset($result['rows']);

    return $result['total'] ?? 0;
  }

  public function consolidateMonth(string $group, string $date): int {
    $referenceDateTime = new DrupalDateTime($date);

    $firstDayOfTheMonth = clone $referenceDateTime;
    $firstDayOfTheMonth->setDate($referenceDateTime->format('Y'), $referenceDateTime->format('n'), '1');
    $startDate = $firstDayOfTheMonth->format('Y-m-d');

    $lastDayOfTheMonth = new DrupalDateTime();
    $lastDayOfTheMonth->setDate(
      $firstDayOfTheMonth->format('Y'),
      $firstDayOfTheMonth->format('n'),
      $lastDayOfTheMonth->format('j')
    );
    $endDate = $lastDayOfTheMonth->format('Y-m-d');

    $groups = ['revenue', 'volume', 'successful_api_calls', 'avg_api_latency', 'vol_from_silent_auth', 'top_10_customers', 'top_client'];

    if (in_array($group, $groups) === FALSE) {
      throw new \InvalidArgumentException('Invalid group: ' . $group);
    }

    $result = $this->getAnalyticsInDateRange($group, $startDate, $endDate);
    $result = reset($result['rows']);

    return $result['total'] ?? 0;
  }

  public function consolidateYear(string $group, string $date): int {
    $referenceDateTime = new DrupalDateTime($date);

    $firstDayOfTheYear = clone $referenceDateTime;
    $firstDayOfTheYear->setDate($referenceDateTime->format('Y'), '1', '1');
    $startDate = $firstDayOfTheYear->format('Y-m-d');

    $lastActiveDayOfTheYear = new DrupalDateTime();
    $lastActiveDayOfTheYear->setDate(
      $firstDayOfTheYear->format('Y'),
      $lastActiveDayOfTheYear->format('n'),
      $lastActiveDayOfTheYear->format('j')
    );
    $endDate = $lastActiveDayOfTheYear->format('Y-m-d');

    $groups = ['revenue', 'volume', 'successful_api_calls', 'avg_api_latency', 'vol_from_silent_auth', 'top_10_customers', 'top_client'];

    if (in_array($group, $groups) === FALSE) {
      throw new \InvalidArgumentException('Invalid group: ' . $group);
    }

    $connection = \Drupal::database();

    $result = $this->getAnalyticsInDateRange($group, $startDate, $endDate);
    $result = reset($result['rows']);

    return $result['total'] ?? 0;
  }

  public function consolidateAll() {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
    $oneYearAgo = date('Y-m-d', strtotime($yesterday . ' -1 year'));

    $a1 = $this->consolidateYear('revenue', $yesterday);
    $b1 = $this->consolidateYear('revenue', $oneYearAgo);

    $a2 = $this->consolidateMonth('revenue', $yesterday);
    $b2 = $this->consolidateMonth('revenue', $oneYearAgo);

    $a3 = $this->consolidateWeek('revenue', $yesterday);
    $b3 = $this->consolidateWeek('revenue', $oneYearAgo);

    $a4 = $this->consolidateDay('revenue', $yesterday);
    $b4 = $this->consolidateDay('revenue', $oneYearAgo);

    $a5 = $this->consolidateMonth('volume', $yesterday);
    $b5 = $this->consolidateMonth('volume', $oneYearAgo);

    $c1 = $this->consolidateMonth('successful_api_calls', $yesterday);
    $d1 = $this->consolidateMonth('successful_api_calls', $oneYearAgo);

    $c2 = $this->consolidateMonth('avg_api_latency', $yesterday);
    $d2 = $this->consolidateMonth('avg_api_latency', $oneYearAgo);

    $c3 = $this->consolidateMonth('vol_from_silent_auth', $yesterday);
    $d3 = $this->consolidateMonth('vol_from_silent_auth', $oneYearAgo);

    $c4 = $this->consolidateMonth('top_10_customers', $yesterday);
    $d4 = $this->consolidateMonth('top_10_customers', $oneYearAgo);

    $c5 = $this->consolidateMonth('top_client', $yesterday);
    $d5 = $this->consolidateMonth('top_client', $oneYearAgo);

    $data = [
      'created' => strtotime($today . ' -1 day'),
      'value_a1' => $a1,
      'value_b1' => $b1,
      'value_a2' => $a2,
      'value_b2' => $b2,
      'value_a3' => $a3,
      'value_b3' => $b3,
      'value_a4' => $a4,
      'value_b4' => $b4,
      'value_a5' => $a5,
      'value_b5' => $b5,
      'value_c1' => $c1,
      'value_d1' => $d1,
      'value_c2' => $c2,
      'value_d2' => $d2,
      'value_c3' => $c3,
      'value_d3' => $d3,
      'value_c4' => $c4,
      'value_d4' => $d4,
      'value_c5' => $c5,
      'value_d5' => $d5
    ];

    if ($b1 != 0) {
      $data['value_b1'] = (int) ((((int) $a1 - (int) $b1) / (int) $b1) * 100);
    }

    if ($b2 != 0) {
      $data['value_b2'] = (int) ((((int) $a2 - (int) $b2) / (int) $b2) * 100);
    }

    if ($b3 != 0) {
      $data['value_b3'] = (int) ((((int) $a3 - (int) $b3) / (int) $b3) * 100);
    }

    if ($b4 != 0) {
      $data['value_b4'] = (int) ((((int) $a4 - (int) $b4) / (int) $b4) * 100);
    }

    if ($b5 != 0) {
      $data['value_b5'] = (int) ((((int) $a5 - (int) $b5) / (int) $b5) * 100);
    }

    if ($d1 != 0) {
      $data['value_d1'] = (int) ((((int) $c1 - (int) $d1) / (int) $d1) * 100);
    }

    if ($d2 != 0) {
      $data['value_d2'] = (int) ((((int) $c2 - (int) $d2) / (int) $d2) * 100);
    }

    if ($d3 != 0) {
      $data['value_d3'] = (int) ((((int) $c3 - (int) $d3) / (int) $d3) * 100);
    }

    if ($d4 != 0) {
      $data['value_d4'] = (int) ((((int) $c4 - (int) $d4) / (int) $d4) * 100);
    }

    if ($d5 != 0) {
      $data['value_d5'] = (int) ((((int) $c5 - (int) $d5) / (int) $d5) * 100);
    }

    return $data;
  }

}
