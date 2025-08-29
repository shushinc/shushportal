<?php

namespace Drupal\consolidate\Commands;

use Drupal\consolidate\Services\AnalyticsConsolidationService;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for the consolidate module.
 */
final class ConsolidateCommands extends DrushCommands {

  /**
   * The analytics consolidation service.
   *
   * @var \Drupal\consolidate\Services\AnalyticsConsolidationService
   */
  protected $consolidationService;

  /**
   * Constructs a ConsolidateCommands object.
   *
   * @param \Drupal\consolidate\Services\AnalyticsConsolidationService $consolidation_service
   *   The analytics consolidation service.
   */
  public function __construct(AnalyticsConsolidationService $consolidation_service) {
    parent::__construct();
    $this->consolidationService = $consolidation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('consolidate.analytics_service')
    );
  }

  /**
   * Consolidate revenue from analytics entities within a date range.
   *
   * @param string $start_date
   *   Start date in Y-m-d format.
   * @param string $end_date
   *   End date in Y-m-d format.
   * @param array $options
   *   Additional options.
   *
   * @command consolidate:consolidate-range
   * @aliases con:rev
   * @usage consolidate:consolidate-range 2024-01-01 2024-01-31
   *   Consolidate revenue for January 2024
   * @usage consolidate:consolidate-range 2024-01-01 2024-01-31 --show-details
   *   Consolidate revenue with detailed output
   */
  #[CLI\Command(name: 'consolidate:consolidate-range', aliases: ['con:ran'])]
  #[CLI\Argument(name: 'filename', description: 'Filename (lowercase string format)')]
  #[CLI\Argument(name: 'start_date', description: 'Start date (Y-m-d format)')]
  #[CLI\Argument(name: 'end_date', description: 'End date (Y-m-d format)')]
  #[CLI\Usage(name: 'consolidate:consolidate-range 2024-01-01 2024-01-31', description: 'Consolidate revenue for January 2024')]
  public function getConsolidatedRange(string $filename, string $start_date, string $end_date, array $options = []) {
    try {
      // Validate date format.
      if (!$this->validateDateFormat($start_date) || !$this->validateDateFormat($end_date)) {
        $this->logger()->error('Invalid date format. Please use Y-m-d format (e.g., 2024-01-01)');
        return DrushCommands::EXIT_FAILURE;
      }

      $this->logger()->notice('Starting revenue consolidation...');
      $this->logger()->info('Date range: @start to @end', [
        '@start' => $start_date,
        '@end' => $end_date,
      ]);

      // Call the consolidation service.
      $result = $this->consolidationService->getAnalyticsInDateRange($filename, $start_date, $end_date);
      $result = reset($result['rows']);

      // Display results.
      $this->output()->writeln('');
      $this->output()->writeln('<info>===== CONSOLIDATION RESULTS =====</info>');
      $this->output()->writeln(sprintf('<info>Date Range:</info> %s to %s', $start_date, $end_date));
      $this->output()->writeln(sprintf('<info>Total Revenue:</info> $%s', number_format($result['total'], 2)));

      return DrushCommands::EXIT_SUCCESS;

    }
    catch (\InvalidArgumentException $e) {
      $this->logger()->error('Invalid argument: ' . $e->getMessage());
      return DrushCommands::EXIT_FAILURE;
    }
    catch (\Exception $e) {
      $this->logger()->error('An error occurred during consolidation: ' . $e->getMessage());
      return DrushCommands::EXIT_FAILURE;
    }
  }

  /**
   * Validates date format.
   *
   * @param string $date
   *   The date string to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  private function validateDateFormat(string $date): bool {
    $format = 'Y-m-d';
    $datetime = \DateTime::createFromFormat($format, $date);
    return $datetime && $datetime->format($format) === $date;
  }

  #[CLI\Command(name: 'consolidate:consolidate-day', aliases: ['con:day'])]
  #[CLI\Argument(name: 'group', description: 'Group (lowercase string format)')]
  #[CLI\Argument(name: 'date', description: 'Date (Y-m-d format)')]
  #[CLI\Usage(name: 'consolidate:consolidate-day 2024-01-01 revenue', description: 'Consolidate revenue for January 1st 2024')]
  public function consolidateDay(string $group, string $date, array $options = []) {
    try {
      // Validate date format.
      if (!$this->validateDateFormat($date)) {
        $this->logger()->error('Invalid date format. Please use Y-m-d format (e.g., 2024-01-01)');
        return DrushCommands::EXIT_FAILURE;
      }

      $this->logger()->notice('Starting revenue consolidation...');
      $this->logger()->info('Date day: @date', [
        '@date' => $date,
      ]);

      // Call the consolidation service.
      /** @var \Drupal\node\Entity\NodeInterface $node */
      $node = $this->consolidationService->consolidateDay($group, $date);

      // Display results.
      $this->output()->writeln(sprintf('<info>Node ID:</info> %s', $node->id()));

      return DrushCommands::EXIT_SUCCESS;
    } catch (\InvalidArgumentException $e) {
      $this->logger()->error('Invalid argument: ' . $e->getMessage());
      return DrushCommands::EXIT_FAILURE;
    } catch (\Exception $e) {
      $this->logger()->error('An error occurred during consolidation: ' . $e->getMessage());
      return DrushCommands::EXIT_FAILURE;
    }
  }

  #[CLI\Command(name: 'consolidate:consolidate-week', aliases: ['con:week'])]
  #[CLI\Argument(name: 'group', description: 'Group (lowercase string format)')]
  #[CLI\Argument(name: 'date', description: 'Date (Y-m-d format)')]
  #[CLI\Usage(name: 'consolidate:consolidate-week 2024-01-01 revenue', description: 'Consolidate revenue for January 1st 2024 week')]
  public function consolidateWeek(string $group, string $date, array $options = []) {
    try {
      // Validate date format.
      if (!$this->validateDateFormat($date)) {
        $this->logger()->error('Invalid date format. Please use Y-m-d format (e.g., 2024-01-01)');
        return DrushCommands::EXIT_FAILURE;
      }

      $this->logger()->notice('Starting revenue consolidation...');
      $this->logger()->info('Date day: @date', [
        '@date' => $date,
      ]);

      // Call the consolidation service.
      /** @var \Drupal\node\Entity\NodeInterface $node */
      $node = $this->consolidationService->consolidateWeek($group, $date);

      // Display results.
      $this->output()->writeln(sprintf('<info>Node ID:</info> %s', $node->id()));

      return DrushCommands::EXIT_SUCCESS;
    } catch (\InvalidArgumentException $e) {
      $this->logger()->error('Invalid argument: ' . $e->getMessage());
      return DrushCommands::EXIT_FAILURE;
    } catch (\Exception $e) {
      $this->logger()->error('An error occurred during consolidation: ' . $e->getMessage());
      return DrushCommands::EXIT_FAILURE;
    }
  }

  #[CLI\Command(name: 'consolidate:consolidate-month', aliases: ['con:month'])]
  #[CLI\Argument(name: 'group', description: 'Group (lowercase string format)')]
  #[CLI\Argument(name: 'date', description: 'Date (Y-m-d format)')]
  #[CLI\Usage(name: 'consolidate:consolidate-month 2024-01-01 revenue', description: 'Consolidate revenue for January 1st 2024 month')]
  public function consolidateMonth(string $group, string $date, array $options = []) {
    try {
      // Validate date format.
      if (!$this->validateDateFormat($date)) {
        $this->logger()->error('Invalid date format. Please use Y-m-d format (e.g., 2024-01-01)');
        return DrushCommands::EXIT_FAILURE;
      }

      $this->logger()->notice('Starting revenue consolidation...');
      $this->logger()->info('Date month: @date', [
        '@date' => $date,
      ]);

      // Call the consolidation service.
      /** @var \Drupal\node\Entity\NodeInterface $node */
      $node = $this->consolidationService->consolidateMonth($group, $date);

      // Display results.
      $this->output()->writeln(sprintf('<info>Node ID:</info> %s', $node->id()));

      return DrushCommands::EXIT_SUCCESS;
    } catch (\InvalidArgumentException $e) {
      $this->logger()->error('Invalid argument: ' . $e->getMessage());
      return DrushCommands::EXIT_FAILURE;
    } catch (\Exception $e) {
      $this->logger()->error('An error occurred during consolidation: ' . $e->getMessage());
      return DrushCommands::EXIT_FAILURE;
    }
  }

  #[CLI\Command(name: 'consolidate:consolidate-year', aliases: ['con:year'])]
  #[CLI\Argument(name: 'group', description: 'Group (lowercase string format)')]
  #[CLI\Argument(name: 'date', description: 'Date (Y-m-d format)')]
  #[CLI\Usage(name: 'consolidate:consolidate-year 2024-01-01 revenue', description: 'Consolidate revenue for January 1st 2024 year')]
  public function consolidateYear(string $group, string $date, array $options = []) {
    try {
      // Validate date format.
      if (!$this->validateDateFormat($date)) {
        $this->logger()->error('Invalid date format. Please use Y-m-d format (e.g., 2024-01-01)');
        return DrushCommands::EXIT_FAILURE;
      }

      $this->logger()->notice('Starting revenue consolidation...');
      $this->logger()->info('Date month: @date', [
        '@date' => $date,
      ]);

      // Call the consolidation service.
      /** @var \Drupal\node\Entity\NodeInterface $node */
      $node = $this->consolidationService->consolidateYear($group, $date);

      // Display results.
      $this->output()->writeln(sprintf('<info>Node ID:</info> %s', $node->id()));

      return DrushCommands::EXIT_SUCCESS;
    } catch (\InvalidArgumentException $e) {
      $this->logger()->error('Invalid argument: ' . $e->getMessage());
      return DrushCommands::EXIT_FAILURE;
    } catch (\Exception $e) {
      $this->logger()->error('An error occurred during consolidation: ' . $e->getMessage());
      return DrushCommands::EXIT_FAILURE;
    }
  }

}
