<?php

namespace Drupal\analytics_batch_generator\Commands;

use Drupal\analytics_batch_generator\Service\AnalyticsNodeGenerator;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Analytics Batch Generator.
 */
class AnalyticsBatchCommands extends DrushCommands {

  /**
   * The analytics node generator service.
   *
   * @var \Drupal\analytics_batch_generator\Service\AnalyticsNodeGenerator
   */
  protected $nodeGenerator;

  /**
   * Constructs a new AnalyticsBatchCommands object.
   *
   * @param \Drupal\analytics_batch_generator\Service\AnalyticsNodeGenerator $node_generator
   *   The analytics node generator service.
   */
  public function __construct(AnalyticsNodeGenerator $node_generator) {
    parent::__construct();
    $this->nodeGenerator = $node_generator;
  }

  /**
   * Generates analytics nodes for each day from today to 3 years ago.
   *
   * @option model_id
   *   Metabase model id.
   *
   * @command analytics:generate-nodes
   * @aliases ang
   * @usage analytics:generate-nodes [--ago=1] [--mode=day]
   *   Generates analytics nodes for the past 3 years.
   */
  public function generateNodes(
    array $options = [
      'ago' => 1,
      'mode' => 'day',
    ]
  ) {
    $this->output()->writeln('Starting batch process to generate analytics nodes...');

    // Start the batch process
    $this->nodeGenerator->generateNodes($options['ago'], $options['mode']);

    // Process the batch directly in Drush
    $batch =& batch_get();
    $batch['progressive'] = FALSE;

    drush_backend_batch_process();

    $this->output()->writeln('Completed node generation process.');
  }

}
