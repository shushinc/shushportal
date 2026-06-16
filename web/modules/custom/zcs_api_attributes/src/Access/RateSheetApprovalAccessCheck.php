<?php

namespace Drupal\zcs_api_attributes\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class RateSheetApprovalAccessCheck {

    /**
     * Checks access for rate sheet routes.
     *
     * @param AccountInterface $account
     *   The user account.
     *
     * @return \Drupal\Core\Access\AccessResultInterface
     *   The access result.
     */
    public function access(AccountInterface $account): AccessResult {

        $allowed_roles = [
            'administrator',
            'carrier_admin',
            'finance_admin',
            'financial_rate_sheet_approval_level_1',
            'financial_rate_sheet_approval_level_2',
        ];

        if (array_intersect($allowed_roles, $account->getRoles())) {
            return AccessResult::allowed()->cachePerPermissions();
        }

        return AccessResult::forbidden()->cachePerUser();
    }

}