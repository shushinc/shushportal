<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\Core\Link;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;
use NumberFormatter;
use Drupal\Core\Pager\PagerManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class PricingOverTime extends ControllerBase {


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



  public function pricingPage() {

    $url = Url::fromRoute('zcs_api_attributes.rate_sheet')->toString();

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'api_attributes')
      ->sort('field_attribute_weight', 'ASC')
      ->accessCheck()
      ->execute();
    $contents = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);


    $resultSet = $this->database->select('attributes_page_data', 'apd')
      ->fields('apd', ['id','page_data', 'effective_date', 'effective_date_integer', 'currency_locale'])
      ->condition('attribute_status', 2)
      ->condition('effective_date_integer', strtotime('now'), '<')
      ->range(0, 3)
      ->orderBy('effective_date', 'DESC')
      ->execute()->fetchAll();
    $prices = $headerDate = $symbols = [];
 
    foreach ($resultSet as $result) {
      $prices[] = Json::decode($result->page_data);
      $headerDate[] = $result->effective_date;
      $number = new NumberFormatter($result->currency_locale, NumberFormatter::CURRENCY);
      $symbols[] = $number->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
    }
    if (!empty($contents)) {
      foreach ($contents as $content) {
        $final[] = [
          'title' => $content->title->value,
          'price0' => $prices[0][$content->id()] ?? 0.00,
          'price1' => $prices[1][$content->id()] ?? 0.00,
          'price2' => $prices[2][$content->id()] ?? 0.00
        ];
      }
    }
    $data['symbols'] = $symbols;
    $data['create_rate_sheet_url'] = $url;
    $data['final'] = $final;
    $data['header_date'] = $headerDate;

    return [
      '#theme' => 'network_authentication_pricing_over_time',
      '#content' => $data,
      '#attached' => [
        'library' => ['zcs_api_attributes/attributes-page']
      ]
    ];
  }

  private function getAttributeValue($attributeValue) {
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
  public function attributeApprovals(Request $request) {

    // add pager to table
    $limit = 10;
    $queryTotal = $this->database->select('attributes_page_data', 'apd');
    if (!empty($request->get('status'))) {
      $queryTotal->condition('apd.attribute_status', $request->get('status'));
    }
    $resultTotal = $queryTotal->countQuery()->execute()->fetchField();
    $pager = $this->pagerManager->createPager($resultTotal, $limit);

      // fetch results
    $query = $this->database->select('attributes_page_data', 'apd')
      ->fields('apd', ['id', 'submit_by', 'approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status', 'attribute_status', 'created']);
    if (!empty($request->get('status'))) {
      $query->condition('apd.attribute_status', $request->get('status'));
    }
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
          'requested_time' => date('Y-m-d', $result->created),
          'url' => Url::fromRoute('zcs_api_attributes.rate_sheet.review', ['id' => $result->id])
        ];
      }
    }

    $output[] = [
      '#type' => 'select',
      '#options' => ['- All -'] + $statuses,
      '#title' => 'Status',
      '#attributes' => [
        'class' => ['select-status']
      ],
      '#value' => $request->get('status') ?? 0
    ];

    $output[] =  [
      '#theme' => 'rate_sheet_approval',
      '#content' => $data,
      '#attached' => [
        'library' => ['zcs_api_attributes/rate-sheet-approval']
      ],
    ];
    $output[] = ['#type' => 'pager'];
    return $output;
  }
}