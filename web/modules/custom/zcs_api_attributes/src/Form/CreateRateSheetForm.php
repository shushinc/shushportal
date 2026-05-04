<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\node\Entity\Node;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
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

    // Rate sheet name
    $form['name'] = [
      '#type' => 'textfield',
      '#default_value' => '',
      '#description' => $this->t('The rate sheet name.'),
      '#required' => TRUE,
    ];

    // Currencies form select
    $form['currencies'] = [
      '#type' => 'select',
      '#options' => $currencies,
      '#default_value' => \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US',
      '#disabled' => TRUE,
      '#weight' => 0,
    ];

    // Effective date
    $form['attribute_date'] = [
      '#type' => 'date',
      '#default_value' => date('Y-m-d'),
      '#weight' => 1,
      '#attributes' => [
        'min' => date('Y-m-d'), // disables previous dates
      ],
    ];

    // Markup retail
    $form['retail_markup_percentage'] = [
      '#type' => 'number',
      '#min' => 1,      
      '#step' => 1,
      '#required' => TRUE,
    ];

    $nids = [];

    // Load api attributes
    $contents = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        $nids[] = $content->id();
        $form['from_range_' . $content->id()] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => 0.000,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
        ];
        $form['to_range_' . $content->id()] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => 0.000,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
        ];
        $form['partial_range_' . $content->id()] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => 0.000,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
        ];
        $form['success_rate_' . $content->id()] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => 0.000,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
        ];
        $form['tiered_calculation_' . $content->id()] = [
          '#type' => 'checkbox',
          '#default_value' => FALSE,
        ];
      }
    }

    $form['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(",", $nids),
    ];

    $form['#theme'] = 'create_rate_sheet';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save Rate Sheet',
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
    $nids = explode(",", $values['nodes']);
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
          $values['name'], // currency_locale
          $values['currencies'], // effective_date
          $this->currentUser()->id(), // created_by
          $values['retail_markup_percentage'],
          \Drupal::time()->getRequestTime(), // created
          strtotime($values['attribute_date']),
        ])
        ->execute();
      
      // Creating the default status
      $this->database->insert('rate_sheet_status')
        ->fields([
          'rate_sheet_id',
          'status_name',
          'date',
          'created_by'
        ])->values([
          $new_rate_sheet_id,
          'Pending',
          \Drupal::time()->getRequestTime(),
          $this->currentUser()->id(),
        ])
        ->execute();
      
      // Logging the action
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
        $from_range = $values["from_range_{$nid}"] ?? 0;
        $to_range = $values["to_range_{$nid}"] ?? 0;
        $partial_range = $values["partial_range_{$nid}"] ?? 0;
        $success_rate = $values["success_rate_{$nid}"] ?? 0;
        $tiered_calculation = $values["tiered_calculation_{$nid}"] ?? 0;

        $node = Node::load($nid);

        $this->database->insert('rate_sheet_item')
          ->fields([
            'rate_sheet_id',
            'api_attribute_id',
            'from_range',
            'to_range',
            'success_rate',
            'partial_range',
            'tiered_calculation',
            'attribute_name',
          ])
          ->values([
            $new_rate_sheet_id,
            $nid,
            $from_range,
            $to_range,
            $success_rate,
            $partial_range,
            $tiered_calculation,
            $node->getTitle(),
          ])
          ->execute();
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
