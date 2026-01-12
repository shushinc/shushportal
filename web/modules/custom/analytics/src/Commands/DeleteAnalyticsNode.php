<?php

namespace Drupal\analytics\Commands;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drush\Commands\DrushCommands;

/**
 *
 */
class DeleteAnalyticsNode extends DrushCommands {

  /**
   * Delete Analytic nodes.
   *
   * @option Analytics
   *   analytics
   *
   * @command analytics:delete-nodes
   * @aliases zcs_adn
   * @usage analytics:delete-nodes
   *   Delete analytic nodes.
   */
  public function deleteAnalyticsNodes(string $name = 'World'): void {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'analytics')
      ->sort('nid', 'ASC')
      ->range(0, 500)
      ->sort('created', 'DESC')
      ->accessCheck(false)
      ->execute();

    $storage = \Drupal::entityTypeManager()->getStorage('node');

    foreach (array_chunk($nids, 500) as $chunk) {
       // Log node IDs before deletion
      foreach ($chunk as $nid) {
        \Drupal::logger('Delete Analytics Node')->info('Deleting node with nid: @nid', [
          '@nid' => $nid,
        ]);
      }
      $nodes = $storage->loadMultiple($chunk);
      $storage->delete($nodes);
    }
  }

}
