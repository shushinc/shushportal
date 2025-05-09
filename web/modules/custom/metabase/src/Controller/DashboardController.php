<?php

namespace Drupal\metabase\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Drupal\metabase\Service\MetabaseService;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Controller for dashboards.
 */
class DashboardController extends ControllerBase {

  /**
   * The API service.
   *
   * @var \Drupal\metabase\Service\MetabaseService
   */
  protected $metabaseApiService;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DashboardController object.
   *
   * @param \Drupal\metabase\Service\MetabaseService $metabase_api_service
   *   The API service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    MetabaseService $metabase_api_service,
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->metabaseApiService = $metabase_api_service;
    $this->httpClient = $http_client;
    $this->logger = $logger_factory->get('metabase');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('metabase.service'),
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Displays a dashboard.
   *
   * @return array|RedirectResponse
   *   A render array for the dashboard or a redirect if there's an error.
   */
  public function dashboard() {

    $build = [
      '#cache' => [
        'contexts' => ['url.path'],
        'max-age' => 0,
      ],
      '#attached' => [
        'library' => [
          'metabase/iframe-resize'
        ]
      ],
    ];

    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    $frames = ['other'];
    if (in_array('administrator', $roles) || in_array('carrier_admin', $roles)) {
      $frames = ['top', 'main'];
    }

    foreach ($frames as $value) {
      // Get the embed URL from the API service.
      $embed_url = $this->getEmbedUrl($value);

      if (empty($embed_url)) {
        $this->messenger()->addError($this->t('Unable to load the @position dashboard. Please check your configuration.', ['@position' => $value]));
      }

      $build[$value] = [
        '#type' => 'html_tag',
        '#tag' => 'iframe',
        '#attributes' => [
          'src' => $embed_url,
          'frameborder' => '0',
          'width' => '100%',
          // 'height' => '800px',
          'allowfullscreen' => 'true',
          'title' => $this->t('Dashboard'),
          'allowtransparency' => 'true',
          'class' => [$value, 'mb-iframe'],
        ],
      ];
    }

    return $build;
  }

  /**
   * Get the embed URL for a dashboard.
   *
   * @return string|null
   *   The embed URL, or NULL if there was an error.
   */
  public function getEmbedUrl($position = 'top') {
    $config = \Drupal::config('metabase.settings');
    $base_url = \Drupal::request()->getSchemeAndHttpHost() . $config->get('embeding.base_url');
    $secket_key = $config->get('embeding.api_token');
    $dashboard_id = $config->get('embeding.dashboard.' . $position);

    // Ensure we have all required configuration.
    if (empty($base_url) || empty($secket_key) || empty($dashboard_id)) {
      $this->logger->error('Missing required configuration for API.');
      return NULL;
    }

    $params = [];
    $params = [
      'user_id' => \Drupal::currentUser()->id(),
    ];

    // Create JWT payload.
    $payload = [
      'resource' => ['dashboard' => (int) $dashboard_id],
      'params' => (object) $params,
      'exp' => time() + (60 * 10),
    ];

    try {
      // Generate token.
      $token = JWT::encode($payload, $secket_key, 'HS256');

      // Build the embed URL.
      $embed_url = $base_url . '/embed/dashboard/' . $token . '#background=false&bordered=false&titled=false';

      // Return the URL as JSON.
      return $embed_url;
    }
    catch (\Exception $e) {
      $this->logger->error('Error fetching embed URL from API: @error', ['@error' => $e->getMessage()]);
      return "";
    }
  }

}
