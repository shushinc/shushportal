<?php

namespace Drupal\zcs_api_attributes\Services;

use Drupal\Core\Database\Connection;
use \Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

class RateSheetService {

    /**
     * The database object
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * Constructs a new RateSheetService object.
     * @param Connection $database
     */
    public function __construct(Connection $database) {
        $this->database = $database;
    }


    /**
     * Returns the current Rate Sheet status based on the last two records.
     *
     * Rules:
     * - 2 APPROVED > APPROVED
     * - 2 DENIED > DENIED
     * - APPROVED + DENIED > PENDING
     * - Any PENDING > PENDING
     * - Only one record (initial state) > PENDING
     *
     * @param int $rate_sheet_id
     *   The Rate Sheet ID.
     *
     * @return string
     *   One of: 'PENDING', 'APPROVED', or 'DENIED'.
     */
    public function getRateSheetStatus(int $rate_sheet_id): string {

        $query = $this->database->select('rate_sheet_status', 'rss')
            ->fields('rss', ['status_name'])
            ->condition('rate_sheet_id', $rate_sheet_id)
            ->orderBy('date', 'DESC')
            ->range(0, 2);

        $statuses = $query->execute()->fetchCol();

        if (empty($statuses)) {
            return 'Pending';
        }

        // Only one status (initial state)
        if (count($statuses) === 1) {
            return 'Pending';
        }

        $first = $statuses[0];
        $second = $statuses[1];

        // If any is pending > still pending
        if ($first === 'Pending' || $second === 'Pending') {
            return 'Pending';
        }

        // Both equal > final decision
        if ($first === $second) {
            return $first; // APPROVED or DENIED
        }

        // Conflict (APPROVED vs DENIED)
        return 'Pending';
    }

    /**
     * Returns approvers HTML for a Rate Sheet.
     *
     * Output format:
     * - Always 2 lines (2 approvers)
     * - Shows email when action already taken
     * - Shows status label (approved, denied, pending)
     *
     * Rules:
     * - No records or only 1 pending > both pending
     * - One decision + one pending > show decision + pending
     * - Two equal decisions > show both
     * - Approved + Denied > both shown
     *
     * @param int $rate_sheet_id
     *   The Rate Sheet ID.
     *
     * @return string
     *   HTML markup.
     */
    public function getRateSheetApprovers(int $rate_sheet_id): string {

        $query = $this->database->select('rate_sheet_status', 'rss')
            ->fields('rss', ['status_name', 'created_by'])
            ->condition('rate_sheet_id', $rate_sheet_id)
            ->orderBy('date', 'ASC');
        $rows = $query->execute()->fetchAll();

        if (empty($rows)) {
            return '<span class="pending">Pending</span><br><span class="pending">Pending</span>';
        }

        if (count($rows) === 1 && strtoupper($rows[0]->status_name) === 'PENDING') {
            return '<span class="pending">Pending</span><br><span class="pending">Pending</span>';
        }

        // Remove first pending if it's the creation record
        if (strtoupper($rows[0]->status_name) === 'PENDING') {
            array_shift($rows);
        }

        // Now take last 2 relevant statuses
        $rows = array_slice(array_reverse($rows), 0, 2);

        $statuses = [];

        foreach ($rows as $row) {
            $statuses[] = [
                'status' => strtoupper($row->status_name),
                'uid' => $row->created_by,
            ];
        }

        // Ensure always 2 slots
        while (count($statuses) < 2) {
            $statuses[] = [
                'status' => 'PENDING',
                'uid' => NULL,
            ];
        }

        $output = [];

        foreach ($statuses as $item) {
            $status = $item['status'];
            $uid = $item['uid'];

            $email = '';

            if ($status !== 'PENDING' && !empty($uid)) {
                $user = \Drupal\user\Entity\User::load($uid);
                if ($user) {
                    $email = $user->getEmail();
                }
            }

            $line = '';

            if ($email) {
                $line .= $email . '<br>';
            }

            $class = strtolower($status);
            $line .= '<span class="' . $class . '">' . ucfirst(strtolower($status)) . '</span>';

            $output[] = $line;
        }

        return implode('<br>', $output);
    }
    /**
     * Inserts a new status for a rate sheet.
     *
     * @param int $rate_sheet_id
     *   The Rate Sheet ID.
     * @param int $status
     *   The status to insert (2 for Approve, 3 for Reject).
     * @param int $user_id
     *   The ID of the user submitting the status.
     */
    public function insertRateSheetStatus(int $rate_sheet_id, int $status, int $user_id) {
        $status_name = $status === 2 ? 'Approved' : 'Denied';

        $this->database->insert('rate_sheet_status')
            ->fields([
                'rate_sheet_id' => $rate_sheet_id,
                'status_name' => $status_name,
                'created_by' => $user_id,
                'date' => \Drupal::time()->getRequestTime(),
            ])
            ->execute();
    }
}
