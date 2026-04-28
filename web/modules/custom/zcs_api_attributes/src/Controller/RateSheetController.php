<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 */
class RateSheetController extends ControllerBase {


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

  /**
   *
   */
  public function rateSheetList() {
    $limit = 10;
    $allResultSet = $this->database->select('rate_sheet', 'rs');
    $resultTotal = $allResultSet->countQuery()->execute()->fetchField();
    $pager = $this->pagerManager->createPager($resultTotal, $limit);
    $final = [];

    $resultSet = $this->database->select('rate_sheet', 'rs')
      ->fields('rs', ['id', 'name', 'effective_date', 'currency', 'markup_retail', 'created_date']);
    $resultSet->range($pager->getCurrentPage() * $limit, $limit);
    $resultSet->orderBy('effective_date', 'DESC');
    $resultSet = $resultSet->execute()->fetchAll();

    $url = Url::fromRoute('zcs_api_attributes.create_rate_sheet');
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
    $classes = ['button', 'button--primary', 'use-ajax'];
    if (!$access->isAllowed()) {
      $classes[] = 'disable-link';
    }

    foreach($resultSet as $result) {
      $final[] = [
        'name' => $result->name,
        'currency' => $result->currency,
        'effective_date' => date('M d, Y', $result->effective_date),
        'markup_retail' => $result->markup_retail,
      ];
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
    $pricing_api_link = Link::fromTextAndUrl($this->t('Create Rate Sheet'), $url)->toRenderable();
    $data['link'] = $pricing_api_link;
    $data['create_rate_sheet_url'] = $url;
    $data['final'] = $final;

    return [
      '#theme' => 'rate_sheet_list',
      '#content' => $data,
      '#attached' => [
        'library' => ['zcs_api_attributes/attributes-page'],
      ],
    ];
  }

  /**
   *
   */
  private function getAttributeValue($attributeValue) {
    if ($attributeValue == 'yes') {
      return Markup::create("<span class='attrib_yes'>Yes</span>");
    }
    elseif ($attributeValue == 'no') {
      return Markup::create("<span class='attrib_no'>No</span>");
    }
    else {
      return '';
    }

  }

  /**
   * Display the Attributes Approval Page.
   */
  public function attributeApprovals(Request $request) {

    // Add pager to table.
    $limit = 10;
    $queryTotal = $this->database->select('attributes_page_data', 'apd');
    if (!empty($request->get('status'))) {
      $queryTotal->condition('apd.attribute_status', $request->get('status'));
    }
    $resultTotal = $queryTotal->countQuery()->execute()->fetchField();
    $pager = $this->pagerManager->createPager($resultTotal, $limit);

    // Fetch results.
    $query = $this->database->select('attributes_page_data', 'apd')
      ->fields('apd', ['id', 'submit_by', 'approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status', 'attribute_status', 'created', 'effective_date']);
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
          'effective_date' => date("M d, Y", strtotime($result->effective_date)),
          'url' => Url::fromRoute('zcs_api_attributes.rate_sheet.review', ['id' => $result->id]),
        ];
      }
    }

    $output[] = [
      '#type' => 'select',
      '#options' => ['- All Status'] + $statuses,
      '#attributes' => [
        'class' => ['select-status'],
      ],
      '#value' => $request->get('status') ?? 0,
    ];

    $output[] = [
      '#theme' => 'rate_sheet_approval',
      '#content' => $data,
      '#attached' => [
        'library' => ['zcs_api_attributes/rate-sheet-approval'],
      ],
    ];
    $output[] = ['#type' => 'pager'];
    return $output;
  }

  /**
   *
   */
  public function access(AccountInterface $account) {
    $allowed_roles = ['administrator', 'carrier_admin', 'finance_admin', 'financial_rate_sheet_approval_level_1', 'financial_rate_sheet_approval_level_2'];
    $user_roles = \Drupal::currentUser()->getRoles();

    if (array_intersect($allowed_roles, $user_roles)) {
      return AccessResult::allowed();
    }
    else {
      $memberships = \Drupal::service('group.membership_loader')->loadByUser(\Drupal::currentUser());
      if (isset($memberships)) {
        return AccessResult::forbidden();
      }
    }
    return AccessResult::forbidden();
  }

}
