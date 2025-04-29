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
   * @option model_id
   *   Metabase model id.
   *
   * @usage drush metabase:import
   * @command metabase:import
   * @aliases mb:import
   */
  public function import(
    $name,
    array $options = [
      'model_id' => NULL,
    ]
  ) {

    $configs = [
      'collection' => [
        'default' => 'api/collection/',
      ],
      'dashboard' => [
        'default' => 'api/dashboard/',
      ],
      'database' => [
        'default' => 'api/database/',
      ],
      'model' => [
        'default' => 'api/model-index/',
      ],
    ];

    if (!in_array($name, array_keys($configs))) {
      $this->io()->error('Invalid config name');
      return;
    }

    foreach ($configs[$name] as $key => $endpoint) {
      $config = \Drupal::configFactory()->getEditable("metabase.settings.{$name}.{$key}");

      $model_id = $options['model_id'];

      $params = [];
      if ($model_id) {
        $params['model_id'] = $model_id;
      }

      $response = $this->metabaseService->get($endpoint, $params);
      if (isset($response['data'])) {
        $config->set('data', $response['data'])->save();
      }
      elseif (is_array($response)) {
        $config->set('data', $response)->save();
      }
      else {
        $this->io()->info(print_r($response, TRUE));
      }
    }
  }

  /**
   * Metabase export config.
   *
   * @param string $name
   *   The config name to be exported.
   * @param string $key
   *   The config key to be exported.
   *
   * @usage drush metabase:export
   * @command metabase:export
   * @aliases mb:export
   */
  public function export($name, $key = 'default') {
    $configs = [
      'dashboard' => [
        'default' => 'api/dashboard/',
      ],
      'database' => [
        'default' => 'api/database/',
      ]
    ];

    if (!in_array($name, array_keys($configs))) {
      $this->io()->error('Invalid config name');
      return;
    }

    if (!in_array($key, array_keys($configs[$name]))) {
      $this->io()->error('Invalid config key');
      return;
    }

    $endpoint = $configs[$name][$key];

    $config = \Drupal::configFactory()->get("metabase.settings.{$name}.{$key}");

    $items = $config->get('data');
    foreach ($items as $key => $value) {
      $response = $this->metabaseService->get($endpoint . $value['id']);
      if ($response == NULL) {
        if ($name == 'database') {
          $value['details']['password'] = 'root';
        }
        $response = $this->metabaseService->post($endpoint, $value);
      }
    }
  }
}
