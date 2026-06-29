<?php

namespace Drupal\zcs_api_attributes\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for number formatting.
 */
class NumberFormatExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('number_format_range', [$this, 'formatRangeNumber']),
    ];
  }

  /**
   * Formats a number with thousands separators for rate sheet ranges.
   *
   * @param mixed $value
   *   The value to format.
   *
   * @return string
   *   The formatted number.
   */
  public function formatRangeNumber($value) {
    // Handle special cases
    if ($value === '' || $value === NULL) {
      return '0';
    }

    // Convert to float
    $numericValue = floatval($value);

    // Special case for -1 (unbounded)
    if ($numericValue == -1) {
      return '∞';
    }

    // Format with up to 3 decimal places, removing trailing zeros
    $formatted = number_format($numericValue, 3, '.', ',');
    
    // Remove trailing zeros after decimal point
    $formatted = rtrim($formatted, '0');
    $formatted = rtrim($formatted, '.');

    return $formatted;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'zcs_api_attributes.number_format';
  }

}
