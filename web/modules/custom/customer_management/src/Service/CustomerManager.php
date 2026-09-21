<?php

namespace Drupal\customer_management\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Handles customer CRUD-related business logic.
 */
class CustomerManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The credential generator.
   *
   * @var \Drupal\customer_management\Service\CredentialGenerator
   */
  protected $credentialGenerator;

  /**
   * The history service.
   *
   * @var \Drupal\customer_management\Service\CustomerHistoryService
   */
  protected $historyService;

  /**
   * The mail service.
   *
   * @var \Drupal\customer_management\Service\CustomerMailService
   */
  protected $mailService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs the service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    CredentialGenerator $credential_generator,
    CustomerHistoryService $history_service,
    CustomerMailService $mail_service,
    AccountProxyInterface $current_user
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->credentialGenerator = $credential_generator;
    $this->historyService = $history_service;
    $this->mailService = $mail_service;
    $this->currentUser = $current_user;
  }

  /**
   * Returns demand partner options.
   */
  public function getDemandPartnerOptions(): array {
    $options = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'demand_partners',
    ]);

    foreach ($terms as $term) {
      $options[$term->id()] = $term->label();
    }

    asort($options);
    return $options;
  }

  /**
   * Creates a customer node with generated credentials.
   */
  public function createCustomer(array $values): array {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $client_id = $this->generateUniqueClientId();
    $hashed_key = $this->credentialGenerator->generateHashedKey();

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'customer',
      'title' => $values['customer_name'],
      'field_contact_name' => $values['contact_name'],
      'field_contact_email' => $values['contact_email'],
      'field_contact_phone' => $values['contact_phone'],
      'field_customer_client_id' => $client_id,
      'field_hashed_key' => $hashed_key,
      'field_demand_partners' => $this->normalizePartnerValues($values['demand_partners'] ?? []),
      'status' => 1,
    ]);
    $node->save();

    $email = (string) $node->get('field_contact_email')->value;
    if ($email !== '') {
      $this->mailService->sendCreatedEmail([
        'customer_name' => $node->label(),
        'contact_name' => (string) $node->get('field_contact_name')->value,
        'client_id' => $client_id,
        'hashed_key' => $hashed_key,
      ], $email);
    }

    $this->historyService->log((int) $node->id(), 'customer_created', (int) $this->currentUser->id(), [
      'email_sent_to' => $email,
      'client_id' => $client_id,
    ]);

    return [
      'node' => $node,
      'client_id' => $client_id,
      'hashed_key' => $hashed_key,
    ];
  }

  /**
   * Updates a customer node and logs meaningful changes.
   */
  public function updateCustomer(int $nid, array $values): NodeInterface {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node || $node->bundle() !== 'customer') {
      throw new \InvalidArgumentException('Invalid customer node.');
    }

    $before = [
      'customer_name' => $node->label(),
      'contact_name' => (string) $node->get('field_contact_name')->value,
      'contact_email' => (string) $node->get('field_contact_email')->value,
      'contact_phone' => (string) $node->get('field_contact_phone')->value,
      'demand_partners' => array_map('intval', array_column($node->get('field_demand_partners')->getValue(), 'target_id')),
    ];

    $node->setTitle($values['customer_name']);
    $node->set('field_contact_name', $values['contact_name']);
    $node->set('field_contact_email', $values['contact_email']);
    $node->set('field_contact_phone', $values['contact_phone']);
    $node->set('field_demand_partners', $this->normalizePartnerValues($values['demand_partners'] ?? []));
    $node->save();

    $after = [
      'customer_name' => $node->label(),
      'contact_name' => (string) $node->get('field_contact_name')->value,
      'contact_email' => (string) $node->get('field_contact_email')->value,
      'contact_phone' => (string) $node->get('field_contact_phone')->value,
      'demand_partners' => array_map('intval', array_column($node->get('field_demand_partners')->getValue(), 'target_id')),
    ];

    $changed_fields = [];
    foreach ($before as $key => $value) {
      if ($after[$key] != $value) {
        $changed_fields[] = $key;
      }
    }

    if ($changed_fields) {
      $this->historyService->log((int) $node->id(), 'customer_updated', (int) $this->currentUser->id(), [
        'changed_fields' => $changed_fields,
      ]);
    }

    return $node;
  }

  /**
   * Resets a customer's hashed key.
   */
  public function resetHashedKey(int $nid): NodeInterface {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node || $node->bundle() !== 'customer') {
      throw new \InvalidArgumentException('Invalid customer node.');
    }

    $new_key = $this->credentialGenerator->generateHashedKey();
    $node->set('field_hashed_key', $new_key);
    $node->save();

    $email = (string) $node->get('field_contact_email')->value;
    if ($email !== '') {
      $this->mailService->sendResetEmail([
        'customer_name' => $node->label(),
        'contact_name' => (string) $node->get('field_contact_name')->value,
        'client_id' => (string) $node->get('field_customer_client_id')->value,
        'hashed_key' => $new_key,
      ], $email);
    }

    $this->historyService->log((int) $node->id(), 'hashed_key_reset', (int) $this->currentUser->id(), [
      'email_sent_to' => $email,
    ]);

    return $node;
  }

  /**
   * Generates a unique client ID.
   */
  protected function generateUniqueClientId(): string {
    $storage = $this->entityTypeManager->getStorage('node');

    do {
      $client_id = $this->credentialGenerator->generateClientId();
      $existing = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'customer')
        ->condition('field_customer_client_id.value', $client_id)
        ->range(0, 1)
        ->execute();
    } while (!empty($existing));

    return $client_id;
  }

  /**
   * Normalizes taxonomy reference values.
   */
  protected function normalizePartnerValues(array $partner_ids): array {
    $partner_ids = array_values(array_filter(array_map('intval', $partner_ids)));
    return array_map(static function (int $tid): array {
      return ['target_id' => $tid];
    }, $partner_ids);
  }

}
