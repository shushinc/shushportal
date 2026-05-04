<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Component\Serialization\Json;
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
      ->fields('rs', ['id', 'name', 'currency', 'markup_retail'])
      ->condition('id', $id)
      ->execute()->fetchObject();
    
    $form['name'] = [
      '#type' => 'textfield',
      '#default_value' => $data->name,
      '#description' => $this->t('The rate sheet name.'),
      '#disabled' => TRUE,
    ];

    // Currencies form select
    $form['currencies'] = [
      '#type' => 'textfield',
      '#options' => $data->currency,
      '#default_value' => \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US',
      '#disabled' => TRUE,
      '#weight' => 0,
    ];

    // Effective date
    $form['attribute_date'] = [
      '#type' => 'textfield',
      '#default_value' => date('M d, Y', $data->effective_date),
      '#weight' => 1,
      '#disabled' => TRUE,
    ];

    // Markup retail
    $form['retail_markup_percentage'] = [
      '#type' => 'number',
      '#default_value' => $data->markup_retail,
      '#disabled' => TRUE,
    ];

    // Fetch rate_sheet_item data
    $rateSheetItems = $this->database->select('rate_sheet_item', 'rsi')
      ->fields('rsi', ['id', 'attribute_name', 'from_range', 'to_range', 'partial_range', 'success_rate', 'tiered_calculation'])
      ->condition('rate_sheet_id', $id)
      ->execute()->fetchAll();

    $form['rate_sheet_items'] = [
      '#type' => 'value',
      '#value' => $rateSheetItems,
    ];

    $form['#theme'] = 'new_rate_sheet_review';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';

    $aprovers_roles = ['financial_rate_sheet_approval_level_1', 'financial_rate_sheet_approval_level_2'];
    $user_roles = $this->currentUser()->getRoles();
  
    // Check user roles
    $user_roles = $this->currentUser()->getRoles();
    $allowed_roles = ['financial_rate_sheet_approval_level_1', 'financial_rate_sheet_approval_level_2'];

    // Check rate sheet status
    $rateSheetService = \Drupal::service('zcs_api_attributes.rate_sheet');
    $rateSheetStatus = $rateSheetService->getRateSheetStatus($id);

    // Check if the user has already approved or denied
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
    
  }

}
