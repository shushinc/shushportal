<?php

namespace Drupal\analytics\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\ExtensionInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Custom twig functions.
 */
class CustomTwig extends AbstractExtension implements ExtensionInterface {

  public function getFilters() {
    return [
      new TwigFilter('convert_number', [$this, 'convertNumber']),
    ];
  }

  public function convertNumber($value) {
    return number_format($value);
  }
}