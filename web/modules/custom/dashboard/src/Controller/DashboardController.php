<?php

namespace Drupal\dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\dashboard\Service\DataSource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for dashboard pages.
 */
class DashboardController extends ControllerBase {

  /**
   * The data source service.
   *
   * @var \Drupal\dashboard\Service\DataSource
   */
  protected $dataSource;

  /**
   * Constructs a DashboardController object.
   *
   * @param \Drupal\dashboard\Service\DataSource $data_source
   *   The data source service.
   */
  public function __construct(DataSource $data_source) {
    $this->dataSource = $data_source;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dashboard.data_source')
    );
  }

  /**
   * Main dashboard page.
   *
   * @return array
   *   Render array for the dashboard.
   */
  public function main() {
    // Get all dashboard data.
    $metrics = $this->dataSource->getCardsData();
    $charts_data = $this->dataSource->getChartsData();
    $filters_data = $this->dataSource->getFiltersData();

    // Build render array.
    $build = [
      '#theme' => 'dashboard_main',
      '#metrics_cards' => $metrics,
      '#filters' => $filters_data,
      '#charts_data' => $charts_data,
      '#attached' => [
        'library' => ['dashboard/chartjs'],
        'drupalSettings' => [
          'dashboard' => [
            'charts_data' => $charts_data,
            'api_endpoint' => '/admin/dashboard/api/data',
          ],
        ],
      ],
    ];

    return $build;
  }

}
