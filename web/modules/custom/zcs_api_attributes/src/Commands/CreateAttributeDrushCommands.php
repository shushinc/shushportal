<?php

namespace Drupal\zcs_api_attributes\Commands;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drush\Commands\DrushCommands;

/**
 *
 */
class CreateAttributeDrushCommands extends DrushCommands {

  /**
   * Generates attributes nodes.
   *
   * @option attributes
   *   attributes
   *
   * @command attributes:generate-nodes
   * @aliases agn
   * @usage attributes:generate-nodes
   *   Generates attributes nodes for default data.
   */
  public function generateNodes(string $name = 'World'): void {
    // Path to your JSON file.
    $json_path = dirname(DRUPAL_ROOT) . '/portalasserts/attributes.json';
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
    $json_ratesheet = [];
    $json_api_attribute = [];

    foreach ($data as $item) {
      if (empty($item['title']) || empty($item['weight'])) {
        $this->output()->writeln("Skipping entry with missing title or weight.");
        continue;
      }
      $node = Node::create([
      // Change this to your content type machine name.
        'type' => 'api_attributes',
        'title' => $item['title'],
        'field_able_to_be_used' => $item['able_to_used'],
        'field_attribute_weight' => $item['weight'],
        'field_endpoint' => $item['endpoint'] ?? '',
      ]);
      $node->save();
      $json_ratesheet[$node->id()] = $item['standard_price'];
      $json_api_attribute[$node->id()]['able_to_be_used'] = ($item['able_to_used'] === 'yes') ? 1 : 0;
      $this->output()->writeln("Attribute created: " . $node->label());
    }

    // For Rate sheet display approve the content on installtion setup.
    $current_date = date('Y-m-d', \Drupal::time()->getCurrentTime());
    $defaultCurrency = \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US';
    Database::getConnection()->insert('attributes_page_data')
      ->fields([
        'submit_by',
        'currency_locale',
        'effective_date',
        'effective_date_integer',
        'page_data',
        'approver1_uid',
        'approver1_status',
        'approver2_uid',
        'approver2_status',
        'attribute_status',
        'created',
        'updated',
        ])
      ->values([
        1, // 'submit_by'
        $defaultCurrency, // 'currency_locale'
        $current_date, // 'effective_date'
        strtotime($current_date), // 'effective_date_integer'
        Json::encode($json_ratesheet), // 'page_data'
        1, // approver1_uid
        2, // approver1_status
        1, // approver2_uid
        2, // approver2_status
        2, // attribute_status
        \Drupal::time()->getRequestTime(), // created
        \Drupal::time()->getRequestTime(), // updated
    ])
      ->execute();

    // For api_attribute_sheet approve the content on installtion setup.
    Database::getConnection()->insert('api_attributes_page_data')
      ->fields([
        'submit_by',
        'page_data',
        'approver1_uid',
        'approver1_status',
        'approver2_uid',
        'approver2_status',
        'attribute_status',
        'created',
        'updated',
      ])
      ->values([1, Json::encode($json_api_attribute), 1, 2, 1, 2, 2, \Drupal::time()->getRequestTime(), \Drupal::time()->getRequestTime()])
      ->execute();

    $this->output()->writeln("Attibutes Data created successfully");
  }

}
