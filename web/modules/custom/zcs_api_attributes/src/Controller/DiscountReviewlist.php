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
class DiscountReviewList extends ControllerBase {


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
   * Display the Attributes Approval Page.
   */
  public function discountApprovals(Request $request) {

    // Add pager to table.
    $limit = 10;
    $queryTotal = $this->database->select('discount_pricing_page_data', 'apd');
    if (!empty($request->get('status'))) {
      $queryTotal->condition('apd.attribute_status', $request->get('status'));
    }
    $resultTotal = $queryTotal->countQuery()->execute()->fetchField();
    $pager = $this->pagerManager->createPager($resultTotal, $limit);

    // Fetch results.
    $query = $this->database->select('discount_pricing_page_data', 'apd')
      ->fields('apd', ['id', 'submit_by', 'client_name', 'approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status', 'attribute_status', 'created']);
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
          'client_name' => $result->client_name,
          'approver1' => $userStorage->load($result->approver1_uid)->mail->value,
          'approver2' => $userStorage->load($result->approver2_uid)->mail->value,
          'approver1_status' => $statuses[$result->approver1_status],
          'approver2_status' => $statuses[$result->approver2_status],
          'status' => $statuses[$result->attribute_status],
          'requested_time' => date('M d, Y', $result->created),
          'url' => Url::fromRoute('zcs_api_attributes.pricing_discount_sheet.review', ['id' => $result->id]),
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
      '#theme' => 'discount_sheet_approval_list',
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
