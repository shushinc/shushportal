<?php

namespace Drupal\zcs_api_attributes\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\ExtensionInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Custom twig functions.
 */
class RateSheetTwig extends AbstractExtension implements ExtensionInterface {

  /**
   *
   */
  public function getFilters() {
    return [
      new TwigFilter('convert_number', [$this, 'convertNumber']),
      new TwigFilter('field_value', [$this, 'fieldValue']),
    ];
  }

  /**
   *
   */
  public function getFunctions() {
    return [
      new TwigFunction('fetch_attributes', [$this, 'fetchAttributes']),
    ];
  }

  /**
   *
   */
  public function convertNumber($value) {
    return number_format($value);
  }

  /**
   *
   */
  public function fieldValue($field) {
    return $field['#value'];
  }

  /**
   *
   */
  public function fetchAttributes() {
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'api_attributes')
      ->sort('field_attribute_weight', 'ASC')
      ->accessCheck()
      ->execute();
    $contents = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    // $final = [];
    $currency = \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US';
    // Show the right currency symbol based on the chosen one.
    $number = new \NumberFormatter($currency, \NumberFormatter::CURRENCY);
    $symbol = $number->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

    $titles = $prices = [];
    if (!empty($contents)) {
      foreach ($contents as $content) {
        // $final[$content->id()] = $content->title->value;
        $titles[$content->id()] = $content->title->value;
        $international_prices[$content->id()] = $symbol . (($content->field_standard_price->value ?? "0.000"));
        $domestic_prices[$content->id()] = $symbol . (($content->field_domestic_standard_price->value ?? "0.000"));
      }
    }
    // Return $final;.
    return compact('titles', 'international_prices', 'domestic_prices');
  }

}
