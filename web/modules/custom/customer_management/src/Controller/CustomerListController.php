<?php

namespace Drupal\customer_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Customer management list controller.
 */
class CustomerListController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs the controller.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    PagerManagerInterface $pager_manager,
    RequestStack $request_stack
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pagerManager = $pager_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('pager.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Builds the customer list page.
   */
  public function list() {
    $request = $this->requestStack->getCurrentRequest();
    $search_name = trim((string) $request->query->get('customer_name', ''));
    $search_email = trim((string) $request->query->get('contact_email', ''));
    $limit = 20;

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'customer')
      ->sort('created', 'DESC');

    if ($search_name !== '') {
      $query->condition('title', '%' . $search_name . '%', 'LIKE');
    }

    if ($search_email !== '') {
      $query->condition('field_contact_email.value', '%' . $search_email . '%', 'LIKE');
    }

    $count_query = clone $query;
    $total = (int) $count_query->count()->execute();

    $pager = $this->pagerManager->createPager($total, $limit);
    $query->range($pager->getCurrentPage() * $limit, $limit);

    $nids = $query->execute();
    $nodes = $storage->loadMultiple($nids);

    $rows = [];
    foreach ($nodes as $node) {
      $partners = [];
      if ($node->hasField('field_demand_partners')) {
        foreach ($node->get('field_demand_partners')->referencedEntities() as $term) {
          $partners[] = $term->label();
        }
      }

      $actions = [
        [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('customer_management.edit_customer', ['node' => $node->id()])->toString(),
          'class' => 'customer-action-edit',
          'ajax' => FALSE,
          'disabled' => FALSE,
        ],
      ];

      $rows[] = [
        'name' => $node->label(),
        'contact_name' => $node->hasField('field_contact_name') ? (string) $node->get('field_contact_name')->value : '',
        'contact_email' => $node->hasField('field_contact_email') ? (string) $node->get('field_contact_email')->value : '',
        'client_id' => $node->hasField('field_customer_client_id') ? (string) $node->get('field_customer_client_id')->value : '',
        'demand_partners' => implode(', ', $partners),
        'actions' => $actions,
      ];
    }

    $data = [
      'link' => Link::fromTextAndUrl($this->t('Add Customer'), Url::fromRoute('customer_management.add_customer'))->toRenderable(),
      'search' => [
        'customer_name' => $search_name,
        'contact_email' => $search_email,
        'action' => Url::fromRoute('customer_management.list_customer')->toString(),
      ],
      'final' => $rows,
      'pager' => [
        '#type' => 'pager',
      ],
    ];

    return [
      '#theme' => 'customers_list',
      '#content' => $data,
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
          'customer_management/customer-management',
        ],
      ],
    ];
  }

}
