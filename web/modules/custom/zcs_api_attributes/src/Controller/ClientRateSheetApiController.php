<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Controller\ControllerBase;
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
   * Constructs a ClientRateSheetApiController object.
   *
   * @param \Drupal\zcs_api_attributes\Service\RateSheetService $rate_sheet_service
   *   The rate sheet service.
   */
  public function __construct(RateSheetService $rate_sheet_service) {
    $this->rateSheetService = $rate_sheet_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('zcs_api_attributes.rate_sheet_service')
    );
  }

  /**
   * Returns pricing ranges for a client's active rate sheet.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with rate sheet ranges or error.
   */
  public function getRanges(Request $request): JsonResponse {
    // Decode JSON request body.
    $data = json_decode($request->getContent(), TRUE);

    // Validate request.
    if (!is_array($data)) {
      return new JsonResponse(
        ['error' => 'Invalid JSON request body'],
        400
      );
    }

    if (empty($data['contactName'])) {
      return new JsonResponse(
        ['error' => 'contactName is required'],
        400
      );
    }

    if (empty($data['contactEmail'])) {
      return new JsonResponse(
        ['error' => 'contactEmail is required'],
        400
      );
    }

    if (!isset($data['attributes'])) {
      return new JsonResponse(
        ['error' => 'attributes is required'],
        400
      );
    }

    if (!is_array($data['attributes'])) {
      return new JsonResponse(
        ['error' => 'attributes must be an array'],
        400
      );
    }

    if (empty($data['attributes'])) {
      return new JsonResponse(
        ['error' => 'attributes cannot be empty'],
        400
      );
    }

    foreach ($data['attributes'] as $attribute) {
      if (!is_string($attribute)) {
        return new JsonResponse(
          ['error' => 'Every attribute must be a string'],
          400
        );
      }
    }

    // Call the service.
    try {
      $result = $this->rateSheetService->getClientRateSheetRanges(
        $data['contactName'],
        $data['contactEmail'],
        $data['attributes']
      );

      if ($result === NULL) {
        return new JsonResponse(
          ['error' => 'Client not found or no active rate sheet available'],
          404
        );
      }

      return new JsonResponse($result, 200);
    }
    catch (\Exception $e) {
      $this->getLogger('zcs_api_attributes')->error(
        'Error retrieving client rate sheet ranges: @message',
        ['@message' => $e->getMessage()]
      );

      return new JsonResponse(
        ['error' => $e->getMessage()],
        500
      );
    }
  }

}
