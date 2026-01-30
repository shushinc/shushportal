<?php

namespace Drupal\zcs_client_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\group\Entity\Group;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use NumberFormatter;
use Drupal\Component\Serialization\Json;


/**
 * Class ViewClientController.
 */
class ViewClientController extends ControllerBase {

  /**
   * Handleautocomplete.
   *
   * @return string
   *   Return users
   */
  public function view($gid) {

    $group = Group::load($gid);
    $client_name = $group->get('label')->value ?? '';
    $contact_name = $group->get('field_contact_name')->value ?? '';
    $contact_email = $group->get('field_contact_email')->value ?? '';
    $status = $group->get('field_partner_status')->value ?? '';
    $pricing_type = $group->get('field_pricing_type')->value ?? '';

    $field_config_partner_type = FieldConfig::load('group.partner.field_partner_type');
    if ($field_config_partner_type) {
      $partner_type_values = $field_config_partner_type->getSetting('allowed_values');
    }

    $field_config_partner_status = FieldConfig::load('group.partner.field_partner_status');
    if ($field_config_partner_status) {
      $partner_status_values = $field_config_partner_status->getSetting('allowed_values');
    }

    $field_config_partner_industries = FieldConfig::load('group.partner.field_industry');
    if ($field_config_partner_industries) {
      $partner_industries_values = $field_config_partner_industries->getSetting('allowed_values');
    }

    $field_config_pricing_type = FieldConfig::load('group.partner.field_pricing_type');
    if ($field_config_pricing_type) {
      $pricing_type_values = $field_config_pricing_type->getSetting('allowed_values');
    }

    $currency =  $group->get('field_currency')->value ?? '';
    $number = new NumberFormatter($currency  ?? 'en_US', NumberFormatter::CURRENCY);
    $symbol = $number->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
    $address = $group->get('field_address')->getValue();
    $address_value = isset($address[0]) ? $address[0]: '';

    $address_line1 = $address_line2 = $address_line3 = $postal_code = $country_code = '';
    if(!empty($address_value)) {
      $address_line1 = $address_value['address_line1'];
      $address_line2 = $address_value['address_line2'];
      $address_line3 = $address_value['address_line3'];
      $postal_code = $address_value['postal_code'];
      $country_code = $address_value['country_code'];
    }

    $description = strip_tags($group->get('field_description')->value) ?? '';
    $client_legal_contact = $group->get('field_client_legal_contact')->value ?? '';
    $legal_email = $group->get('field_client_point_of_contact')->value ?? '';

    $type = $group->get('field_partner_type')->value ?? '';
    $industry =  $group->get('field_industry')->value ?? '';
    $aggreement_effective_date = $group->get('field_agreement_effective_date')->value ?? '';

    $prepayment_amount = $group->get('field_prepayment_amount')->value ?? '';
    $prepayment_balance_left = $group->get('field_prepayment_balance_left')->value ?? '';
    $prepayment_balance_used =  $group->get('field_prepayment_balance_used')->value ?? '';

    $api_aggreement_covers_array = [];
    if(!empty($group->get('field_apis_agreement_covers')->value)) {
      $api_aggreement_covers_array = json::decode($group->get('field_apis_agreement_covers')->value);
    }
    //dump($api_aggreement_covers_array);
    $api_covers = [];
   // $contents =  \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    $nids = \Drupal::entityQuery('node')
    ->condition('type', 'api_attributes')
    ->sort('field_attribute_weight', 'ASC')
    ->accessCheck()
    ->execute();
    /** @var \Drupal\node\NodeInterface[] */
    $contents = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    if (!empty($contents)) {
      foreach ($contents as $content) {
         $api_covers[$content->getTitle()] = isset($api_aggreement_covers_array[$content->id()]) ? $api_aggreement_covers_array[$content->id()] : 0;
      }
    }

    $module_path = \Drupal::service('extension.list.module')->getPath('zcs_client_management');
    $image_url = '/' . $module_path . '/images/client_view.png';
    return [
      '#theme' => 'client_view',
      '#client_name' => $client_name,
      '#contact_name' => $contact_name,
      '#contact_email' => $contact_email,
      '#status' =>   $partner_status_values[$status],
      '#description' => $description,
      '#client_legal_contact' => $client_legal_contact,
      '#legal_email' => $legal_email,
      '#type' => isset($partner_type_values[$type]) ? $partner_type_values[$type] : '',
      '#industry' => isset($partner_industries_values[$industry]) ? $partner_industries_values[$industry] : '',
      '#agreement_effective_date' => $aggreement_effective_date,
      '#pricing_type' => $pricing_type_values[$pricing_type] ?? '',
      '#currency' => $symbol,
      '#prepayment_amount' => $prepayment_amount,
      '#prepayment_balance_left' => $prepayment_balance_left,
      '#prepayment_balance_used' => $prepayment_balance_used,
      '#address_line_1' => $address_line1,
      '#address_line_2' => $address_line2,
      '#address_line_3' => $address_line3,
      '#country_code' => $country_code,
      '#postal_code' => $postal_code,
      '#agreement_covers' => $api_covers,
      '#image_url' => $image_url,
      '#cache' => [
        'max-age' => 0,
      ],
      '#attached' => [
        'library' => ['zcs_client_management/client-view-page']
      ]
    ];
  }

}
