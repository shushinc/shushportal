<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Url;


/**
 *
 */
class PricingDiscount extends FormBase {

  /**
   * EntityTypeManager $entityTypeManager.
   */
  protected $entityTypeManager;

  /**
   * Array $list.
   */
  protected $list;

  /**
   * Connection $connection.
   */
  protected $database;

  protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $connection;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pricing_discount_sheet';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $client_id = $this->request->query->get('client');

    // Get the list of clients.
    $group_type = 'partner';
    $group_storage = \Drupal::entityTypeManager()->getStorage('group');
    $query = $group_storage->getQuery()->condition('type', $group_type);
    $query = $group_storage->getQuery()->condition('field_partner_type', 'enterprise');
    $group_ids = $query->accessCheck(FALSE)->execute();
    $clients = $group_storage->loadMultiple($group_ids);
    
    $client_groups = [];
    foreach($clients as $group) {
      $client_groups[$group->get('id')->value] = $group->get('label')->value;
    }
    if(empty($client_id)) {
      $client_id = array_key_first($client_groups);
    }

    $data = $this->database->select('discount_pricing_page_data', 'dppd')
      ->fields('dppd', ['submit_by','currency_locale', 'client_id', 'client_name','approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status', 'attribute_status', 'page_data','created', 'updated'])
      ->condition('attribute_status', 2)
      ->condition('client_id', $client_id)
      ->orderBy('updated', 'DESC') 
      ->execute()->fetchObject();

    $url = Url::fromRoute('zcs_api_attributes.create_pricing_discount');
    $route_name = $url->getRouteName();
    $route_parameters = $url->getRouteParameters();

    // Use access manager to check access.
    $access = \Drupal::service('access_manager')->checkNamedRoute(
      $route_name,
      $route_parameters,
      \Drupal::currentUser(),
    // Return AccessResult object.
      TRUE
    );

    // Build class list dynamically.
    $classes = ['button', 'button--primary', 'use-ajax' ,'btn-primary'];
    if (!$access->isAllowed()) {
      $classes[] = 'disable-link';
    }

    $url->setOptions([
      'attributes' => [
        'class' => $classes,
        'data-dialog-type' => 'modal',
        'data-dialog-options' => json_encode([
          'width' => 1000,
          'dialogClass' => 'api-popup-width-resize',
        ]),
      ],
    ]);

    $form['client_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['client-discount-wrapper'],
      ],
    ];

    // Show only for carrier admin
    $form['client_wrapper']['client'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Client'),
      '#options' => $client_groups,
      '#default_value' => $client_id, // key from $client_groups
      '#attributes' => [
        'class' => ['select-client'],
      ],
      //'#prefix' => '<div class="client-discount-wrapper">',
    ];
    $form['client_wrapper']['create_discount_pricing_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Create Pricing Discount'),
      '#url' => $url,
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
      '#prefix' => '<div class="create-discount-link-wrapper">',
      '#suffix' => '</div>',
      // '#suffix' => '</div>',
    ];

    $header = [
      'id' => $this->t('#'),
      'attribute_name' => $this->t('Attribute Name'),
      'client_name' => $this->t('Client Name'),
      'discount_price' => $this->t('Discount Price(%)'),
    ];

    $rows = [];
    $id = 1;
    if (!empty($data)) {
      foreach (Json::decode($data->page_data) as $key => $value) {
        $nids[] = $key;
        $node = Node::load($key);
        $rows[] = [
          'id' => $id++,
          'attribute_name' => $node->get('title')->value,
          'client_name' => $data->client_name,
          'discount_price' => number_format((float)$value['discount_pricing'] ?? ((float)$value['discount_pricing'] ?? ((float)$value['discount_pricing'] ?? 0.000)), 3),
        ];         
      }
    }

    $form['pricing_discount'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No Pricing discount Found for the selected Client'),
    ];
  
    // Add pager.
    $form['pager'] = ['#type' => 'pager'];
    $form['#attached']['library'][] = 'zcs_api_attributes/discount-sheet';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // nothing to process in submit
  }

}
