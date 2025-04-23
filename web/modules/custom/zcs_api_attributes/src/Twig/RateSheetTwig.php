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

  public function getFilters() {
    return [
      new TwigFilter('convert_number', [$this, 'convertNumber']),
    ];
  }

  public function getFunctions() {
    return [
      new TwigFunction('fetch_attributes', [$this, 'fetchAttributes']),
    ];
  }

  public function convertNumber($value) {
    return number_format($value);
    // $suffix = '';
    // if ($value >= 0 && $value < 1000) {
    //   $vaFormat = floor($value);
    // } else if ($value >= 1000 && $value < 1000000) {
    //   $vaFormat = floor($value / 1000);
    //   $suffix = 'K';
    // } else if ($value >= 1000000 && $value < 1000000000) {
    //   $vaFormat = floor($value / 1000000);
    //   $suffix = 'M';
    // } else if ($value >= 1000000000 && $value < 1000000000000) {
    //   $vaFormat = floor($value / 1000000000);
    //   $suffix = 'B';
    // } else if ($value >= 1000000000000) {
    //   $vaFormat = floor($value / 1000000000000);
    //   $suffix = 'T';
    // }
    // return $vaFormat . $suffix;
  }

  public function fetchAttributes() {
    $contents = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    $final = [];
    if (!empty($contents)) {
      foreach ($contents as $content) {
        $final[$content->id()] = $content->title->value;
      }
    }
    return $final;
  }
}