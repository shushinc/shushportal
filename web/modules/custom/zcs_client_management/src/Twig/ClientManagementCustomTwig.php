<?php

namespace Drupal\zcs_client_management\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\ExtensionInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Custom twig functions.
 */
class ClientManagementCustomTwig extends AbstractExtension implements ExtensionInterface {

  public function getFilters() {
    return [
      new TwigFilter('format_number', [$this, 'formatNumber']),
    ];
  }

  public function formatNumber($value) {
    $suffix = '';
    $vaFormat = '';
    if ($value >= 0 && $value < 1000) {
      $vaFormat = $value;
    } else if ($value >= 1000 && $value < 1000000) {
      $vaFormat = $value / 1000;
      $suffix = 'K';
    } else if ($value >= 1000000 && $value < 1000000000) {
      $vaFormat = $value / 1000000;
      $suffix = 'M';
    } else if ($value >= 1000000000 && $value < 1000000000000) {
      $vaFormat = $value / 1000000000;
      $suffix = 'B';
    } else if ($value >= 1000000000000) {
      $vaFormat = $value / 1000000000000;
      $suffix = 'T';
    }
    return $vaFormat . $suffix;
  }
}