<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\zcs_api_attributes\Service\ApiAccessTokenService;
use Drupal\zcs_api_attributes\Service\RateSheetService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Password\PasswordInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

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
   * The passwordHasher manager.
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $passwordHasher;

  /**
   * The logger manager.
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a ClientRateSheetApiController object.
   *
   * @param \Drupal\zcs_api_attributes\Service\RateSheetService $rate_sheet_service
   *   The rate sheet service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Password\PasswordInterface $passwordHasher
   *   The Drupal password hasher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    RateSheetService $rate_sheet_service,
    EntityTypeManagerInterface $entity_type_manager,
    PasswordInterface $password_hasher,
    LoggerInterface $logger,
  ) {
    $this->rateSheetService = $rate_sheet_service;
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordHasher = $password_hasher;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('zcs_api_attributes.rate_sheet_service'),
      $container->get('entity_type.manager'),
      $container->get('password'),
      $container->get('logger.factory')->get('zcs_api_attributes'),
    );
  }

  /**
   * Processes aggregate payload and calculates pricing.
   *
   * Authentication is performed using HTTP Basic Authentication.
   * Only active Super Admin users are authorized.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with enriched buckets.
   */
  public function getRanges(Request $request): JsonResponse {

    $account = $this->authenticateSuperAdmin($request);

    if (!$account) {
      return new JsonResponse([
          'error' => 'Unauthorized',
          'message' => 'Valid Super Admin credentials are required.',
        ],
        401,
        ['WWW-Authenticate' => 'Basic realm="Moriarty Pricing API"',],
      );
    }

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


  /**
   * Authenticates an active Super Admin using HTTP Basic Authentication.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return \Drupal\user\UserInterface|null
   *   The authenticated account, or NULL when authentication fails.
   */
  protected function authenticateSuperAdmin(Request $request): ?UserInterface {
    $credentials = $this->extractBasicCredentials($request);

    if (!$credentials) {
      return NULL;
    }

    [$email, $password] = $credentials;

    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties([
        'mail' => $email,
        'status' => 1,
      ]);

    /** @var \Drupal\user\UserInterface|false $account */
    $account = reset($users);

    if (!$account instanceof UserInterface) {
      return NULL;
    }

    if (!$this->passwordHasher->check($password, $account->getPassword(),)) {
      return NULL;
    }

    if (!$this->isSuperAdmin($account)) {
      return NULL;
    }

    return $account;
  }

  /**
   * Extracts e-mail and password from the Basic Authorization header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return array|null
   *   An array containing e-mail and password, or NULL when invalid.
   */
  protected function extractBasicCredentials(Request $request): ?array {
    $authorization = $request->headers->get('Authorization');

    if (!$authorization || !str_starts_with($authorization, 'Basic ')) {
      return NULL;
    }

    $encoded_credentials = trim(substr($authorization, 6));
    $decoded_credentials = base64_decode($encoded_credentials, TRUE);

    if ($decoded_credentials === FALSE || !str_contains($decoded_credentials, ':')) {
      return NULL;
    }

    [$email, $password] = explode(':', $decoded_credentials, 2);

    $email = trim($email);

    if ($email === '' || $password === '') {
      return NULL;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return NULL;
    }

    return [$email, $password];
  }

  /**
   * Determines whether an account is a Super Admin.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   *
   * @return bool
   *   TRUE when the account is authorized as Super Admin.
   */
  protected function isSuperAdmin(UserInterface $account): bool {
    // Drupal's user 1 is the root administrator.
    if ((int) $account->id() === 1) {
      return TRUE;
    }

    // Allow users with the Administrator role.
    return $account->hasRole('administrator');
  }

}
