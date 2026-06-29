<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\zcs_api_attributes\Service\ApiAccessTokenService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for API Access Token administration.
 */
class ApiAccessTokenController extends ControllerBase {

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
   * Constructs an ApiAccessTokenController object.
   *
   * @param \Drupal\zcs_api_attributes\Service\ApiAccessTokenService $token_service
   *   The token service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ApiAccessTokenService $token_service,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->tokenService = $token_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('zcs_api_attributes.api_access_token_service'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays the list of API access tokens.
   *
   * @return array
   *   A render array.
   */
  public function listTokens(): array {
    $tokens = $this->tokenService->loadAllTokens();

    $rows = [];
    foreach ($tokens as $token) {
      $client = $this->loadClient($token->client_id);
      $client_label = $client ? $client->label() : $this->t('Unknown');

      $last_used = $token->last_used 
        ? date('Y-m-d H:i:s', $token->last_used) 
        : $this->t('Never');

      $operations = [
        'view' => [
          'title' => $this->t('View'),
          'url' => Url::fromRoute('zcs_api_attributes.api_access_token.view', ['token_id' => $token->id]),
        ],
        'regenerate' => [
          'title' => $this->t('Regenerate'),
          'url' => Url::fromRoute('zcs_api_attributes.api_access_token.regenerate', ['token_id' => $token->id]),
        ],
      ];

      if ($token->active) {
        $operations['disable'] = [
          'title' => $this->t('Disable'),
          'url' => Url::fromRoute('zcs_api_attributes.api_access_token.disable', ['token_id' => $token->id]),
        ];
      }
      else {
        $operations['enable'] = [
          'title' => $this->t('Enable'),
          'url' => Url::fromRoute('zcs_api_attributes.api_access_token.enable', ['token_id' => $token->id]),
        ];
      }

      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('zcs_api_attributes.api_access_token.delete', ['token_id' => $token->id]),
      ];

      $rows[] = [
        'name' => $token->name,
        'client' => $client_label,
        'prefix' => $token->token_prefix,
        'expires' => $this->tokenService->getExpirationLabel($token),
        'status' => $this->tokenService->getStatusLabel($token),
        'last_used' => $last_used,
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => $operations,
          ],
        ],
      ];
    }

    $build['tokens'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Client'),
        $this->t('Prefix'),
        $this->t('Expires'),
        $this->t('Status'),
        $this->t('Last Used'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No API access tokens found.'),
    ];

    return $build;
  }

  /**
   * Displays details of a single token.
   *
   * @param int $token_id
   *   The token ID.
   *
   * @return array
   *   A render array.
   */
  public function viewToken(int $token_id): array {
    $token = $this->tokenService->loadToken($token_id);

    if (!$token) {
      $this->messenger()->addError($this->t('Token not found.'));
      return $this->redirect('zcs_api_attributes.api_access_token.list');
    }

    $client = $this->loadClient($token->client_id);
    $client_label = $client ? $client->label() : $this->t('Unknown');

    $created_by = $this->entityTypeManager->getStorage('user')->load($token->created_by);
    $changed_by = $token->changed_by ? $this->entityTypeManager->getStorage('user')->load($token->changed_by) : NULL;
    $revoked_by = $token->revoked_by ? $this->entityTypeManager->getStorage('user')->load($token->revoked_by) : NULL;

    $build = [
      '#type' => 'container',
    ];

    $build['details'] = [
      '#type' => 'table',
      '#rows' => [
        [$this->t('Name'), $token->name],
        [$this->t('Client'), $client_label],
        [$this->t('Prefix'), $token->token_prefix],
        [$this->t('Status'), $this->tokenService->getStatusLabel($token)],
        [$this->t('Expiration'), $this->tokenService->getExpirationLabel($token)],
        [$this->t('Last Used'), $token->last_used ? date('Y-m-d H:i:s', $token->last_used) : $this->t('Never')],
        [$this->t('Last Used IP'), $token->last_used_ip ?: $this->t('N/A')],
        [$this->t('Last Used User-Agent'), $token->last_used_user_agent ?: $this->t('N/A')],
        [$this->t('Created By'), $created_by ? $created_by->getDisplayName() : $this->t('Unknown')],
        [$this->t('Created Date'), date('Y-m-d H:i:s', $token->created_date)],
        [$this->t('Changed By'), $changed_by ? $changed_by->getDisplayName() : $this->t('N/A')],
        [$this->t('Changed Date'), $token->changed_date ? date('Y-m-d H:i:s', $token->changed_date) : $this->t('N/A')],
        [$this->t('Revoked By'), $revoked_by ? $revoked_by->getDisplayName() : $this->t('N/A')],
        [$this->t('Revoked Date'), $token->revoked_date ? date('Y-m-d H:i:s', $token->revoked_date) : $this->t('N/A')],
      ],
    ];

    return $build;
  }

  /**
   * Loads a client (Group) by ID.
   *
   * @param int $client_id
   *   The client ID.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The client group or NULL.
   */
  protected function loadClient(int $client_id) {
    try {
      return $this->entityTypeManager->getStorage('group')->load($client_id);
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
