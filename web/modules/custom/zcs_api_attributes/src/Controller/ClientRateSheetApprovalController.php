<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
// use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Session\AccountInterface;
// use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
// use Symfony\Component\HttpFoundation\Request;
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
     *
     */
    public function clientRateSheetList() {

        return [
            '#theme' => 'client_rate_sheet_approval',
            '#content' => [],
        ];
    }
}