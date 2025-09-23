<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManagerInterface;

class AttributesPageController extends ControllerBase {

   /**
   * Connection $database.
   */
  protected $database;

  /**
   * Pager Variable.
   */
  protected $pagerManager;


  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $connection, PagerManagerInterface $pager_manager) {
    $this->database = $connection;
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('pager.manager')
    );
  }


  public function attributesPage() {
    $final = [];
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'api_attributes')
      ->sort('field_attribute_weight', 'ASC')
      ->accessCheck()
      ->execute();
    $contents = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        $final[$content->id()][] = [
          'title' => $content->title->value,
          'integrated_carrier_network' => $this->getAttributeValue($content->field_successfully_integrated_cn->value),
          'to_be_used' => $this->getAttributeValue($content->field_able_to_be_used->value),
        ];
      }
    }
    
    $url = Url::fromRoute('zcs_api_attributes.api_attribute_sheet');
    $route_name = $url->getRouteName();
    $route_parameters = $url->getRouteParameters();
    
    // Use access manager to check access
    $access = \Drupal::service('access_manager')->checkNamedRoute(
      $route_name,
      $route_parameters,
      \Drupal::currentUser(),
      TRUE // return AccessResult object
    );

    // Build class list dynamically
    $classes = ['button', 'button--primary', 'use-ajax'];
    if (!$access->isAllowed()) {
      $classes[] = 'disable-link';
    }

    $url->setOptions([
      'attributes' => [
        'class' => $classes,
        'data-dialog-type' => 'modal',
        'data-dialog-options' => json_encode(['width' => 700]),
      ],
    ]);
    $create_attribute_sheet_link = Link::fromTextAndUrl($this->t('Update API Attribute'), $url)->toRenderable();

    $data['final'] = $final;
    $data['link'] = $create_attribute_sheet_link;

    return [
      '#theme' => 'attributes_page',
      '#content' => $data,
      '#attached' => [
        'library' => ['zcs_api_attributes/attributes-page']
      ]
    ];
  }

  private function getAttributeValue($attributeValue){
    if($attributeValue == 'yes') {
       return Markup::create("<span class='attrib_yes'>Yes</span>");
    }
    elseif($attributeValue == 'no') {
      return Markup::create("<span class='attrib_no'>No</span>");
    }
    else {
      return '';
    }

  }


  /**
   * Display the Attributes Approval Page.
   */
  public function apiAttributeApprovals(Request $request) {
    // add pager to table
    $limit = 10;
    $queryTotal = $this->database->select('api_attributes_page_data', 'apd');
    if (!empty($request->get('status'))) {
      $queryTotal->condition('apd.attribute_status', $request->get('status'));
    }
    $resultTotal = $queryTotal->countQuery()->execute()->fetchField();
    $pager = $this->pagerManager->createPager($resultTotal, $limit);

      // fetch results
    $query = $this->database->select('api_attributes_page_data', 'apd')
      ->fields('apd', ['id', 'submit_by', 'approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status', 'attribute_status', 'created']);
    if (!empty($request->get('status'))) {
      $query->condition('apd.attribute_status', $request->get('status'));
    }
    $query->orderBy('apd.created', 'DESC');
    $query->range($pager->getCurrentPage() * $limit, $limit);
    $resultSet = $query->execute()->fetchAll();
    $statusSet = $this->database->select('attribute_status', 'as')
    ->fields('as', ['id', 'status'])
    ->execute()
    ->fetchAll();
    foreach ($statusSet as $status) {
      $statuses[$status->id] = $status->status;
    }

    $data = [];
    if (!empty($resultSet)) {
      foreach ($resultSet as $result) {
        $userStorage = $this->entityTypeManager()->getStorage('user');
        $data[] = [
          'id' => $result->id,
          'submitted' => $userStorage->load($result->submit_by)->mail->value,
          'approver1' => $userStorage->load($result->approver1_uid)->mail->value,
          'approver2' => $userStorage->load($result->approver2_uid)->mail->value,
          'approver1_status' => $statuses[$result->approver1_status],
          'approver2_status' => $statuses[$result->approver2_status],
          'status' => $statuses[$result->attribute_status],
          'requested_time' => date('M d, Y', $result->created),
          'url' => Url::fromRoute('zcs_api_attributes.api_attribute_sheet.review', ['id' => $result->id])
        ];
      }
    }

    $output[] = [
      '#type' => 'select',
      '#options' => ['- All Status'] + $statuses,
      '#attributes' => [
        'class' => ['select-status']
      ],
      '#value' => $request->get('status') ?? 0
    ];

    $output[] =  [
      '#theme' => 'api_attribute_sheet_approval',
      '#content' => $data,
      '#attached' => [
      'library' => ['zcs_api_attributes/rate-sheet-approval']
      ],
    ];
    $output[] = ['#type' => 'pager'];
    return $output;
  }
}