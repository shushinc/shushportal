<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the create rate sheet form.
 */
class CreateRateSheetForm extends FormBase {

  /**
   * EntityTypeManager $entityTypeManager.
   */
  protected $entityTypeManager;

  /**
   * Array $list.
   */
  protected $list;

  /**
   * Connection $connection.
   */
  protected $database;

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
    return 'rate_sheet';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $defaultCurrency = \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US';
    $number = new \NumberFormatter($defaultCurrency, \NumberFormatter::CURRENCY);
    $symbol = $number->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

    // To fetch currencies.
    $currencies = [];
    foreach ($this->list as $list) {
      if (!empty($list['locale'])) {
        $currencies[$list['locale']] = $list['currency'] . ' (' . $list['alphabeticCode'] . ')';
      }
    }

    // Rate sheet name.
    $form['name'] = [
      '#type' => 'textfield',
      '#default_value' => '',
      '#description' => $this->t('The rate sheet name.'),
      '#required' => TRUE,
    ];

    // Currencies form select.
    $form['currencies'] = [
      '#type' => 'select',
      '#options' => $currencies,
      '#default_value' => \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US',
      '#disabled' => TRUE,
      '#weight' => 0,
    ];

    // Effective date.
    $form['attribute_date'] = [
      '#type' => 'date',
      '#default_value' => date('Y-m-d'),
      '#weight' => 1,
      '#attributes' => [
        'min' => date('Y-m-d'),
      ],
    ];

    // Markup retail.
    $form['retail_markup_percentage'] = [
      '#type' => 'number',
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $nids = [];

    $form['rate_sheet_item_ranges'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    // Load api attributes.
    $contents = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        $attribute_id = $content->id();
        $nids[] = $attribute_id;

        $form['rate_sheet_item_ranges'][$attribute_id][0]['from_range'] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => 0.000,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
          '#attributes' => [
            'data-rate-sheet-range-field' => 'from_range',
            'data-attribute-id' => $attribute_id,
            'data-range-index' => 0,
          ],
        ];

        $form['rate_sheet_item_ranges'][$attribute_id][0]['to_range'] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => 0.000,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
          '#attributes' => [
            'data-rate-sheet-range-field' => 'to_range',
            'data-attribute-id' => $attribute_id,
            'data-range-index' => 0,
          ],
        ];

        $form['rate_sheet_item_ranges'][$attribute_id][0]['partial_range'] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => 0.000,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
          '#attributes' => [
            'data-rate-sheet-range-field' => 'partial_range',
            'data-attribute-id' => $attribute_id,
            'data-range-index' => 0,
          ],
        ];

        $form['rate_sheet_item_ranges'][$attribute_id][0]['success_rate'] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => 0.000,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
          '#attributes' => [
            'data-rate-sheet-range-field' => 'success_rate',
            'data-attribute-id' => $attribute_id,
            'data-range-index' => 0,
          ],
        ];

        $form['tiered_calculation_' . $attribute_id] = [
          '#type' => 'checkbox',
          '#default_value' => FALSE,
        ];
      }
    }

    $form['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(',', $nids),
    ];

    $form['range_currency_symbol'] = [
      '#type' => 'hidden',
      '#value' => $symbol,
    ];

    $form['rate_sheet_item_ranges_payload'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#attributes' => [
        'data-rate-sheet-ranges-payload' => '',
      ],
    ];

    $form['#theme'] = 'create_rate_sheet';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-ranges';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Rate Sheet'),
    ];

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

    $values = $form_state->getValues();
    $user_input = $form_state->getUserInput();
    $submitted_ranges = $user_input['rate_sheet_item_ranges'] ?? $values['rate_sheet_item_ranges'] ?? [];
    $payload = $values['rate_sheet_item_ranges_payload'] ?? $user_input['rate_sheet_item_ranges_payload'] ?? '';

    if (!empty($payload)) {
      $decoded_payload = json_decode($payload, TRUE);

      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_payload) && !empty($decoded_payload)) {
        $submitted_ranges = $decoded_payload;
      }
    }

    $nids = array_filter(explode(',', $values['nodes'] ?? ''));
    $transaction = $this->database->startTransaction();

    try {

      $new_rate_sheet_id = $this->database->insert('rate_sheet')
        ->fields([
          'name',
          'currency',
          'created_by',
          'markup_retail',
          'created_date',
          'effective_date',
        ])
        ->values([
          $values['name'],
          $values['currencies'],
          $this->currentUser()->id(),
          $values['retail_markup_percentage'],
          \Drupal::time()->getRequestTime(),
          strtotime($values['attribute_date']),
        ])
        ->execute();

      // Creating the default status.
      $this->database->insert('rate_sheet_status')
        ->fields([
          'rate_sheet_id',
          'status_name',
          'date',
          'created_by',
        ])->values([
          $new_rate_sheet_id,
          'Pending',
          \Drupal::time()->getRequestTime(),
          $this->currentUser()->id(),
        ])
        ->execute();

      // Logging the action.
      $this->database->insert('action_log')
        ->fields([
          'action_type',
          'entity_target_type',
          'entity_target_id',
          'created_by',
          'created_date',
          'log_data',
        ])->values([
          'CREATING',
          'RATE_SHEET',
          $new_rate_sheet_id,
          $this->currentUser()->id(),
          \Drupal::time()->getRequestTime(),
          '',
        ])
        ->execute();

      foreach ($nids as $nid) {
        $range_items = $submitted_ranges[$nid] ?? [];
        $tiered_calculation = $values["tiered_calculation_{$nid}"] ?? 0;

        $node = Node::load($nid);
        if (!$node) {
          continue;
        }

        $rate_sheet_item_id = $this->database->insert('rate_sheet_item')
          ->fields([
            'rate_sheet_id',
            'api_attribute_id',
            'tiered_calculation',
            'attribute_name',
          ])
          ->values([
            $new_rate_sheet_id,
            $nid,
            $tiered_calculation,
            $node->getTitle(),
          ])
          ->execute();

        ksort($range_items, SORT_NUMERIC);

        foreach ($range_items as $range_item) {
          if (!is_array($range_item)) {
            continue;
          }

          $this->database->insert('rate_sheet_item_range')
            ->fields([
              'rate_sheet_item_id',
              'from_range',
              'to_range',
              'success_rate',
              'partial_range',
            ])
            ->values([
              $rate_sheet_item_id,
              $range_item['from_range'] ?? 0,
              $range_item['to_range'] ?? 0,
              $range_item['success_rate'] ?? 0,
              $range_item['partial_range'] ?? 0,
            ])
            ->execute();
        }
      }
    }
    catch (\Exception $e) {
      $transaction->rollBack();
    }

    // Commit the transaction by unsetting the $transaction variable.
    unset($transaction);
    $form_state->setRedirect('zcs_api_attributes.rate_sheet_list');
  }

}
