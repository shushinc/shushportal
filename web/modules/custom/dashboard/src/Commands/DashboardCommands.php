<?php

namespace Drupal\dashboard\Commands;

use Drupal\dashboard\Service\DashboardQueryService;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\Table;

/**
 * Dashboard Drush commands.
 */
final class DashboardCommands extends DrushCommands {

  /**
   * Get contents from stdin if available.
   *
   * @return string
   *   The stdin contents or empty string.
   */
  protected function getStdinContents(): string {
    // Check if stdin is available and not a TTY (meaning it's piped input)
    if (!posix_isatty(STDIN)) {
      $contents = '';
      while (($line = fgets(STDIN)) !== FALSE) {
        $contents .= $line;
      }
      return trim($contents);
    }
    return '';
  }

  /**
   * The dashboard query service.
   *
   * @var \Drupal\dashboard\Service\DashboardQueryService
   */
  protected $queryService;

  /**
   * Constructs a new DashboardCommands object.
   *
   * @param \Drupal\dashboard\Service\DashboardQueryService $query_service
   *   The dashboard query service.
   */
  public function __construct(DashboardQueryService $query_service) {
    parent::__construct();
    $this->queryService = $query_service;
  }

  /**
   * Execute a SQL query on the database.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   */
  #[CLI\Command(name: 'dashboard:query', aliases: ['dq'])]
  #[CLI\Option(name: 'query', description: 'The SQL query to execute (SELECT statements only)')]
  #[CLI\Option(name: 'format', description: 'Output format: table, json, csv', suggestedValues: ['table', 'json', 'csv'])]
  #[CLI\Option(name: 'limit', description: 'Limit the number of rows returned')]
  #[CLI\Option(name: 'filter-year', description: 'Filter by year')]
  #[CLI\Option(name: 'filter-month', description: 'Filter by month')]
  #[CLI\Option(name: 'filter-attribute', description: 'Filter by attribute')]
  #[CLI\Usage(name: 'drush dashboard:query --query="SELECT * FROM {node} LIMIT 10"', description: 'Execute a SELECT query')]
  #[CLI\Usage(name: 'drush dq --query="SELECT nid, title FROM {node_field_data} WHERE status = 1" --format=json', description: 'Execute query with JSON output')]
  #[CLI\Usage(name: 'cat query.sql | drush dq --format=table', description: 'Execute query from file via pipe')]
  public function query(array $options = [
    'query' => NULL,
    'format' => 'table',
    'limit' => NULL,
    'filter-year' => NULL,
    'filter-month' => NULL,
    'filter-attribute' => NULL,
  ]): void {

    // Get all input options to capture dynamic filter arguments
    $input = $this->input();
    $allOptions = [];

    // Get all option definitions from the command
    foreach ($input->getOptions() as $name => $value) {
      if ($value !== NULL) {
        $allOptions[$name] = $value;
      }
    }

    // Extract dynamic filter arguments
    $filters = $this->extractFilterArguments($allOptions);

    $query = $options['query'];
    $format = $options['format'] ?? 'table';
    $limit = $options['limit'] ? (int) $options['limit'] : NULL;

    // Check if query is provided via stdin (pipe)
    if (empty($query)) {
      $stdin = $this->getStdinContents();
      if (!empty($stdin)) {
        $query = trim($stdin);
      }
    }

    // Validate that query is provided either via option or stdin
    if (empty($query)) {
      $this->logger()->error('Query is required. Use --query="YOUR_SQL_QUERY" or pipe query via stdin.');
      $this->logger()->notice('Examples:');
      $this->logger()->notice('  drush dq --query="SELECT * FROM {node}"');
      $this->logger()->notice('  cat query.sql | drush dq --format=json');
      $this->logger()->notice('  echo "SELECT nid FROM {node}" | drush dq');
      return;
    }

    // Validate the query
    if (!$this->queryService->validateQuery($query)) {
      $this->logger()->error('Invalid query. Only SELECT statements are allowed.');
      return;
    }

    // Add limit if specified
    if ($limit && !stripos($query, 'LIMIT')) {
      $query .= " LIMIT {$limit}";
    }

    // Execute the query
    $result = $this->queryService->executeQuery($query, $filters);

    // Handle errors
    if (!$result['success']) {
      $this->logger()->error($result['message']);
      return;
    }

    // Display success message
    $this->logger()->success($result['message']);

    // Handle empty results
    if (empty($result['rows'])) {
      $this->logger()->notice('No rows returned.');
      return;
    }

    // Output results based on format
    switch ($format) {
      case 'json':
        $this->output()->writeln(json_encode($result['rows'], JSON_PRETTY_PRINT));
        break;

      case 'csv':
        $this->outputCsv($result['rows']);
        break;

      case 'table':
      default:
        $this->outputTable($result['rows']);
        break;
    }
  }

  /**
   * List available database tables.
   */
  #[CLI\Command(name: 'dashboard:tables', aliases: ['dt'])]
  #[CLI\Usage(name: 'drush dashboard:tables', description: 'List all database tables')]
  public function tables(): void {
    $tables = $this->queryService->getTableNames();

    if (empty($tables)) {
      $this->logger()->notice('No tables found.');
      return;
    }

    $this->logger()->success(sprintf('Found %d tables:', count($tables)));

    // Display tables in columns
    $chunks = array_chunk($tables, 3);
    foreach ($chunks as $chunk) {
      $this->output()->writeln(sprintf('  %-30s %-30s %-30s',
        $chunk[0] ?? '',
        $chunk[1] ?? '',
        $chunk[2] ?? ''
      ));
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

    // Set headers from first row keys
    $headers = array_keys($rows[0]);
    $table->setHeaders($headers);

    // Add rows
    foreach ($rows as $row) {
      // Convert all values to strings and handle NULLs
      $tableRow = array_map(function($value) {
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

    // Output headers
    $headers = array_keys($rows[0]);
    $this->output()->writeln(implode(',', array_map(function($header) {
      return '"' . str_replace('"', '""', $header) . '"';
    }, $headers)));

    // Output data rows
    foreach ($rows as $row) {
      $csvRow = array_map(function($value) {
        $value = $value === NULL ? '' : (string) $value;
        return '"' . str_replace('"', '""', $value) . '"';
      }, array_values($row));

      $this->output()->writeln(implode(',', $csvRow));
    }
  }

  /**
   * Extract filter arguments from options.
   *
   * @param array $options
   *   All command options.
   *
   * @return array
   *   Array of filter arguments with 'filter-' prefix removed.
   */
  private function extractFilterArguments(array $options): array {
    $filters = [];

    foreach ($options as $key => $value) {
      // Check if the option starts with 'filter-'
      if (strpos($key, 'filter-') === 0 && $value !== NULL && $value !== '') {
        // Remove 'filter-' prefix and convert to snake_case for service use
        $filterKey = str_replace('filter-', '', $key);
        $filterKey = str_replace('-', '_', $filterKey);
        $filters[$filterKey] = $value;
      }
    }

    return $filters;
  }

}
