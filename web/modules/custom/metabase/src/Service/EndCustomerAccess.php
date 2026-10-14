<?php

namespace Drupal\metabase\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Resolves which analytics_customer terms a user may see.
 *
 * A term is visible to a user when it is referenced (field_end_customer) by a
 * published analytics node whose field_partner points at one of the user's
 * partner groups. Users with 'view all analytics end customers' see all terms.
 */
class EndCustomerAccess {

  /**
   * Locked-param value meaning "no restriction".
   */
  public const ALL = '*';

  /**
   * Locked-param value meaning "nothing" (fail closed). No term has tid 0.
   */
  public const NONE = '0';

  /**
   * Static cache per user.
   *
   * @var array
   */
  protected array $cache = [];

  public function __construct(
    protected Connection $database,
    protected $membershipLoader,
  ) {}

  /**
   * Whether the account bypasses the restriction.
   */
  public function isUnrestricted(AccountInterface $account): bool {
    return $account->hasPermission('view all analytics end customers');
  }

  /**
   * Partner groups the account belongs to, keyed by group id.
   *
   * @return string[]
   *   Group labels keyed by group id.
   */
  public function getPartnerGroups(AccountInterface $account): array {
    if ($account->isAnonymous()) {
      return [];
    }
    $groups = [];
    foreach ($this->membershipLoader->loadByUser($account) as $membership) {
      $group = $membership->getGroup();
      if ($group && $group->bundle() === 'partner') {
        $groups[(int) $group->id()] = $group->label();
      }
    }
    return $groups;
  }

  /**
   * End customer terms the account may see.
   *
   * @return string[]
   *   Term names keyed by tid, sorted by name.
   */
  public function getAllowedEndCustomers(AccountInterface $account): array {
    $uid = (int) $account->id();
    if (isset($this->cache[$uid])) {
      return $this->cache[$uid];
    }

    $query = $this->database->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid', 'name'])
      ->condition('t.vid', 'analytics_customer')
      ->condition('t.default_langcode', 1)
      ->distinct()
      ->orderBy('t.name');

    if (!$this->isUnrestricted($account)) {
      $group_ids = array_keys($this->getPartnerGroups($account));
      if (empty($group_ids)) {
        return $this->cache[$uid] = [];
      }
      $query->join('node__field_end_customer', 'ec',
        "ec.field_end_customer_target_id = t.tid AND ec.bundle = 'analytics'");
      $query->join('node_field_data', 'n',
        'n.nid = ec.entity_id AND n.vid = ec.revision_id AND n.status = 1');
      $query->join('node__field_partner', 'p',
        'p.entity_id = ec.entity_id AND p.revision_id = ec.revision_id');
      $query->condition('p.field_partner_target_id', $group_ids, 'IN');
    }

    return $this->cache[$uid] = $query->execute()->fetchAllKeyed();
  }

  /**
   * Builds the value for the locked `end_customer_ids` JWT param.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The viewer.
   * @param int|null $selected
   *   A tid the user picked in the Drupal filter, or NULL for "all".
   *
   * @return string
   *   '*', a comma-separated tid list, or '0'.
   */
  public function buildLockedValue(AccountInterface $account, ?int $selected = NULL): string {
    $allowed = $this->getAllowedEndCustomers($account);

    // Never trust the request: a tid outside the allowed list is ignored.
    if ($selected !== NULL && isset($allowed[$selected])) {
      return (string) $selected;
    }
    if ($this->isUnrestricted($account)) {
      return self::ALL;
    }
    return $allowed ? implode(',', array_keys($allowed)) : self::NONE;
  }

}
