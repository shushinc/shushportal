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
   * Returns pricing ranges for a client's active rate sheet.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with rate sheet ranges or error.
   */
  public function getRanges(Request $request): JsonResponse {
    $authorization = $request->headers->get('Authorization');

    if (empty($authorization)) {
      return new JsonResponse(
        ['error' => 'Authorization header is required'],
        401
      );
    }

    $auth_result = $this->tokenService->validateToken($authorization);

    if (!$auth_result) {
      return new JsonResponse(
        ['error' => 'Invalid or expired token'],
        401
      );
    }

    $client_id = $auth_result['client_id'];

    $data = json_decode($request->getContent(), TRUE);

    if (!is_array($data)) {
      return new JsonResponse(
        ['error' => 'Invalid JSON request body'],
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

    try {
      $client = $this->entityTypeManager->getStorage('group')->load($client_id);

      if (!$client) {
        return new JsonResponse(
          ['error' => 'Client not found'],
          404
        );
      }

      $contact_name = $client->hasField('field_contact_name') 
        ? $client->get('field_contact_name')->value 
        : '';
      
      $contact_email = $client->hasField('field_contact_email') 
        ? $client->get('field_contact_email')->value 
        : '';

      $result = $this->rateSheetService->getClientRateSheetRanges(
        $contact_name,
        $contact_email,
        $data['attributes']
      );

      if ($result === NULL) {
        return new JsonResponse(
          ['error' => 'No active rate sheet available for this client'],
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
        ['error' => 'Internal server error'],
        500
      );
    }
  }

}
