<?php

namespace Drupal\dashboard\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for executing database queries.
 */
class DashboardQueryService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new DashboardQueryService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->logger = $logger_factory->get('dashboard');
  }

  /**
   * Execute a SQL query and return results.
   *
   * @param string $query
   *   The SQL query to execute.
   * @param array $args
   *   Optional query arguments.
   *
   * @return array
   *   Array containing results and metadata.
   */
  public function executeQuery(string $query, array $args = []): array {
    try {
      // Basic security check - only allow SELECT statements
      $trimmed_query = trim(strtoupper($query));
      if (!str_starts_with($trimmed_query, 'SELECT') && !str_starts_with($trimmed_query, 'WITH')) {
        throw new \InvalidArgumentException('Only SELECT queries are allowed for security reasons.');
      }

      $query = $this->processConditionalQuery($query, $args);

      // Log the query execution
      $this->logger->info('Executing query: @query', ['@query' => $query]);

      // Execute the query
      $result = $this->database->query($query, $args);

      // Fetch all results
      $rows = [];
      while ($row = $result->fetchAssoc()) {
        $rows[] = $row;
      }

      return [
        'success' => TRUE,
        'rows' => $rows,
        'count' => count($rows),
        'query' => $query,
        'message' => sprintf('Query executed successfully. Found %d rows.', count($rows)),
      ];

    }
    catch (\Exception $e) {
      // Log the error
      $this->logger->error('Query execution failed: @message. Query: @query', [
        '@message' => $e->getMessage(),
        '@query' => $query,
      ]);

      return [
        'success' => FALSE,
        'rows' => [],
        'count' => 0,
        'query' => $query,
        'error' => $e->getMessage(),
        'message' => 'Query execution failed: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get database table names with optional prefix filtering.
   *
   * @return array
   *   Array of table names.
   */
  public function getTableNames(): array {
    try {
      $tables = $this->database->schema()->findTables('%');
      return array_values($tables);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get table names: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Validate and sanitize a query string.
   *
   * @param string $query
   *   The query to validate.
   *
   * @return bool
   *   TRUE if query is valid, FALSE otherwise.
   */
  public function validateQuery(string $query): bool {
    // Basic validation - ensure it's a SELECT statement
    $trimmed_query = trim(strtoupper($query));

    // Check if it starts with SELECT
    if (!str_starts_with($trimmed_query, 'SELECT')) {
      return FALSE;
    }

    // Check for potentially dangerous keywords
    $dangerous_keywords = [
      'DROP', 'DELETE', 'UPDATE', 'INSERT', 'CREATE', 'ALTER',
      'TRUNCATE', 'REPLACE', 'MERGE', 'EXEC', 'EXECUTE'
    ];

    foreach ($dangerous_keywords as $keyword) {
      if (str_contains($trimmed_query, $keyword)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Process SQL query with conditional segments.
   */
  private function processConditionalQuery($query, array $parameters = []) {
    // Pattern to match [[...]] segments
    $pattern = '/\[\[(.*?)\]\]/';

    // Process each conditional segment
    $processed_query = preg_replace_callback($pattern, function ($matches) use ($parameters) {
      $segment = $matches[1]; // Content between [[ and ]]

      // Extract parameter name from the segment (look for :parameter_name)
      if (preg_match('/:(\w+)/', $segment, $param_matches)) {
        $param_name = $param_matches[1];

        // Include segment only if parameter exists and has a value
        if (isset($parameters[$param_name]) && $parameters[$param_name] !== null && $parameters[$param_name] !== '') {
          return $segment; // Return the segment without [[ ]]
        }
      }

      return ''; // Remove the entire segment
    }, $query);

    // Clean up extra whitespace and line breaks
    $processed_query = preg_replace('/\s+/', ' ', $processed_query);
    $processed_query = preg_replace('/\s*--\s*$/', '', $processed_query); // Remove trailing --

    return trim($processed_query);
  }

}
