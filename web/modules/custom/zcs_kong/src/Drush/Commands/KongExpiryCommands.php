<?php

namespace Drupal\zcs_kong\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\zcs_kong\Services\KongService;


/**
 * Drush commands for Kong.
 */
class KongExpiryCommands extends DrushCommands {

  /**
   * Kong gateway service.
   *
   * @var \Drupal\zcs_kong\Services\KongService
   */
  protected $KongGateway;

  /**
   * Constructor.
   */
  public function __construct(KongService $kongGateway) {
    parent::__construct();
    $this->KongGateway = $kongGateway;
  }

  /**
   * Sync expired Kong applications.
   *
   * @command zcs:sync-expired-apps
   * @aliases zsea
   *
   * @usage drush zcs:sync-expired-apps
   *   Deletes expired Kong OAuth applications and updates Drupal.
   */
  public function syncExpiredApps() {
    $count = $this->KongGateway->syncExpiredApps();
  }

}