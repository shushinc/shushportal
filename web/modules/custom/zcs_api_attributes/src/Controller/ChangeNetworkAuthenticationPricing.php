<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Link;

class ChangeNetworkAuthenticationPricing extends ControllerBase {
  public function pricingPage() {

    $contents = $this->entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        if ($content->field_api_attributes_status->target_id) {
          $titles[$content->field_api_attributes_status->target_id]['name'] = $this->entityTypeManager()->getStorage('taxonomy_term')->load($content->field_api_attributes_status->target_id)->name->value;
          $url = Url::fromRoute('zcs_api_attributes.change_network_authentication_pricing_update', ['id' => $content->id()]);
          $url->setOptions([
            'attributes' => [
              'class' => ['use-ajax'], // Enables AJAX
              'data-dialog-type' => 'modal', // Opens in a modal
              'data-dialog-options' => json_encode([
                'width' => 400,
              ]),
            ],
          ]);
          $final[$content->field_api_attributes_status->target_id][] = [
            'title' => $content->title->value,
            'price_per_call' => $content->field_price_per_call->value ?? 0,
            'url' => Link::fromTextAndUrl('Edit', $url),
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
    return [
      '#theme' => 'change_network_authentication_pricing',
      '#content' => $data,
      '#attached' => [
        'library' => ['zcs_api_attributes/attributes-page']
      ]
    ];
  }

  private function getAttributeValue($attributeValue){
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
}