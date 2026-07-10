<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\zcs_api_attributes\Service\ApiAccessTokenService;
use Drupal\zcs_api_attributes\Service\RateSheetService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for Client Rate Sheet API endpoints.
 */
class ClientRateSheetApiController extends ControllerBase {

  /**
   * The rate sheet service.
   *
   * @var \Drupal\zcs_api_attributes\Service\RateSheetService
   */
  protected $rateSheetService;

  /**
   * The API access token service.
   *
   * @var \Drupal\zcs_api_attributes\Service\ApiAccessTokenService
   */
  protected $tokenService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a ClientRateSheetApiController object.
   *
   * @param \Drupal\zcs_api_attributes\Service\RateSheetService $rate_sheet_service
   *   The rate sheet service.
   * @param \Drupal\zcs_api_attributes\Service\ApiAccessTokenService $token_service
   *   The token service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    RateSheetService $rate_sheet_service,
    ApiAccessTokenService $token_service,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->rateSheetService = $rate_sheet_service;
    $this->tokenService = $token_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('zcs_api_attributes.rate_sheet_service'),
      $container->get('zcs_api_attributes.api_access_token_service'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Processes aggregate payload and calculates pricing.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with enriched buckets.
   */
  public function getRanges(Request $request): JsonResponse {
    // Decode JSON payload
    $payload = json_decode($request->getContent(), TRUE);

    if (!is_array($payload)) {
      return new JsonResponse(
        ['error' => 'Invalid JSON request body'],
        400
      );
    }

    // Validate top-level structure
    if (!isset($payload['buckets']) || !is_array($payload['buckets'])) {
      return new JsonResponse(
        ['error' => 'Missing or invalid buckets array'],
        400
      );
    }

    if (empty($payload['buckets'])) {
      return new JsonResponse(
        ['error' => 'Buckets array cannot be empty'],
        400
      );
    }

    try {
      // Process payload through service
      $result = $this->rateSheetService->processAggregatePricing($payload);

      return new JsonResponse($result, 200);
    }
    catch (\Exception $e) {
      $this->getLogger('zcs_api_attributes')->error(
        'Error processing aggregate pricing: @message',
        ['@message' => $e->getMessage()]
      );

      return new JsonResponse(
        ['error' => 'Internal server error'],
        500
      );
    }
  }

}
