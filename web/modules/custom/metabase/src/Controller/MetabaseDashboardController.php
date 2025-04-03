<?php

namespace Drupal\metabase\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Firebase\JWT\JWT;

/**
 * Controller for the Metabase Dashboard embed URL generator.
 */
class MetabaseDashboardController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * MetabaseDashboardController constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('config.factory')
      );
  }

  /**
   * Generates a Metabase embed URL for a dashboard.
   *
   * @param int $dashboard_id
   *   The ID of the dashboard to embed.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the embed URL.
   */
  public function getEmbedUrl($dashboard_id) {
    // Get settings from configuration.
    $config = $this->configFactory->get('metabase.settings');
    $base_url = $config->get('api.base_url');
    $secket_key = $config->get('embeding.api_token');

    // Validate configuration.
    if (empty($base_url) || empty($secket_key)) {
      return new JsonResponse(
            ['error' => 'Metabase configuration is incomplete.'],
            400
        );
    }

    // Validate dashboard ID.
    if (!is_numeric($dashboard_id)) {
      return new JsonResponse(
            ['error' => 'Invalid dashboard ID.'],
            400
        );
    }

    // Create JWT payload.
    $payload = [
      'resource' => ['dashboard' => (int) $dashboard_id],
      'params' => [],
    // 10 minute expiration
      'exp' => time() + (60 * 10),
    ];

    try {
      // Generate token.
      $token = JWT::encode($payload, $secket_key, 'HS256');

      // Build the embed URL.
      $embed_url = $base_url . '/embed/dashboard/' . $token . '#bordered=true&titled=true';

      // Return the URL as JSON.
      return new JsonResponse(['embed_url' => $embed_url]);
    }
    catch (\Exception $e) {
      return new JsonResponse(
            ['error' => 'Failed to generate embed URL: ' . $e->getMessage()],
            500
        );
    }
  }

}
