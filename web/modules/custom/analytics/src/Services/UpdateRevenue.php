<?php

namespace Drupal\analytics\Services;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\group\Entity\Group;

/**
 * Provides Update Revenue.
 */
class UpdateRevenue  {

  protected $database;


   /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  
  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function AnalyticsUpdate($node) {
    $nid = $node->id();
    \Drupal::logger('revenue-update-step-1')->notice("Revenue update for Analytics nid:$nid");
    $result = '';
    if (!$node->get('field_attribute')->isEmpty()) {
      $referenced_entity = $node->get('field_attribute')->entity;
      $attribute_title = $referenced_entity->label();
    }
    if (!$node->get('field_partner')->isEmpty()) {
      /** @var \Drupal\group\Entity\GroupInterface $group */
      $group = $node->get('field_partner')->entity;
      $group_title = $group->label();
      $pricing_type = $group->get('field_pricing_type')->value;
    }
    $gid = $node->get('field_partner')->target_id;
    $revenue_node_calculation[] = [
        'nid'        => $node->id(),
        'title'      => $node->label(),
        'parnter_title' => $group_title,
        'gid'   => $gid,
        'api_attribute'  => $node->get('field_attribute')->target_id,
        'api_attribute_title' => $attribute_title ?? '' ,
        'price_type' => $pricing_type,
        'date' => $node->get('field_date')->value,
        'full_billable_transaction_count' => $node->get('field_success_api_volume_in_mil')->value,
        'half_billable_transaction_count' => $node->get('field_error_api_volume_in_mil')->value,
     ];
     $rate_sheet_result = $this->getProposedPricingSheetData();
     $data = $this->getDiscountSheetData($gid);
     if (!empty($rate_sheet_result)) {
      $retail_markup_percentage = $rate_sheet_result->retail_markup_percentage ?? 1;
      $consolidate_price_sheet = $this->formatRateSheetDetails($rate_sheet_result->page_data);
      $perform_calculation = $this->calculationForAnalyticsRevenue($retail_markup_percentage, $consolidate_price_sheet, $revenue_node_calculation, $data->page_data);
      $result = $this->performCalucluationRevenue($perform_calculation);
    }
    return $result;
  }



  public function getDiscountSheetData($gid) {
    \Drupal::logger('revenue-update-step-3')->notice("Process discount sheet");
    $data = $this->database->select('discount_pricing_page_data', 'dppd')
      ->fields('dppd', ['page_data', 'client_id', 'client_name'])
      ->condition('attribute_status', 2) // approved
      ->condition('client_id', $gid)
      ->orderBy('updated', 'DESC')       // latest first
      ->range(0, 1)                      // limit 1
      ->execute()
      ->fetchObject();
    if (!$data) {
      \Drupal::logger('revenue-update-step-3-failure')->notice("Process discount sheet failure"); 
      return NULL; // or false
    }
    return $data;
  }

  


  public function getProposedPricingSheetData(){
    \Drupal::logger('revenue-update-step-2')->notice("Process the proposed pricing sheet:");  
    $rate_sheet = '';
    $today = date('Y-m-d');
    $query = \Drupal::database()->select('attributes_page_data', 'apd');
    $query->fields('apd');
    $query->condition('apd.effective_date', $today, '<=');
    $query->condition('apd.attribute_status', 2);  
    $query->orderBy('apd.effective_date', 'DESC');          
    $query->range(0, 1);                                
    $result = $query->execute()->fetchObject();
    if (!$result) {
     \Drupal::logger('revenue-update-step-2-failure')->notice("No proposed API Pricinsheet:"); 
      return NULL; // or false
    }
    return $result;
  }


