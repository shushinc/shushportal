<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\zcs_api_attributes\Service\RateSheetService;


/**
 * 
 */
Class ClientRateSheetApprovalController extends ControllerBase {

    /**
     * Connection $database.
     */
    protected $database;

    /**
     * Pager Variable.
     */
    protected $pagerManager;
    
    /**
     * Rate Sheet service.
     * 
     * Drupal\zcs_api_attributes\Service\RateSheetService
     */
    protected $rateSheetService;

    /**
     * {@inheritdoc}
     */
    public function __construct(Connection $connection, PagerManagerInterface $pager_manager, RateSheetService $rate_sheet_service) {
        $this->database = $connection;
        $this->pagerManager = $pager_manager;
        $this->rateSheetService = $rate_sheet_service;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('database'),
            $container->get('pager.manager'),
            $container->get('zcs_api_attributes.rate_sheet_service')
        );
    }


    /**
     * Display the Client Rate Sheet Approval list.
     */
    public function clientRateSheetList() {
        $limit = 10;
        
        // Get filter parameter from query string
        $request = \Drupal::request();
        $rate_sheet_name_filter = $request->query->get('rate_sheet_name', '');
        
        // Get all client rate sheets
        $all_client_rate_sheets = $this->rateSheetService->getClientRateSheets();
        
        // Apply filter if provided
        if (!empty($rate_sheet_name_filter)) {
            $filter_lower = strtolower(trim($rate_sheet_name_filter));
            $all_client_rate_sheets = array_filter($all_client_rate_sheets, function($sheet) use ($filter_lower) {
                return strpos(strtolower($sheet['rate_sheet_name']), $filter_lower) !== false;
            });
        }
        
        $resultTotal = count($all_client_rate_sheets);
        $pager = $this->pagerManager->createPager($resultTotal, $limit);
        
        // Paginate results
        $offset = $pager->getCurrentPage() * $limit;
        $client_rate_sheets = array_slice($all_client_rate_sheets, $offset, $limit);
        
        $final = [];
        
        foreach ($client_rate_sheets as $sheet) {
            $final[] = [
                'rate_sheet_id' => $sheet['rate_sheet_id'],
                'client_id' => $sheet['client_id'],
                'client_name' => $sheet['client_name'],
                'rate_sheet_name' => $sheet['rate_sheet_name'],
                'currency' => $sheet['currency'],
                'effective_date' => date('M d, Y', $sheet['effective_date']),
                'markup_retail' => $sheet['markup_retail'],
                'approvers' => $sheet['approvers'],
                'status' => $sheet['status'],
            ];
        }

        $build = [
            '#theme' => 'client_rate_sheet_approval',
            '#content' => [
                'final' => $final,
                'rate_sheet_name_filter' => $rate_sheet_name_filter,
            ],
            '#attached' => [
                'library' => [
                    'zcs_api_attributes/attributes-page',
                    'zcs_api_attributes/rate-sheet-approval',
                    'zcs_api_attributes/client-rate-sheet-batch',
                ],
            ],
        ];
        
        $build['pager'] = [
            '#type' => 'pager',
        ];
        
        return $build;
    }

    public function changeStatusClientRateSheetBatch() {
        $request = \Drupal::request();
        $selected_items = $request->request->all('selected_items');
        $batch_action = $request->request->get('batch_action');
        $success_count = 0;
        $error_count = 0;
        $current_user_id = \Drupal::currentUser()->id();
        $return_msg_label = "";
        
        // Group by rate sheet ID
        $grouped = [];
        foreach ($selected_items as $item) {
            list($rate_sheet_id, $client_id) = explode('_', $item);
            if (!isset($grouped[$rate_sheet_id])) {
                $grouped[$rate_sheet_id] = [];
            }
            $grouped[$rate_sheet_id][] = $client_id;
        }

        if (empty($selected_items)) {
            \Drupal::messenger()->addWarning($this->t('No items selected.'));
            $url = Url::fromRoute('zcs_api_attributes.client_rate_sheet_approval');
            return new RedirectResponse($url->toString());
        }

        foreach ($grouped as $rate_sheet_id => $client_ids) {
            try {
                $this->rateSheetService->statusClientRateSheetBatchOperation($rate_sheet_id, $client_ids, $current_user_id, $batch_action);
                $success_count += count($client_ids);
            }
            catch (\Exception $e) {
                $error_count += count($client_ids);
                \Drupal::messenger()->addError(
                    $this->t(
                        'Failed to perform bach operation on clients rate sheets: @message',
                        ['@message' => $e->getMessage()]
                    )
                );
            }
        }

        if ($success_count > 0) {
            \Drupal::messenger()->addStatus(
                $this->t(
                    'Successfully @operation @count client rate sheet(s).', ['@count' => $success_count, '@operation' => $return_msg_label]
                )
            );
        }

        $url = Url::fromRoute('zcs_api_attributes.client_rate_sheet_approval');
        return new RedirectResponse($url->toString());
    }
}
