<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Link;

class AttributesPageController extends ControllerBase {
  public function attributesPage() {
    $contents = $this->entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        $final[$content->id()][] = [
          'title' => $content->title->value,
          'integrated_carrier_network' => $this->getAttributeValue($content->field_successfully_integrated_cn->value),
          'to_be_used' => $this->getAttributeValue($content->field_able_to_be_used->value),
        ];
      }
    }
    $data['final'] = $final;
    return [
      '#theme' => 'attributes_page',
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