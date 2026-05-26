<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class NewRateSheetReviewForm extends FormBase {

  /**
   * EntityTypeManager $entityTypeManager.
   */
  protected $entityTypeManager;

  /**
   * Connection $connection.
   */
  protected $database;

  /**
   * Array $list.
   */
  protected $list;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection) {
    $this->list = require __DIR__ . '/../../resources/currencies.php';
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'new_rate_sheet_review';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = 0) {

    $data = $this->database->select('rate_sheet', 'rs')
      ->fields('rs', ['id', 'name', 'currency', 'markup_retail', 'effective_date'])
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$data) {
      $form['message'] = [
        '#markup' => $this->t('Rate sheet not found.'),
      ];
      $form['#theme'] = 'new_rate_sheet_review';
      return $form;
    }

    $form['rate_sheet_id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#default_value' => $data->name,
      '#description' => $this->t('The rate sheet name.'),
      '#disabled' => TRUE,
    ];

    // Currencies form select.
    $form['currencies'] = [
      '#type' => 'textfield',
      '#default_value' => $data->currency,
      '#disabled' => TRUE,
      '#weight' => 0,
    ];

    // Effective date.
    $form['attribute_date'] = [
      '#type' => 'textfield',
      '#default_value' => date('M d, Y', $data->effective_date),
      '#weight' => 1,
      '#disabled' => TRUE,
    ];

    // Markup retail.
    $form['retail_markup_percentage'] = [
      '#type' => 'number',
      '#default_value' => $data->markup_retail,
      '#disabled' => TRUE,
    ];

    $rate_sheet_items = $this->database->select('rate_sheet_item', 'rsi')
      ->fields('rsi', ['id', 'attribute_name', 'tiered_calculation'])
      ->condition('rate_sheet_id', $id)
      ->orderBy('id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $ranges_by_item_id = [];
    $rate_sheet_item_ids = array_column($rate_sheet_items, 'id');

    if (!empty($rate_sheet_item_ids)) {
      $rate_sheet_item_ranges = $this->database->select('rate_sheet_item_range', 'rsir')
        ->fields('rsir', [
          'id',
          'rate_sheet_item_id',
          'from_range',
          'to_range',
          'partial_range',
          'success_rate',
        ])
        ->condition('rate_sheet_item_id', $rate_sheet_item_ids, 'IN')
        ->orderBy('rate_sheet_item_id', 'ASC')
        ->orderBy('id', 'ASC')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);

      foreach ($rate_sheet_item_ranges as $range) {
        $ranges_by_item_id[$range['rate_sheet_item_id']][] = $range;
      }
    }

    foreach ($rate_sheet_items as &$rate_sheet_item) {
      $rate_sheet_item['ranges'] = $ranges_by_item_id[$rate_sheet_item['id']] ?? [];
    }
    unset($rate_sheet_item);

    $form['rate_sheet_items'] = [
      '#type' => 'value',
      '#value' => $rate_sheet_items,
    ];

    $form['#theme'] = 'new_rate_sheet_review';

    $user_roles = $this->currentUser()->getRoles();
    $allowed_roles = ['financial_rate_sheet_approval_level_1', 'financial_rate_sheet_approval_level_2'];

    // Check rate sheet status.
    $rateSheetService = \Drupal::service('zcs_api_attributes.rate_sheet');
    $rateSheetStatus = $rateSheetService->getRateSheetStatus($id);

    // Check if the user has already approved or denied.
    $user_id = $this->currentUser()->id();
    $user_has_acted = $this->database->select('rate_sheet_status', 'rss')
      ->condition('rate_sheet_id', $id)
      ->condition('created_by', $user_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    if (array_intersect($allowed_roles, $user_roles) && $rateSheetStatus === 'Pending' && !$user_has_acted) {
      $form['status'] = [
        '#type' => 'select',
        '#options' => [2 => 'Approve', 3 => 'Reject'],
        '#required' => TRUE,
      ];
      $form['approve'] = [
        '#type' => 'submit',
        '#value' => 'Save',
      ];
    }

    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-ranges';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-ux';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-review';
    $form['#attached']['library'][] = 'zcs_api_attributes/discount-sheet';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_id = $this->currentUser()->id();
    $rate_sheet_id = $form_state->getValue('rate_sheet_id');
    $status = $form_state->getValue('status');

    // Check user roles.
    $allowed_roles = ['financial_rate_sheet_approval_level_1', 'financial_rate_sheet_approval_level_2'];
    $user_roles = $this->currentUser()->getRoles();

    if (!array_intersect($allowed_roles, $user_roles)) {
      \Drupal::messenger()->addError($this->t('You do not have permission to approve or reject this rate sheet.'));
      return;
    }

    // Check if the user has already submitted a status.
    $user_has_acted = $this->database->select('rate_sheet_status', 'rss')
      ->condition('rate_sheet_id', $rate_sheet_id)
      ->condition('created_by', $user_id)
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($user_has_acted) {
      \Drupal::messenger()->addError($this->t('You have already submitted a status for this rate sheet.'));
      return;
    }

    // Use the RateSheetService to insert the new status.
    $rateSheetService = \Drupal::service('zcs_api_attributes.rate_sheet');
    $rateSheetService->insertRateSheetStatus(intval($rate_sheet_id), $status, $user_id);

    // Log the action.
    \Drupal::logger('zcs_api_attributes')->notice('User @user_id submitted status @status for rate sheet @rate_sheet_id.', [
      '@user_id' => $user_id,
      '@status' => $status,
      '@rate_sheet_id' => $rate_sheet_id,
    ]);

    // Redirect to the rate sheet list.
    $form_state->setRedirect('zcs_api_attributes.rate_sheet_list');
  }

}
