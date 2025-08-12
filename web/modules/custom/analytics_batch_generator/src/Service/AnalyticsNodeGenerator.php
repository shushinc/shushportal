<?php

namespace Drupal\analytics_batch_generator\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service for generating analytics nodes in batch.
 */
class AnalyticsNodeGenerator {

  use DependencySerializationTrait;
  use StringTranslationTrait;

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
  public function generateNodes($ago = 3, $mode = 'day') {
    // Get current timestamp.
    $current_time = $this->time->getCurrentTime();

    $ago = abs(intval($ago));

    if ($mode == 'day') {
      $time_ago = $current_time - ($ago * 24 * 60 * 60);
    }
    elseif ($mode == 'month') {
      $time_ago = $current_time - (30 * $ago * 24 * 60 * 60);
    }
    else {
      $time_ago = $current_time - (365 * $ago * 24 * 60 * 60);
    }

    // Create an array of timestamps for each day.
    $days = [];
    $timestamp = $time_ago;
    while ($timestamp <= $current_time) {
      $days[] = $timestamp;
      // Add one day (in seconds)
      $timestamp += 86400;
    }

    // Set up the batch.
    $batch_builder = new BatchBuilder();
    $batch_builder
      ->setTitle($this->t('Generating analytics nodes'))
      ->setInitMessage($this->t('Starting node generation...'))
      ->setProgressMessage($this->t('Processed @current out of @total days.'))
      ->setErrorMessage($this->t('An error occurred during processing'));

    // Create batch operations for chunks of days.
    // Process 2 days per batch operation.
    $chunks = array_chunk($days, 2);

    foreach ($chunks as $chunk) {
      $batch_builder->addOperation(
        [$this, 'processNodeBatch'],
        [$chunk]
      );
    }

    $batch_builder->setFinishCallback([$this, 'finishNodeBatch']);

    batch_set($batch_builder->toArray());

    // If (PHP_SAPI !== 'cli') {
    // // If not in drush, process the batch immediately.
    // batch_process();
    // }
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

        $partners = $this->getAllGroups('partner');
        $attibutes = $this->getAllTerms('analytics_attributes');
        $carriers = $this->getAllTerms('analytics_carrier');
        $customers = $this->getAllTerms('analytics_customer');

        $data = array_map(function ($partner) {
          return [
            'partner' => $partner->id->value,
          ];
        }, $partners);

        $data = array_map(function ($item) use ($attibutes) {
          $iteration = [];
          foreach ($attibutes as $attribute) {
            $iteration[] = [
              'partner' => $item['partner'],
              'attribute' => $attribute->tid->value,
            ];
          }
          return $iteration;
        }, $data);
        $data = $this->flatten($data);

        $data = array_map(function ($item) use ($carriers) {
          $iteration = [];
          foreach ($carriers as $carrier) {
            $iteration[] = [
              'partner' => $item['partner'],
              'attribute' => $item['attribute'],
              'carrier' => $carrier->tid->value,
            ];
          }
          return $iteration;
        }, $data);
        $data = $this->flatten($data);

        $data = array_map(function ($item) use ($customers) {
          $iteration = [];
          foreach ($customers as $customer) {
            $iteration[] = [
              'partner' => $item['partner'],
              'attribute' => $item['attribute'],
              'carrier' => $item['carrier'],
              'customer' => $customer->tid->value,
            ];
          }
          return $iteration;
        }, $data);
        $data = $this->flatten($data);

        foreach ($data as $item) {
          $status_counts = [
            '200' => $this->getRandomInteger(100, 1000),
            '404' => $this->getRandomInteger(10, 200),
            'other_non_200' => $this->getRandomInteger(0, 5),
          ];

          // Create node with random data for each field.
          $node = $node_storage->create([
            'type' => 'analytics',
            'title' => 'Test',
            'field_api_volume_in_mil' => array_sum($status_counts),
          // status_counts.200.
            'field_success_api_volume_in_mil' => $status_counts['200'],
          // status_counts.404.
            'field_404_api_volume_in_mil' => $status_counts['404'],
          // status_counts.other_non_200.
            'field_error_api_volume_in_mil' => $status_counts['other_non_200'],
            'field_attribute' => $item['attribute'],
            'field_carrier' => $item['carrier'],
            'field_end_customer' => $item['customer'],
            'field_partner' => $item['partner'],
            'field_average_api_latency_in_mil' => $this->getRandomInteger(10, 30),
            'field_date' => date('Y-m-d\TH:i:s', $timestamp),
            'field_est_revenue' => $this->getRandomInteger(1000, 10000),
            'field_transaction_type' => $this->getRandomTransactionType(),
            'field_transaction_type_count' => '10',
            // 'field_kong_analytical_id' =>
            // "2025-06-12 06:00:00|Uber|InfoBip|X Telecom|Account Status"
          ]);

          $node->save();
          $context['results']['created']++;
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('analytics_batch_generator')->error('Error creating node for timestamp @timestamp: @message', [
          '@timestamp' => $timestamp,
          '@message' => $e->getMessage(),
        ]);
      }

      $context['results']['processed']++;
    }

    $context['message'] = $this->t('Created @created nodes out of @processed days processed.', [
      '@created' => $context['results']['created'],
      '@processed' => $context['results']['processed'],
    ]);
  }

  /**
   * Batch finished callback.
   */
  public function finishNodeBatch($success, $results, $operations) {
    if ($success) {
      $message = $this->t('Successfully created @created analytics nodes from @processed days.', [
        '@created' => $results['created'],
        '@processed' => $results['processed'],
      ]);
      \Drupal::messenger()->addMessage($message);
    }
    else {
      $message = $this->t('Finished with an error.');
      \Drupal::messenger()->addError($message);
    }
  }

  /**
   * Gets all taxonomy terms.
   *
   * @param string $vocabulary
   *   The vocabulary machine name.
   *
   * @return array
   *   Array of term objects.
   */
  public function getAllTerms($vocabulary) {
    try {
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $query = $term_storage->getQuery()
        ->condition('vid', $vocabulary)
        ->accessCheck(FALSE);
      $tids = $query->execute();

      if (empty($tids)) {
        return [];
      }

      return $term_storage->loadMultiple($tids);
    }
    catch (\Exception $e) {
      \Drupal::logger('analytics_batch_generator')->error('Error getting random terms: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Gets all groups.
   *
   * @param string $group_type
   *   The group type.
   *
   * @return array
   *   Array of group objects.
   */
  public function getAllGroups($group_type) {
    try {
      $group_storage = $this->entityTypeManager->getStorage('group');
      $query = $group_storage->getQuery()
        ->condition('type', $group_type)
        ->accessCheck(FALSE);
      $gids = $query->execute();

      if (empty($gids)) {
        return [];
      }

      return $group_storage->loadMultiple($gids);
    }
    catch (\Exception $e) {
      \Drupal::logger('analytics_batch_generator')->error('Error getting random groups: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
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

  /**
   * Flatten array.
   *
   * @param array $data
   *   Array to flatten.
   *
   * @return array
   *   Flattened array.
   */
  protected function flatten($data) {
    $clean_data = [];
    foreach ($data as $item) {
      foreach ($item as $value) {
        $clean_data[] = $value;
      }
    }
    return $clean_data;
  }

}
