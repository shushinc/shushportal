<?php

namespace Drupal\dashboard\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service for providing dashboard data.
 */
class DataSource {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a DataSource object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(
    Connection $database,
    ConfigFactoryInterface $config_factory,
    TimeInterface $time,
    ModuleHandlerInterface $module_handler,
  ) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->time = $time;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Gets revenue metrics data.
   *
   * @return array
   *   Array of revenue metrics.
   */
  public function getCardsData() {

    $connection = \Drupal::database();

    $data = $this->loadCardsData();

    foreach ($data as &$card) {
      if (isset($card['source'])) {
        $top_query = $this->loadSqlFromFile('card-' . $card['source'] . '-top');
        if ($top_query !== FALSE) {
          $results = $connection->query($top_query)->fetchAll();
          // print_r($results);
          if (isset($results[0])) {
            list ($sign, $value, $suffix) = $this->formatNumber($results[0]->value);
            $card['value'] =  ($sign == '-' ? '-' : '' ) . $value;
            $card['suffix'] = $suffix;
          }
          else {
            $card['value'] = 0;
            $card['suffix'] = '';
          }
        }
      }
    }

    // @todo Replace with actual SQL queries
    return $data;
  }

  /**
   * Gets filters data.
   *
   * @return array
   *   Array of filter options.
   */
  public function getFiltersData() {
    return [
      'carriers' => [
        'label' => 'Carriers',
        'options' => [
          'account_status_change_retrieve' => 'Account Status Change Retrieve...',
        ],
        'selected' => 'account_status_change_retrieve',
      ],
      'attributes' => [
        'label' => 'Attributes',
        'options' => [
          'account_status_change_retrieve' => 'Account Status Change Retrieve...',
        ],
        'selected' => 'account_status_change_retrieve',
      ],
      'client' => [
        'label' => 'Client',
        'options' => [
          'clientA' => 'ClientA',
        ],
        'selected' => 'clientA',
      ],
      'end_customers' => [
        'label' => 'End Customers',
        'options' => [
          'customerName' => 'customerName',
        ],
        'selected' => 'customerName',
      ],
      'month' => [
        'label' => 'Month',
        'options' => [
          'jun' => 'Jun',
          'jul' => 'Jul',
          'aug' => 'Aug',
        ],
        'selected' => 'jun',
      ],
      'year' => [
        'label' => 'Year',
        'options' => [
          '2024' => '2024',
          '2025' => '2025',
        ],
        'selected' => '2025',
      ],
    ];
  }

  /**
   * Gets charts data for visualization.
   *
   * @return array
   *   Array of chart data.
   */
  public function getChartsData() {
    return [
      [
        'title' => 'Estimated Payment by Attribute',
        'id' => 'estimated_payment_by_attribute'
      ],
      [
        'title' => 'Successful API Volume by Attribute',
        'id' => 'successful_api_volume_by_attribute'
      ],
      [
        'title' => 'Daily Average API Latency',
        'id' => 'daily_average_api_latency'
      ],
      [
        'title' => 'API Volumes by Type',
        'id' => 'api_volumes_by_type'
      ],
    ];
  }

  /**
   * Gets historical data for trends.
   *
   * @param string $metric
   *   The metric to get historical data for.
   * @param string $period
   *   The time period (daily, weekly, monthly).
   * @param int $limit
   *   Number of data points to return.
   *
   * @return array
   *   Historical data array.
   */
  public function getHistoricalData($metric, $period = 'daily', $limit = 30) {
    // @todo Implement actual database queries based on parameters
    $sample_data = [];
    $base_value = 50000;

    for ($i = 0; $i < $limit; $i++) {
      $sample_data[] = [
        'date' => date('Y-m-d', strtotime("-{$i} days")),
        'value' => $base_value + rand(-10000, 15000),
      ];
    }

    return array_reverse($sample_data);
  }

  protected function formatNumber($number) {
    // Handle negative numbers
    if ($number < 0) {
      $sign = '-';
    }
    elseif ($number > 0) {
      $sign = '+';
    }
    else {
      $sign = '';
    }
    $number = abs($number);

    // Define suffixes and their values
    $suffixes = [
      ['value' => 1000000000000, 'suffix' => 'T'], // Trillions
      ['value' => 1000000000, 'suffix' => 'B'],    // Billions
      ['value' => 1000000, 'suffix' => 'M'],       // Millions
      ['value' => 1000, 'suffix' => 'K']           // Thousands
    ];

    // Find the appropriate suffix
    foreach ($suffixes as $item) {
      if ($number >= $item['value']) {
        $result = $number / $item['value'];

        // Format to 3 significant digits
        if ($result >= 100) {
          // 3 digits before decimal (e.g., 123K)
          $formatted = number_format($result, 0);
        } elseif ($result >= 10) {
          // 2 digits before, 1 after decimal (e.g., 12.3K)
          $formatted = number_format($result, 1);
        } else {
          // 1 digit before, 2 after decimal (e.g., 1.23K)
          $formatted = number_format($result, 2);
        }

        // Remove trailing zeros after decimal point
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, '.');

        return [$sign, $formatted, $item['suffix']];
      }
    }

    // For numbers less than 1000, return as is (max 3 digits)
    return [$sign, number_format($number), ''];
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
    $module_path = $this->moduleHandler->getModule('dashboard')->getPath();
    $sql_file = DRUPAL_ROOT . '/' . $module_path . '/assets/sql/' . $filename . '.sql';

    if (file_exists($sql_file)) {
      return file_get_contents($sql_file);
    }

    return FALSE;
  }

  protected function loadCardsData() {
    $module_path = $this->moduleHandler->getModule('dashboard')->getPath();
    $ymlFile = DRUPAL_ROOT . '/' . $module_path . '/assets/data/cards.yml';

    if (!file_exists($ymlFile)) {
      throw new NotFoundHttpException();
    }

    $ymlElements = Yaml::decode(file_get_contents($ymlFile));

    return $ymlElements['cards'];
  }

}
