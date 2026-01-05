<?php

namespace Drupal\zcs_api_attributes\Services;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\group\Entity\Group;

/**
 * Provides RetailMarkupPercentage Calculation.
 */
class DiscountPriceSheet  {

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
  public function DiscountPrice() {
    // step -1 Get the discount table id from the custom table. 
    $pricing_id = 9;
    $data = $this->database->select('discount_pricing_page_data', 'dppd')
    ->fields('dppd', ['page_data', 'client_id', 'client_name'])
    ->condition('attribute_status', 2) // approved
    ->condition('id', $pricing_id)
    ->execute()->fetchObject();
    if($data->client_id) {
    // step-2 : get the analytics node for the particular client id:
      \Drupal::logger('discount-step-2-count')->notice('fetching Nidfor GID ' . $data->client_id);  
      $date_value = \Drupal::config('zcs_custom.settings')->get('rmp_limit') ?: '-2 months';
      $date_value = '-10 months';
      $date_filter = strtotime($date_value);
      $nids = \Drupal::entityQuery('node')
      ->condition('type', 'analytics')
      ->condition('field_partner.target_id', $data->client_id)
      ->condition('field_date', date('Y-m-d\TH:i:s', $date_filter), '>=')
      ->range(0, 10)
      ->accessCheck(FALSE)
      ->execute();
    }
    if ($nids) {
      \Drupal::logger('discount-step-3-count')->notice('Nidfor GID ' . $data->client_id . ': ' . count($nids));  
      $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
      foreach ($nodes as $node) {
        $group = Group::load($data->client_id);
        $price_type = $group->get('field_pricing_type')->value;
        $target = $node->get('field_attribute')->entity;
        $attribute_title = $target ? $target->label() : NULL;
        $data_for_calculation[] = [
          'nid'        => $node->id(),
          'title'      => $node->label(),
          'parnter_title' => $data->client_name,
          'from_gid'   => $data->client_id,
          'node_gid'   => $node->get('field_partner')->target_id,
          'api_attribute'  => $node->get('field_attribute')->target_id,
          'api_attribute_title' => $attribute_title,
          'price_type' => $price_type,
          'date' => $node->get('field_date')->value,
        ];
      }
    }
    else {
      \Drupal::logger('discount-step-3-count')->error('no-nids-found');  
    }
    $rate_sheet_result = $this->getProposedPricingSheetData();
    if (!empty($rate_sheet_result)) {
      $retail_markup_percentage = $rate_sheet_result->retail_markup_percentage ?? 1;
      $consolidate_price_sheet = $this->formatRateSheetDetails($rate_sheet_result->page_data);
      $perform_calculation = $this->calculationForAnalyticsRevenue($retail_markup_percentage, $consolidate_price_sheet, $data_for_calculation, $data->page_data);
      dump($perform_calculation);
      //die;
      $result = $this->performCalucluationRevenue($perform_calculation);
      return $result;
    }
  }


  public function getProposedPricingSheetData(){
    \Drupal::logger('discount-step-4')->notice("Process the proposed pricing sheet:");  
    $rate_sheet = '';
    $today = date('Y-m-d');
    $query = \Drupal::database()->select('attributes_page_data', 'apd');
    $query->fields('apd');
    $query->condition('apd.effective_date', $today, '<=');
    $query->condition('apd.attribute_status', 2);  
    $query->orderBy('apd.effective_date', 'DESC');          
    $query->range(0, 1);                                
    $result = $query->execute()->fetchObject();
    return $result;
  }


  public function formatRateSheetDetails($rate_sheet_result){
    \Drupal::logger('discount-step-5')->notice("Format the rate sheet"); 
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
    return $result;
  }


  public function calculationForAnalyticsRevenue($retail_markup_percentage, $consolidate_price_sheet, $data_for_calculation, $discount_sheet) {
    $discount_price_sheet =json::decode($discount_sheet);
    $calculate_revenue = [];
        foreach ($data_for_calculation as $a1) {
            foreach ($consolidate_price_sheet as $a2 => $val) {
              if($val['price_type'] == "international") {
                  $price_type = "international_pricing";
              }
              if($val['price_type'] == "domestic") {
                  $price_type =  "domestic_pricing";
              }
              if ($a1['api_attribute_title'] === $val['api_attribute_title'] && $a1['price_type'] === $price_type) {
                  // Build combined result
                  $calculate_revenue[] = [
                      'nid' => $a1['nid'],
                      'title' => $a1['title'],
                      'client_id' => $a1['node_gid'],
                      'client_name' => $a1['parnter_title'],
                      'api_attribute_title' => $a1['api_attribute_title'],
                      'price_type' => $a1['price_type'],
                      'price' => $val['price'],
                      'date' => $a1['date'],
                      'discount_price' => $discount_price_sheet[$val['nid']]['discount_pricing'],
                      'retail_markup_percentage' => $retail_markup_percentage,
                  ];
              }
              else {
                  \Drupal::logger('discount-step-6-error')->error("pricing is not tagged with partner");
              }
            }
        }
        \Drupal::logger('discount-step-6')->notice("Analytics Revenue data");
        return $calculate_revenue;
    }


    public function performCalucluationRevenue($perform_calculation_data) {
      foreach ($perform_calculation_data as $data) {
          $pricing = $data['price'];
          $markup_percentage = $data['retail_markup_percentage'];
          $final_pricing = $pricing * (1 + ($markup_percentage / 100));
          $node = \Drupal\node\Entity\Node::load($data['nid']);
          if ($node) {
              \Drupal::logger('discount-step-6-calculation')->notice('NID: @nid, Retail%: @retail_value,  price: @price, Attribute: @attribute_title',
              array(
                  '@price' => $data['price'],
                  '@nid' => $data['nid'],
                  '@retail_value' => $data['retail_markup_percentage'],
                  '@attribute_title'=> $data['api_attribute_title'],
              ));
  
              $node->set('field_est_revenue', $final_pricing);
              $node = $node->save();
          }
          else {
              \Drupal::logger('discount-step-6')->notice("FAILURE FOR NODE:");
          }
      }
      return TRUE;
  }
}