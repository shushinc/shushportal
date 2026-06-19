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
        
        // Get all client rate sheets
        $all_client_rate_sheets = $this->rateSheetService->getClientRateSheets();
        
        $resultTotal = count($all_client_rate_sheets);
        $pager = $this->pagerManager->createPager($resultTotal, $limit);
        
        // Paginate results
        $offset = $pager->getCurrentPage() * $limit;
        $client_rate_sheets = array_slice($all_client_rate_sheets, $offset, $limit);
        
        $final = [];
        $current_user = \Drupal::currentUser();
        
        foreach ($client_rate_sheets as $sheet) {
            $actions = [];

            $actions[] = [
                'title' => 'Approve',
                'url' => '/pricing/client-rate-sheet/approve/' . $sheet['rate_sheet_id'] . '/' . $sheet['client_id'],
                'class' => 'client-rate-sheet-approve',
            ];

            $actions[] = [
                'title' => 'Reject',
                'url' => '/pricing/client-rate-sheet/reject/' . $sheet['rate_sheet_id'] . '/' . $sheet['client_id'],
                'class' => 'client-rate-sheet-reject',
            ];

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
                'actions' => $actions,
            ];
        }

        $build = [
            '#theme' => 'client_rate_sheet_approval',
            '#content' => [
                'final' => $final,
            ],
            '#attached' => [
                'library' => [
                    'zcs_api_attributes/attributes-page',
                    'zcs_api_attributes/rate-sheet-approval',
                ],
            ],
        ];
        
        $build['pager'] = [
            '#type' => 'pager',
        ];
        
        return $build;
    }

    /**
     * Approve a client rate sheet.
     */
    public function approveClientRateSheet($rate_sheet_id, $client_id) {
        $current_user_id = \Drupal::currentUser()->id();

        try {
            $this->rateSheetService->approveClientRateSheet($rate_sheet_id, $client_id, $current_user_id);
        }
        catch (\Exception $e) {
            \Drupal::messenger()->addError($this->t('Failed to approve client rate sheet: @message', ['@message' => $e->getMessage()]));
        }
        
        // Redirect back to the list
        $url = Url::fromRoute('zcs_api_attributes.client_rate_sheet_approval');
        return new RedirectResponse($url->toString());
    }

    /**
     * Reject a client rate sheet.
     */
    public function rejectClientRateSheet($rate_sheet_id, $client_id) {
        $current_user_id = \Drupal::currentUser()->id();
        
        try {
            $this->rateSheetService->rejectClientRateSheet($rate_sheet_id, $client_id, $current_user_id);
            \Drupal::messenger()->addStatus($this->t('Client rate sheet rejected successfully.'));
        }
        catch (\Exception $e) {
            \Drupal::messenger()->addError($this->t('Failed to reject client rate sheet: @message', ['@message' => $e->getMessage()]));
        }
        
        // Redirect back to the list
        $url = Url::fromRoute('zcs_api_attributes.client_rate_sheet_approval');
        return new RedirectResponse($url->toString());
    }
}