  public function formatRateSheetDetails($rate_sheet_result){
    \Drupal::logger('revenue-update-step-4')->notice("Format the rate sheet"); 
    $result = [];
    $rate_sheet = json::decode($rate_sheet_result);
    foreach($rate_sheet as $type => $rate_type_value) {
        foreach($rate_type_value as $key => $price) {
        $node = \Drupal\node\Entity\Node::load($key);
            if ($node) {
                $result[] = [
                    'nid' => $node->id(), 
                    'api_attribute_title' => $node->getTitle(),
                    'price' => $price,
                    'price_type' => $type,
                ];
            } 
        }
    }   
    \Drupal::logger('revenue-update-step-4-result')->notice(
      'Formatted rate sheet result: <pre>@result</pre>',
      ['@result' => print_r($result, TRUE)]
    );
    return $result;
  }


  public function calculationForAnalyticsRevenue($retail_markup_percentage, $consolidate_price_sheet, $data_for_calculation, $discount_sheet) {
    \Drupal::logger('revenue-update-step-5')->notice("calculation data for analytics revenue");
    $discount_price_sheet =json::decode($discount_sheet);
    $calculate_revenue = [];
    foreach ($data_for_calculation as $node) {
      foreach ($consolidate_price_sheet as $a2 => $val) {
        if ($val['price_type'] == "international") {
            $price_type = "international_pricing";
        }
        if($val['price_type'] == "domestic") {
            $price_type =  "domestic_pricing";
        }
        if ($node['api_attribute_title'] === $val['api_attribute_title'] && $node['price_type'] === $price_type) {
          \Drupal::logger('revenue-update-step-5.1')->notice("calculation attribute:" .$node['api_attribute_title']);
          // Build combined result
          $calculate_revenue[] = [
              'nid' => $node['nid'],
              'title' => $node['title'],
              'client_id' => $node['gid'],
              'client_name' => $node['parnter_title'],
              'api_attribute_title' => $node['api_attribute_title'],
              'price_type' => $node['price_type'],
              'price' => $val['price'],
              'date' => $node['date'],
              'discount_price' => $discount_price_sheet[$val['nid']]['discount_pricing'],
              'retail_markup_percentage' => $retail_markup_percentage,
              'full_billable_transaction_count' => $node['full_billable_transaction_count'],
              'half_billable_transaction_count' => $node['half_billable_transaction_count'],
          ];
        }
      }
    }
    \Drupal::logger('revenue-update-step-5-response')->notice(
      'calculationForAnalyticsRevenue: <pre>@result</pre>',
      ['@result' => print_r($calculate_revenue, TRUE)]
    );
    return $calculate_revenue;
  }


    public function performCalucluationRevenue($perform_calculation_data) {
      \Drupal::logger('revenue-update-step-6')->notice("Peform_calculation");
      $final_pricing = '';
      foreach ($perform_calculation_data as $data) {
        $pricing_per_api_call = $data['price'];
        $pricing = ($data['full_billable_transaction_count'] * $pricing_per_api_call) + 
        ($data['half_billable_transaction_count'] * $pricing_per_api_call / 2);
        $markup_percentage = $data['retail_markup_percentage'];
        $final_pricing = $pricing * (1 + ($markup_percentage / 100)) - ($data['discount_price']/100);
        \Drupal::logger('discount-step-6-calculation')->notice(
            'NID: @nid, 
            Retail%: @retail_value,
            price per api call: @price,
            Attribute: @attribute_title,
            Discount Price: @discount_price,
            Final Price: @final_pricing,
            Full bill: @full_billable_amount,
            half bill: @half_billable_amount',
          array(
              '@price' => $data['price'],
              '@nid' => $data['nid'],
              '@retail_value' => $data['retail_markup_percentage'],
              '@attribute_title'=> $data['api_attribute_title'],
              '@discount_price' => $data['discount_price'],
              '@final_pricing' => $final_pricing,
              '@full_billable_amount' => $data['full_billable_transaction_count'],
              '@half_billable_amount' => $data['half_billable_transaction_count'],
          ));
      }
      return $final_pricing;
    }
}