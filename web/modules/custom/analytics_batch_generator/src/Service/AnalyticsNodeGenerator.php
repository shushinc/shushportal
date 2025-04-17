<?php

namespace Drupal\analytics_batch_generator\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Service for generating analytics nodes in batch.
 */
class AnalyticsNodeGenerator {

  use DependencySerializationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new AnalyticsNodeGenerator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * Generates analytics nodes for the past 3 years.
   */
  public function generateNodes() {
    // Get current timestamp
    $current_time = $this->time->getCurrentTime();

    // Calculate timestamp for 3 years ago
    $three_years_ago = $current_time - (3 * 365 * 24 * 60 * 60);

    // Create an array of timestamps for each day
    $days = [];
    $timestamp = $three_years_ago;
    while ($timestamp <= $current_time) {
      $days[] = $timestamp;
      $timestamp += 86400; // Add one day (in seconds)
    }

    // Set up the batch
    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setTitle(t('Generating analytics nodes'))
      ->setInitMessage(t('Starting node generation...'))
      ->setProgressMessage(t('Processed @current out of @total days.'))
      ->setErrorMessage(t('An error occurred during processing'));

    // Create batch operations for chunks of days
    $chunks = array_chunk($days, 10); // Process 10 days per batch operation

    foreach ($chunks as $chunk) {
      $batch_builder->addOperation(
        [$this, 'processNodeBatch'],
        [$chunk]
      );
    }

    $batch_builder->setFinishCallback([$this, 'finishNodeBatch']);

    batch_set($batch_builder->toArray());

    if (PHP_SAPI !== 'cli') {
      // If not in drush, process the batch immediately
      batch_process();
    }
  }

  /**
   * Batch operation callback for creating nodes.
   */
  public function processNodeBatch($days, &$context) {
    if (!isset($context['results']['processed'])) {
      $context['results']['processed'] = 0;
      $context['results']['created'] = 0;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');

    foreach ($days as $timestamp) {
      try {
        // Load related taxonomy terms and entities for reference fields
        $attributes = $this->getRandomTerms('analytics_attributes', 1);
        $carriers = $this->getRandomTerms('analytics_carrier', 1);
        $customers = $this->getRandomTerms('analytics_customer', 1);
        $partners = $this->getRandomGroups('partner', 1);

        // Create node with random data for each field
        $node = $node_storage->create([
          'type' => 'analytics',
          'title' => 'Test',
          'field_404_api_volume_in_mil' => $this->getRandomDecimal(0, 5),
          'field_api_volume_in_mil' => $this->getRandomInteger(1, 100),
          'field_attribute' => empty($attributes) ? NULL : $attributes[0],
          'field_average_api_latency_in_mil' => $this->getRandomInteger(50, 500),
          'field_carrier' => empty($carriers) ? NULL : $carriers[0],
          'field_date' => date('Y-m-d\TH:i:s', $timestamp),
          'field_end_customer' => empty($customers) ? NULL : $customers[0],
          'field_error_api_volume_in_mil' => $this->getRandomDecimal(0, 2),
          'field_est_revenue' => $this->getRandomInteger(1000, 10000),
          'field_partner' => empty($partners) ? NULL : $partners[0],
          'field_success_api_volume_in_mil' => $this->getRandomDecimal(5, 50),
          'field_transaction_type' => $this->getRandomTransactionType(),
        ]);

        $node->save();
        $context['results']['created']++;
      }
      catch (\Exception $e) {
        \Drupal::logger('analytics_batch_generator')->error('Error creating node for timestamp @timestamp: @message', [
          '@timestamp' => $timestamp,
          '@message' => $e->getMessage(),
        ]);
      }

      $context['results']['processed']++;
    }

    $context['message'] = t('Created @created nodes out of @processed days processed.', [
      '@created' => $context['results']['created'],
      '@processed' => $context['results']['processed'],
    ]);
  }

  /**
   * Batch finished callback.
   */
  public function finishNodeBatch($success, $results, $operations) {
    if ($success) {
      $message = t('Successfully created @created analytics nodes from @processed days.', [
        '@created' => $results['created'],
        '@processed' => $results['processed'],
      ]);
      \Drupal::messenger()->addMessage($message);
    }
    else {
      $message = t('Finished with an error.');
      \Drupal::messenger()->addError($message);
    }
  }

  /**
   * Gets random taxonomy terms.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   * @param int $count
   *   Number of terms to return.
   *
   * @return array
   *   Array of term IDs.
   */
  protected function getRandomTerms($vocabulary, $count = 1) {
    try {
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $query = $term_storage->getQuery()
        ->condition('vid', $vocabulary)
        ->accessCheck(FALSE)
        ->range(0, 100);
      $tids = $query->execute();

      if (empty($tids)) {
        return [];
      }

      $random_keys = array_rand($tids, min(count($tids), $count));
      if (!is_array($random_keys)) {
        $random_keys = [$random_keys];
      }

      $result = [];
      foreach ($random_keys as $key) {
        $result[] = $tids[$key];
      }

      return $result;
    }
    catch (\Exception $e) {
      \Drupal::logger('analytics_batch_generator')->error('Error getting random terms: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Gets random group entities.
   *
   * @param string $group_type
   *   The group type.
   * @param int $count
   *   Number of groups to return.
   *
   * @return array
   *   Array of group IDs.
   */
  protected function getRandomGroups($group_type, $count = 1) {
    try {
      $group_storage = $this->entityTypeManager->getStorage('group');
      $query = $group_storage->getQuery()
        ->condition('type', $group_type)
        ->accessCheck(FALSE)
        ->range(0, 100);
      $gids = $query->execute();

      if (empty($gids)) {
        return [];
      }

      $random_keys = array_rand($gids, min(count($gids), $count));
      if (!is_array($random_keys)) {
        $random_keys = [$random_keys];
      }

      $result = [];
      foreach ($random_keys as $key) {
        $result[] = $gids[$key];
      }

      return $result;
    }
    catch (\Exception $e) {
      \Drupal::logger('analytics_batch_generator')->error('Error getting random groups: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Gets a random decimal number within a range.
   *
   * @param float $min
   *   Minimum value.
   * @param float $max
   *   Maximum value.
   *
   * @return float
   *   Random decimal number.
   */
  protected function getRandomDecimal($min, $max) {
    return $min + mt_rand() / mt_getrandmax() * ($max - $min);
  }

  /**
   * Gets a random integer within a range.
   *
   * @param int $min
   *   Minimum value.
   * @param int $max
   *   Maximum value.
   *
   * @return int
   *   Random integer.
   */
  protected function getRandomInteger($min, $max) {
    return mt_rand($min, $max);
  }

  /**
   * Gets a random transaction type.
   *
   * @return string
   *   Random transaction type.
   */
  protected function getRandomTransactionType() {
    $types = [
      'successful',
      'unsuccessful_transactions',
      'user_not_supported',
    ];

    return $types[array_rand($types)];
  }

}
