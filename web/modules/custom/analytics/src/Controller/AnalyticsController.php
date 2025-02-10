<?php


namespace Drupal\analytics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\user\Entity\User;
use Drupal\group\Entity\Group;

class AnalyticsController extends ControllerBase {

  protected $request;

  protected $messenger;


  public function __construct(RequestStack $request_stack, MessengerInterface $messenger) {
    $this->request = $request_stack->getCurrentRequest();
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('messenger')
    );
  }

  public function analyticsDashboard() {
    $content = [];
    $currentMonth = $this->request->query->get('month') ?? date('m'); // Current month  
    $currentYear = $this->request->query->get('year') ?? date('Y'); // Current year  
    $daysInMonth = date('t', strtotime(date($currentYear . '-' . $currentMonth .'-01'))); // Total days in the current month.

    // Chart 1 Logic + code.
    $vars = $this->__analyticsChartsLogic($currentMonth, $currentYear, $daysInMonth);
    $dates = $vars['dates'];
    $finalData = $vars['finalData'];
    $content = $vars['content'];
    $content['currentMonth'] = $currentMonth;
    $content['currentYear'] = $currentYear;

    // Years filter
    for ($y=date('Y'); $y>=date('Y', strtotime('-10 Years')); $y--) {
      $content['years'][] = $y;
    }

    return [
      '#theme' => 'analytics',
      '#content' => $content,
      '#attached' => [
        'library' => ['analytics/dashboard'],
        'drupalSettings' => [
          'chart1' => [
            'dataArray' => $finalData['chart1']
          ],
          'chart2' => [
            'dataArray' => $finalData['chart2']
          ],
          'chart3' => [
            'dataArray' => $finalData['chart3']
          ],
          'chart4' => [
            'dataArray' => $finalData['chart4']
          ],
          'dates' => $dates,
        ],
      ]
    ];
  }

  public function analyticsAjaxDashboard() {
    $month = $this->request->query->get('month') ?? date('m'); // Current month  
    $currentYear = $this->request->query->get('year') ?? date('Y'); // Current year  
    $daysInMonth = date('t', strtotime(date($currentYear . '-' . $month .'-01')));

    // Charts Logic + code.
    $vars = $this->__analyticsChartsLogic($month, $currentYear, $daysInMonth);
    return new JsonResponse($vars);
  }

  private function __analyticsChartsLogic($currentMonth, $currentYear, $daysInMonth) {
    $dates = $currentDates = $finalData = [];
    $chart1FinalData = $chart2FinalData = $chart3FinalData = $chart4FinalData = [];
    $chart1DataArray = $chart2DataArray = $chart3DataArray = $chart4DataArray = [];
    for ($day = 1; $day <= $daysInMonth; $day++) {
      $dates[] = "           $currentMonth / $day              ";
      $currentDates[] = date('Y-m-d', strtotime("$currentYear-$currentMonth-$day"));
      $datesCount[date('Y-m-d', strtotime("$currentYear-$currentMonth-$day"))] = 0;
    }

    // getting all the months.
    for ($m=1; $m<=12; $m++) {
      $content['months'][] = date('M', mktime(0,0,0,$m, 1, $currentYear));
    }

    $termStorage = $this->entityTypeManager()->getStorage('taxonomy_term');
    // getting attribute terms.
    $attributeTerms = $termStorage->loadByProperties(['vid' => 'analytics_attributes']);
    foreach ($attributeTerms as $term) {
      $chart1DataArray[$term->tid->value]['name'] = $term->name->value;
      $chart1DataArray[$term->tid->value]['data'] = $datesCount;
      $content['attributeArray'][$term->tid->value] = $term->name->value;
    }

    // getting carrier name terms.
    $carrierTerms = $termStorage->loadByProperties(['vid' => 'analytics_carrier']);
    foreach ($carrierTerms as $term) {
      $content['carrierArray'][$term->tid->value] = $term->name->value;
    }

    // getting end customer terms.
    $customerTerms = $termStorage->loadByProperties(['vid' => 'analytics_customer']);
    foreach ($customerTerms as $term) {
      $content['customerArray'][$term->tid->value] = $term->name->value;
    }


    // Access part for the demand partners filters.
    $currentUserGroups = $this->getCurrentUserGroups();
    $current_user = \Drupal::currentUser();
    $user_roles = $current_user->getRoles();
    $excluded_roles = ['administrator', 'carrier_admin'];
    $group_keys = array_keys($currentUserGroups);
    $user_has_permission_for_all = FALSE;
    // Check if any of the excluded roles exist in the user's roles.
    foreach ($excluded_roles as $role) {
      if (in_array($role, $user_roles, TRUE)) {
        $user_has_permission_for_all = TRUE;
      }
    }
    // getting partners.
    $partners = $this->entityTypeManager()->getStorage('group')->loadByProperties(['type' => 'partner']);
    foreach ($partners as $partner) {
      if (!$user_has_permission_for_all && !array_key_exists($partner->id(), $currentUserGroups)) {
        continue;
      }
      $content['partnerArray'][$partner->id()] = $partner->label();
    }

    $chart2DataArray = $chart3DataArray = $chart4DataArray = $chart1DataArray;

    
    // getting nodes for the terms.
    $nodeQuery = $this->entityTypeManager()->getStorage('node')->getQuery();
    $nodeQuery->accessCheck(TRUE);
    $nodeQuery = $nodeQuery->condition('type', 'analytics')
      ->condition('status', '1')
      ->condition('field_date', [$currentDates[0], end($currentDates)], 'BETWEEN');
     
    if(!$user_has_permission_for_all) {
      $nodeQuery->condition('field_partner.target_id', $group_keys, 'IN');
    }  
    if ($this->request->query->get('attribute')) {
      $nodeQuery->condition('field_attribute', $this->request->query->get('attribute'));
    }
    if ($this->request->query->get('carrier')) {
      $nodeQuery->condition('field_carrier', $this->request->query->get('carrier'));
    }
    if ($this->request->query->get('customer')) {
      $nodeQuery->condition('field_end_customer', $this->request->query->get('customer'));
    }
    if ($this->request->query->get('partner')) {
      $nodeQuery->condition('field_partner', $this->request->query->get('partner'));
    }
    $apiStatus = false; 
    if ($this->request->query->get('api_status')) {
      $apiStatus = true;
      if ($this->request->query->get('api_status') == '200')
        $apiField = 'field_success_api_volume_in_mil';
      elseif ($this->request->query->get('api_status') == '404')
        $apiField = 'field_404_api_volume_in_mil';
      else 
        $apiField = 'field_error_api_volume_in_mil';
    }

    $nids = $nodeQuery->sort('field_date', 'ASC')->execute();
    $nodes = $this->entityTypeManager()->getStorage('node')->loadMultiple($nids);

    $totalVolume = $content['totalSuccessCalls'] = $content['totalUnsuccessCalls'] = $content['highestApi'] = $content['averageLatency'] = $content['averageApi'] = 0;
    foreach ($nodes as $node) {
      $chart1DataArray[$node->field_attribute->target_id]['data'][date('Y-m-d', strtotime($node->field_date->value))] += $node->field_api_volume_in_mil->value;
      $chart2DataArray[$node->field_attribute->target_id]['data'][date('Y-m-d', strtotime($node->field_date->value))] += ($apiStatus) ? $node->$apiField->value : $node->field_success_api_volume_in_mil->value + $node->field_error_api_volume_in_mil->value + $node->field_404_api_volume_in_mil->value;
      $chart3DataArray[$node->field_attribute->target_id]['data'][date('Y-m-d', strtotime($node->field_date->value))] += $node->field_average_api_latency_in_mil->value;
      $chart4DataArray[$node->field_attribute->target_id]['data'][date('Y-m-d', strtotime($node->field_date->value))] += $node->field_est_revenue->value;
      $content['totalSuccessCalls'] += $node->field_success_api_volume_in_mil->value + $node->field_404_api_volume_in_mil->value;
      $content['totalUnsuccessCalls'] += $node->field_error_api_volume_in_mil->value + $node->field_404_api_volume_in_mil->value;
      $content['averageLatency'] += $node->field_average_api_latency_in_mil->value;
      $totalVolume += $node->field_api_volume_in_mil->value;
    }

    if ($currentMonth != date('m') || $currentYear != date('Y')) {
      $content['totalSuccessCallsAvg'] =  $content['totalSuccessCalls'] / $daysInMonth;
      $content['totalUnsuccessCallsAvg'] = $content['totalUnsuccessCalls'] / $daysInMonth;
    }
    else {
      $content['totalSuccessCallsAvg'] =  $content['totalSuccessCalls'] / date('d');
      $content['totalUnsuccessCallsAvg'] = $content['totalUnsuccessCalls'] / date('d');
    }

    $content['highestApi'] = $totalVolume / $daysInMonth;
    $content['averageLatency'] = $content['averageLatency'] / $daysInMonth;
    $content['averageApi'] = $content['totalSuccessCalls'] / $daysInMonth;

    // finalize array chart1
    foreach ($chart1DataArray as $dataA) {
      $chart1FinalData[] = $dataA;
    }
    foreach ($chart1FinalData as $i => $final) {
      $chart1FinalData[$i]['data'] = array_values($final['data']);
    }

    // finalize array chart2
    foreach ($chart2DataArray as $dataA) {
      $chart2FinalData[] = $dataA;
    }
    foreach ($chart2FinalData as $i => $final) {
      $chart2FinalData[$i]['data'] = array_values($final['data']);
    }

    // finalize array chart3
    foreach ($chart3DataArray as $dataA) {
      $chart3FinalData[] = $dataA;
    }
    foreach ($chart3FinalData as $i => $final) {
      $chart3FinalData[$i]['data'] = array_values($final['data']);
    }

    // finalize array chart4
    foreach ($chart4DataArray as $dataA) {
      $chart4FinalData[] = $dataA;
    }
    foreach ($chart4FinalData as $i => $final) {
      $chart4FinalData[$i]['data'] = array_values($final['data']);
    }

    $finalData['chart1'] = $chart1FinalData;
    $finalData['chart2'] = $chart2FinalData;
    $finalData['chart3'] = $chart3FinalData;
    $finalData['chart4'] = $chart4FinalData;

    // assign all chart array to one
    return compact("dates", "finalData", "content");
  }


  public function getCurrentUserGroups() {
    // Get the current user ID.
    $current_user = \Drupal::currentUser();
    $user = User::load($current_user->id());
    // Load the group membership service.
    $membership_loader = \Drupal::service('group.membership_loader');
    // Get memberships for the current user.
    $memberships = $membership_loader->loadByUser($user);
    // Collect the groups the user is part of.
    $groups = [];
    foreach ($memberships as $membership) {
      $group = $membership->getGroup();
      $groups[$group->id()] = $group->label();
    }
    return $groups;
  }
}