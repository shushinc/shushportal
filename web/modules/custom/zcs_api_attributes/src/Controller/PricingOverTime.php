<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Link;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PricingOverTime extends ControllerBase {


    /**
   * Connection $database.
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection) {
    $this->database = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  } 



  public function pricingPage() {

   $effective_date_1 = \Drupal::config('zcs_custom.api_attribute_settings')->get('effective_date_1');
   $effective_date_2 = \Drupal::config('zcs_custom.api_attribute_settings')->get('effective_date_2');
   $effective_date_3 = \Drupal::config('zcs_custom.api_attribute_settings')->get('effective_date_3');

    $contents = $this->entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        if ($content->field_api_attributes_status->target_id) {
          $titles[$content->field_api_attributes_status->target_id]['name'] = $this->entityTypeManager()->getStorage('taxonomy_term')->load($content->field_api_attributes_status->target_id)->name->value;
          $final[$content->field_api_attributes_status->target_id][] = [
            'title' => $content->title->value,
            'price_per_call' => $content->field_price_per_call->value ?? 0,
            'effective_date_1_price_per_call' => $content->field_effective_date1_price_call->value ?? 0,
            'effective_date_2_price_per_call' => $content->field_effective_date2_price_call->value ?? 0,
            'effective_date_3_price_per_call' => $content->field_effective_date3_price_call->value ?? 0,
          ];
        }
      }
    }
    $data['admin'] = 0;
    $roles = $this->currentUser()->getRoles();
    if (!empty(array_intersect(['administrator', 'carrier_admin'], $roles))) {
      $data['admin'] = 1;
    }
    $data['final'] = $final;
    $data['titles'] = $titles;
    $data['effective_date_1'] = $effective_date_1;
    $data['effective_date_2'] = $effective_date_2;
    $data['effective_date_3'] = $effective_date_3;

    return [
      '#theme' => 'network_authentication_pricing_over_time',
      '#content' => $data,
      '#attached' => [
        'library' => ['zcs_api_attributes/attributes-page']
      ]
    ];
  }

  private function getAttributeValue($attributeValue) {
    if($attributeValue == 'yes') {
       return Markup::create("<span class='attrib_yes'>Yes</span>");
    }
    elseif($attributeValue == 'no') {
      return Markup::create("<span class='attrib_no'>No</span>");
    }
    else {
      return '';
    }

  }

  /**
   * Display the Attributes Approval Page.
   */
  public function attributeApprovals() {
    $data = [];
    $resultSet = $this->database->select('attributes_page_data', 'apd')
      ->fields('apd', ['id', 'submit_by', 'approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status', 'attribute_status', 'created'])
      ->execute()
      ->fetchAll();
    $statusSet = $this->database->select('attribute_status', 'as')
      ->fields('as', ['id', 'status'])
      ->execute()
      ->fetchAll();
    foreach ($statusSet as $status) {
      $statuses[$status->id] = $status->status;
    }
    if (!empty($resultSet)) {
      foreach ($resultSet as $result) {
        $userStorage = $this->entityTypeManager()->getStorage('user');
        $data[] = [
          'id' => $result->id,
          'submitted' => $userStorage->load($result->submit_by)->mail->value,
          'approver1' => $userStorage->load($result->approver1_uid)->mail->value,
          'approver2' => $userStorage->load($result->approver2_uid)->mail->value,
          'approver1_status' => $statuses[$result->approver1_status],
          'approver2_status' => $statuses[$result->approver2_status],
          'status' => $statuses[$result->attribute_status],
          'requested_time' => date('Y-m-d', $result->created)
        ];
      }
    }
    return [
      '#theme' => 'rate_sheet_approval',
      '#content' => $data,
      '#attached' => [
        'library' => ['zcs_api_attributes/rate-sheet-approval']
      ]
    ];
  }
}