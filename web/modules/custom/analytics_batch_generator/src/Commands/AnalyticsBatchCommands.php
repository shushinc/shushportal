<?php

namespace Drupal\analytics_batch_generator\Commands;

use Drupal\analytics_batch_generator\Service\AnalyticsNodeGenerator;
use Drupal\analytics_batch_generator\Service\AnalyticsRandomNodeGenerator;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\Table;

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
   * The analytics node generator service.
   *
   * @var \Drupal\analytics_batch_generator\Service\AnalyticsRandomNodeGenerator
   */
  protected $randomNodeGenerator;

  /**
   * Constructs a new AnalyticsBatchCommands object.
   *
   * @param \Drupal\analytics_batch_generator\Service\AnalyticsNodeGenerator $node_generator
   *   The analytics node generator service.
   * @param \Drupal\analytics_batch_generator\Service\AnalyticsRandomNodeGenerator $random_node_generator
   *   The analytics random node generator service.
   */
  public function __construct(
    AnalyticsNodeGenerator $node_generator,
    AnalyticsRandomNodeGenerator $random_node_generator,
  ) {
    parent::__construct();
    $this->nodeGenerator = $node_generator;
    $this->randomNodeGenerator = $random_node_generator;
  }

  /**
   * Generates analytics nodes for each day from today to 3 years ago.
   *
   * @option model_id
   *   Metabase model id.
   *
   * @command analytics:generate-nodes
   * @aliases agn
   * @usage analytics:generate-nodes [--ago=3] [--mode=day]
   *   Generates analytics nodes for the past 3 days.
   */
  public function generateNodes(
    array $options = [
      'ago' => 1,
      'mode' => 'day',
    ],
  ) {
    $this->output()->writeln('Starting batch process to generate analytics nodes...');

    // Start the batch process.
    $this->nodeGenerator->generateNodes($options['ago'], $options['mode']);

    // Process the batch directly in Drush.
    $batch = &batch_get();
    $batch['progressive'] = FALSE;

    drush_backend_batch_process();

    $this->output()->writeln('Completed node generation process.');
  }

  /**
   * Generates analytics nodes for each day from today to 3 years ago.
   *
   * @option model_id
   *   Metabase model id.
   *
   * @command analytics:generate-random-nodes
   * @aliases argn
   * @usage analytics:generate-random-nodes [--ago=3] [--mode=day]
   *   Generates analytics nodes for the past 3 days.
   */
  public function generateRandomNodes(
    array $options = [
      'ago' => 1,
      'mode' => 'day',
    ],
  ) {
    $this->output()->writeln('Starting batch process to generate analytics nodes...');

    // Start the batch process.
    $this->randomNodeGenerator->generateNodes($options['ago'], $options['mode']);

    // Process the batch directly in Drush.
    $batch =& batch_get();
    $batch['progressive'] = FALSE;

    drush_backend_batch_process();

    $this->output()->writeln('Completed node generation process.');
  }

  /**
   * Gets all taxonomy terms.
   *
   * @param array $options
   *   The command options.
   */
  #[CLI\Command(name: 'analytics:get-all-terms', aliases: ['agat'])]
  #[CLI\Option(name: 'vocabulary', description: 'The SQL query to execute (SELECT statements only)')]
  #[CLI\Option(name: 'format', description: 'Output format: table, json, csv', suggestedValues: ['table', 'json', 'csv'])]
  #[CLI\Usage(name: 'drush analytics:get-all-terms --vocabulary="topics" --format=json', description: 'Execute query with JSON output')]
  #[CLI\Usage(name: 'drush agat --vocabulary="topics" --format=json', description: 'Execute query with JSON output')]
  public function getAllTerms(
    array $options = [
      'vocabulary' => NULL,
      'format' => 'table',
    ],
  ) {
    $vocabulary = $options['vocabulary'];
    $format = $options['format'] ?? 'table';

    $terms = $this->nodeGenerator->getAllTerms($vocabulary);

    $term_array = [];
    foreach ($terms as $term) {
      $term_array[] = [
        'tid' => $term->tid->value,
        'name' => $term->name->value,
      ];
    }

    // Output results based on format.
    switch ($format) {

      case 'csv':
        $this->outputCsv($term_array);
        break;

      case 'table':
      default:
        $this->outputTable($term_array);
        break;
    }
  }

  /**
   * Gets all taxonomy terms.
   *
   * @param array $options
   *   The command options.
   */
  #[CLI\Command(name: 'analytics:get-all-groups', aliases: ['agag'])]
  #[CLI\Option(name: 'group', description: 'The SQL query to execute (SELECT statements only)')]
  #[CLI\Option(name: 'format', description: 'Output format: table, json, csv', suggestedValues: ['table', 'json', 'csv'])]
  #[CLI\Usage(name: 'drush analytics:get-all-groups --group="partner" --format=table', description: 'Execute query with JSON output')]
  #[CLI\Usage(name: 'drush agag --group="partner" --format=table', description: 'Execute query with JSON output')]
  public function getAllGroups(
    array $options = [
      'group' => NULL,
      'format' => 'table',
    ],
  ) {
    $group = $options['group'];
    $format = $options['format'] ?? 'table';

    $groups = $this->nodeGenerator->getAllGroups($group);

    $group_array = [];
    foreach ($groups as $group) {
      $group_array[] = [
        'tid' => $group->id->value,
        'label' => $group->label->value,
      ];
    }

    // Output results based on format.
    switch ($format) {

      case 'csv':
        $this->outputCsv($group_array);
        break;

      case 'table':
      default:
        $this->outputTable($group_array);
        break;
    }
  }

  /**
   * Output results as a table.
   *
   * @param array $rows
   *   The query result rows.
   */
  protected function outputTable(array $rows): void {
    if (empty($rows)) {
      return;
    }

    $table = new Table($this->output());

    // Set headers from first row keys.
    $headers = array_keys($rows[0]);
    $table->setHeaders($headers);

    // Add rows.
    foreach ($rows as $row) {
      // Convert all values to strings and handle NULLs.
      $tableRow = array_map(function ($value) {
        return $value === NULL ? 'NULL' : (string) $value;
      }, array_values($row));

      $table->addRow($tableRow);
    }

    $table->render();
  }

  /**
   * Output results as CSV.
   *
   * @param array $rows
   *   The query result rows.
   */
  protected function outputCsv(array $rows): void {
    if (empty($rows)) {
      return;
    }

    // Output headers.
    $headers = array_keys($rows[0]);
    $this->output()->writeln(implode(',', array_map(function ($header) {
      return '"' . str_replace('"', '""', $header) . '"';
    }, $headers)));

    // Output data rows.
    foreach ($rows as $row) {
      $csvRow = array_map(function ($value) {
        $value = $value === NULL ? '' : (string) $value;
        return '"' . str_replace('"', '""', $value) . '"';
      }, array_values($row));

      $this->output()->writeln(implode(',', $csvRow));
    }
  }

}
