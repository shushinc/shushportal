<?php

namespace Drupal\metabase\Commands;

use Drupal\metabase\Service\MetabaseService;
use Drush\Commands\DrushCommands;

class ServiceConsumerCommands extends DrushCommands {

  /**
   * The metabase service.
   *
   * @var \Drupal\metabase\Service\MetabaseService
   */
  protected $metabaseService;

  /**
   * ApigeeEdgeCommands constructor.
   *
   * @param \Drupal\metabase\Service\MetabaseService $metabase_service
   *   The metabase service.
   */
  public function __construct(MetabaseService $metabase_service) {
    parent::__construct();
    $this->metabaseService = $metabase_service;
  }

  /**
   * Metabase test.
   *
   * @usage drush metabase:test
   * @command metabase:test
   * @aliases mb:test
   */
  public function test() {
    $response = $this->metabaseService->get('api/database/');

    // $this->io()->writeln(print_r($response, TRUE));

    // Define table headers
    $headers = [
      'ID',
      'Engine',
      'Name',
      'Created At'
    ];

    $rows = [];

    if (isset($response['data'])) {
      // Prepare table rows
      $rows = array_map(function($item) {
        return [
          $item['id'],
          $item['engine'],
          $item['name'],
          $item['created_at']
        ];
      }, $response['data']);
    }

    $this->io()->table($headers, $rows);
  }

  /**
   * Metabase import config.
   *
   * @param string $name
   *   The config name to be imported.
   *
   * @usage drush metabase:import
   * @command metabase:import
   * @aliases mb:import
   */
  public function import($name) {

    $configs = [
      'database' => [
        'default' => 'api/database/',
      ]
    ];

    if (!in_array($name, array_keys($configs))) {
      $this->io()->error('Invalid config name');
      return;
    }

    foreach ($configs[$name] as $key => $endpoint) {
      $config = \Drupal::configFactory()->getEditable("metabase.settings.{$name}.{$key}");
      $response = $this->metabaseService->get($endpoint);
      if ($response['data']) {
        $config->set('data', $response['data'])->save();
      }
    }
  }
}
