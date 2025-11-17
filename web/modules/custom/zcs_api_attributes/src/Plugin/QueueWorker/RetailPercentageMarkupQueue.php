<?php

namespace Drupal\zcs_api_attributes\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\group\Entity\Group;
use Drupal\Component\Serialization\Json;

/**
 * Plugin implementation of the non passthrough proxy status queueworker.
 *
 * @QueueWorker (
 *   id = "retail_markup_percentage",
 *   title = @Translation("Retail Markup Percentage"),
 *   cron = {"time" = 120}
 * )
 */
class RetailPercentageMarkupQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    \Drupal::logger('RMP-step-3')->notice("Processing queue for parnter ID: $item->gid");
    $data_for_calculation = [];
    // Step: 2: Get the list of analytic nodes for the respective partners. 
    $date_value = \Drupal::config('zcs_custom.settings')->get('rmp_limit') ?: '-1 months';
    $date_filter = strtotime($date_value);
    $nids = \Drupal::entityQuery('node')
    ->condition('type', 'analytics')
    ->condition('field_partner.target_id', $item->gid)
    ->condition('field_date', date('Y-m-d\TH:i:s', $date_filter), '>=')
    ->accessCheck()
    ->execute();
    if ($nids) {
        \Drupal::logger('RMP-step-3-count')->notice('Nidfor GID ' . $item->gid . ': ' . count($nids));  
        $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
        foreach ($nodes as $node) {
            $target = $node->get('field_attribute')->entity;
            $attribute_title = $target ? $target->label() : NULL;
            $data_for_calculation[] = [
                'nid'        => $node->id(),
                'title'      => $node->label(),
                'parnter_title' => $item->partner_title,
                'from_gid'   => $item->gid,
                'node_gid'   => $node->get('field_partner')->target_id,
                'api_attribute'  => $node->get('field_attribute')->target_id,
                'api_attribute_title' => $attribute_title,
                'price_type' => $item->price_type,
                'date' => $node->get('field_date')->value,
            ];
        }
    }


    $rate_sheet_result = $this->getProposedPricingSheetData();
    if (!empty($rate_sheet_result)) {
      $retail_markup_percentage = $rate_sheet_result->retail_markup_percentage ?? 1;
      $consolidate_price_sheet = $this->formatRateSheetDetails($rate_sheet_result->page_data);
      $perform_calculation = $this->calculationForAnalyticsRevenue($retail_markup_percentage, $consolidate_price_sheet, $data_for_calculation);
      $result = $this->performCalucluationRevenue($perform_calculation);
      return $result;
    }
  }



  public function getProposedPricingSheetData(){
    \Drupal::logger('RMP-step-4')->notice("Process the proposed pricing sheet:");  
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
    \Drupal::logger('RMP-step-5')->notice("Format the rate sheet"); 
    $result = [];
    $rate_sheet = json::decode($rate_sheet_result);
    foreach($rate_sheet as $type => $rate_type_value) {
        foreach($rate_type_value as $key => $price) {
        $node = \Drupal\node\Entity\Node::load($key);
            if ($node) {
                $result[] = [
                    'api_attribute_title' => $node->getTitle(),
                    'price' => $price,
                    'price_type' => $type,
                ];
            } 
        }
    }    
  return $result;
 }


 public function calculationForAnalyticsRevenue($retail_markup_percentage, $consolidate_price_sheet, $data_for_calculation) {
  $calculate_revenue = [];
    foreach ($data_for_calculation as $a1) {
        foreach ($consolidate_price_sheet as $a2 => $val) {
            if($val['price_type'] == "international") {
                $price_type = "international_pricing";
            }
            if($val['price_type'] == "domestic") {
                $price_type =  "domestic_pricing";
            }
            if (
                $a1['api_attribute_title'] === $val['api_attribute_title'] &&
                $a1['price_type'] === $price_type
            ) {
                // Build combined result
                $calculate_revenue[] = [
                    'nid' => $a1['nid'],
                    'title' => $a1['title'],
                    'api_attribute_title' => $a1['api_attribute_title'],
                    'price_type' => $a1['price_type'],
                    'price' => $val['price'],
                    'date' => $a1['date'],
                    'retail_markup_percentage' => $retail_markup_percentage,
                ];
            }
        }
    }
    \Drupal::logger('RMP-step-6')->notice("Analytics Revenue data");
    return $calculate_revenue;
  }



  public function performCalucluationRevenue($perform_calculation_data) {
    foreach ($perform_calculation_data as $data) {
        $pricing = $data['price'];
        $markup_percentage = $data['retail_markup_percentage'];
        $final_pricing = $pricing * (1 + ($markup_percentage / 100));
        $node = \Drupal\node\Entity\Node::load($data['nid']);
        if ($node) {
            \Drupal::logger('RMP-step-6-calculation')->notice('NID: @nid, Retail%: @retail_value,  price: @price, Attribute: @attribute_title',
            array(
                '@price' => $data['price'],
                '@nid' => $data['nid'],
                '@retail_value' => $data['retail_markup_percentage'],
                '@attribute_title'=> $data['api_attribute_title'],
            ));

            $node->set('field_est_revenue', $final_pricing);
            $node = $node->save();
            //return TRUE;
        }
        else {
            \Drupal::logger('RMP-Failure')->notice("FAILURE FOR NODE:");
        }
    }
    return TRUE;

}

}
