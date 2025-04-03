<?php

namespace Drupal\metalite\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\metalite\Service\MetaliteService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for Metalite dashboards.
 */
class MetaliteController extends ControllerBase {

  /**
   * The Metalite API service.
   *
   * @var \Drupal\metalite\Service\MetaliteService
   */
  protected $metaliteApiService;

  /**
   * Constructs a new MetaliteController object.
   *
   * @param \Drupal\metalite\Service\MetaliteService $metalite_api_service
   *   The Metalite API service.
   */
  public function __construct(MetaliteService $metalite_api_service) {
    $this->metaliteApiService = $metalite_api_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('metalite.api_service')
      );
  }

  /**
   * Displays a Metalite dashboard.
   *
   * @return array|RedirectResponse
   *   A render array for the dashboard or a redirect if there's an error.
   */
  public function dashboard() {
    // Get the embed URL from the API service.
    $embed_url = $this->metaliteApiService->getEmbedUrl();

    if (empty($embed_url)) {
      $this->messenger()->addError($this->t('Unable to load the Metalite dashboard. Please check your configuration.'));
      // Return new RedirectResponse(Url::fromRoute('<front>')->toString());
    }

    // Return a render array with an iframe.
    return [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'src' => $embed_url,
        'frameborder' => '0',
        'width' => '100%',
        'height' => '800px',
        'allowfullscreen' => 'true',
        'title' => $this->t('Metalite Dashboard'),
      ],
      '#cache' => [
        'contexts' => ['url.path'],
      // Cache for 1 hour.
        'max-age' => 3600,
      ],
    ];
  }

}
