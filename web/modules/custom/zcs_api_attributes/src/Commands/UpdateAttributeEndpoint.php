<?php

namespace Drupal\zcs_api_attributes\Commands;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drush\Commands\DrushCommands;

/**
 *
 */
class UpdateAttributeEndpoint extends DrushCommands {

  /**
   * Update attributes endpoints.
   *
   * @option attributes
   *   attributes
   *
   * @command attributes:update-endpoint
   * @aliases zcs_up_endpoint_url
   * @usage attributes:update-endpoint
   *   Update attributes endpoints for attributes
   */
  public function updateEndpointAttributes(string $name = 'World'): void {
    // Path to your JSON file.
    $json_path = dirname(DRUPAL_ROOT) . '/portalasserts/attributes_endpoints_update.json';
    $this->output()->writeln($json_path);

    if (!file_exists($json_path)) {
      $this->output()->writeln("JSON file not found at: $json_path");
      return;
    }

    $json_content = file_get_contents($json_path);
    $data = json_decode($json_content, TRUE);

    if (!$data || !is_array($data)) {
      $this->output()->writeln("Invalid or empty JSON data.");
      return;
    }

    $items = $data['apis_attributes'] ?? [];

    foreach ($items as $item) {
      if (empty($item['name']) || empty($item['endpoint'])) {
        $this->output()->writeln("Skipping entry with missing attribute or endpoint.");
        continue;
      }
        // Load node by title and content type
        $nids = \Drupal::entityQuery('node')
            ->condition('type', 'api_attributes')
            ->condition('title', $item['name']) // Change name here
            ->accessCheck(FALSE)
            ->execute();


        if (!empty($nids)) {
            $nid = reset($nids);
            $node = Node::load($nid);
            if ($node) {
                $node->set('field_endpoint', $item['endpoint']);
                $node->save();
                $node->save();
                $this->output()->writeln("Attribute Endpoint updated for: " . $node->label() . " | Endpoint | " .$item['endpoint']);
            }
        }
    }
  }

}
