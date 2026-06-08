<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\zcs_api_attributes\Service\RateSheetService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Access\AccessResult;

/**
 * Provides the edit rate sheet form.
 */
class EditRateSheetForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The currency list.
   *
   * @var array
   */
  protected $list;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The rate sheet service.
   *
   * @var \Drupal\zcs_api_attributes\Service\RateSheetService
   */
  protected $rateSheetService;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs an EditRateSheetForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\zcs_api_attributes\Service\RateSheetService $rate_sheet_service
   *   The rate sheet service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $date_formatter,
    RateSheetService $rate_sheet_service,
    MessengerInterface $messenger,
    Connection $database,
    AccountProxyInterface $current_user
  ) {
    $this->list = require __DIR__ . '/../../resources/currencies.php';
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->rateSheetService = $rate_sheet_service;
    $this->messenger = $messenger;
    $this->database = $database;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('zcs_api_attributes.rate_sheet_service'),
      $container->get('messenger'),
      $container->get('database'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_rate_sheet';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = 0) {
    // Load the rate sheet.
    $rate_sheet = $this->database->select('rate_sheet', 'rs')
      ->fields('rs', ['id', 'name', 'currency', 'markup_retail', 'effective_date', 'created_by'])
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$rate_sheet) {
      $this->messenger->addError($this->t('Rate sheet not found.'));
      return $form;
    }

    // Check if current user is the owner.
    $is_owner = $rate_sheet->created_by == $this->currentUser->id();
    if (!$is_owner) {
      $this->messenger->addError($this->t('You do not have permission to view this rate sheet.'));
      return $form;
    }

    // Check if rate sheet is cancelled
    $status = $this->rateSheetService->getRateSheetStatus($id);
    $is_cancelled = strtolower($status) === 'cancelled';

    if ($is_cancelled) {
      $this->messenger->addWarning($this->t('This rate sheet has been cancelled and cannot be edited.'));
    }

    // Check if there are unresolved reject comments
    $can_edit = !$is_cancelled && $this->rateSheetService->hasUnresolvedComments($id);

    $config = $this->configFactory->get('zcs_custom.settings');
    $defaultCurrency = $config->get('currency') ?? 'en_US';
    $number = new \NumberFormatter($defaultCurrency, \NumberFormatter::CURRENCY);
    $symbol = $number->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

    // To fetch currencies.
    $currencies = [];
    foreach ($this->list as $list) {
      if (!empty($list['locale'])) {
        $currencies[$list['locale']] = $list['currency'] . ' (' . $list['alphabeticCode'] . ')';
      }
    }

    $currency_label = $rate_sheet->currency;
    foreach ($this->list as $currency) {
      if (!empty($currency['locale']) && $currency['locale'] === $rate_sheet->currency) {
        $currency_label = $currency['currency'] . ' (' . $currency['alphabeticCode'] . ')';
        break;
      }
    }

    $form['rate_sheet_id'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    // Rate sheet name.
    $form['name'] = [
      '#type' => 'textfield',
      '#default_value' => $rate_sheet->name,
      '#description' => $this->t('The rate sheet name.'),
      '#required' => TRUE,
    ];

    // Currencies form select.
    $form['currencies'] = [
      '#type' => 'textfield',
      '#default_value' => $currency_label,
      '#disabled' => TRUE,
      '#weight' => 0,
    ];

    // Effective date.
    $form['attribute_date'] = [
      '#type' => 'date',
      '#default_value' => date('Y-m-d', $rate_sheet->effective_date),
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
      '#default_value' => $rate_sheet->markup_retail,
    ];

    // Load rate sheet items.
    $rate_sheet_items = $this->database->select('rate_sheet_item', 'rsi')
      ->fields('rsi', ['id', 'attribute_name', 'tiered_calculation', 'api_attribute_id'])
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

    $nids = [];
    $form['rate_sheet_item_ranges'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($rate_sheet_items as $item) {
      $attribute_id = $item['api_attribute_id'];
      $nids[] = $attribute_id;
      $ranges = $ranges_by_item_id[$item['id']] ?? [];

      // Filter out ranges where from_range is 0 and all other values are 0 or -1
      // (these are unfilled default ranges that shouldn't be shown in edit mode)
      $filtered_ranges = [];
      foreach ($ranges as $range) {
        // Keep the range if:
        // 1. from_range is not 0, OR
        // 2. Any of the rate values (partial_range or success_rate) are non-zero
        if ($range['from_range'] != 0 || $range['partial_range'] != 0 || $range['success_rate'] != 0) {
          $filtered_ranges[] = $range;
        }
      }

      // If no valid ranges exist after filtering, don't create any form elements
      // (the template will show the default single range)
      if (empty($filtered_ranges)) {
        continue;
      }

      foreach ($filtered_ranges as $range_index => $range) {
        $form['rate_sheet_item_ranges'][$attribute_id][$range_index]['from_range'] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => $range['from_range'],
          '#step' => 0.001,
          '#attributes' => [
            'data-rate-sheet-range-field' => 'from_range',
            'data-attribute-id' => $attribute_id,
            'data-range-index' => $range_index,
          ],
        ];

        $form['rate_sheet_item_ranges'][$attribute_id][$range_index]['to_range'] = [
          '#type' => 'number',
          '#min' => -1,
          '#default_value' => $range['to_range'],
          '#step' => 0.001,
          '#attributes' => [
            'data-rate-sheet-range-field' => 'to_range',
            'data-attribute-id' => $attribute_id,
            'data-range-index' => $range_index,
          ],
        ];

        $form['rate_sheet_item_ranges'][$attribute_id][$range_index]['partial_range'] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => $range['partial_range'],
          '#step' => 0.001,
          '#attributes' => [
            'data-rate-sheet-range-field' => 'partial_range',
            'data-attribute-id' => $attribute_id,
            'data-range-index' => $range_index,
          ],
        ];

        $form['rate_sheet_item_ranges'][$attribute_id][$range_index]['success_rate'] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => $range['success_rate'],
          '#step' => 0.001,
          '#attributes' => [
            'data-rate-sheet-range-field' => 'success_rate',
            'data-attribute-id' => $attribute_id,
            'data-range-index' => $range_index,
          ],
        ];
      }

      $form['tiered_calculation_' . $attribute_id] = [
        '#type' => 'checkbox',
        '#default_value' => $item['tiered_calculation'],
      ];
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

    // Get reject comments.
    $reject_comments = $this->rateSheetService->getRateSheetRejectComments($id);

    $form['reject_comments'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    foreach ($reject_comments as $comment) {
      $form['reject_comments'][$comment['id']] = [
        '#type' => 'checkbox',
        '#title' => $this->t('@email (@date): @comment', [
          '@email' => $comment['created_by'],
          '@date' => date('M d, Y H:i', $comment['created_date']),
          '@comment' => $comment['comment'],
        ]),
        '#default_value' => $comment['solved'],
      ];
    }

    $form['reject_comments_data'] = [
      '#type' => 'value',
      '#value' => $reject_comments,
    ];

    $form['can_edit'] = [
      '#type' => 'value',
      '#value' => $can_edit,
    ];

    $form['is_cancelled'] = [
      '#type' => 'value',
      '#value' => $is_cancelled,
    ];

    // Always show actions container for the owner
    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-actions d-flex gap-2']],
    ];

    // Only show submit button if user can edit (has unresolved comments) and not cancelled
    if ($can_edit && !$is_cancelled) {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Update Rate Sheet'),
      ];
    }

    // Always show cancel button if not already cancelled
    if (!$is_cancelled) {
      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancel Rate Sheet'),
        '#submit' => ['::cancelRateSheet'],
        '#attributes' => [
          'class' => ['button', 'button--danger'],
          'data-rate-sheet-cancel-button' => '',
        ],
      ];
    }

    $form['#theme'] = 'create_rate_sheet';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-ranges';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-cancel';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Validate rate sheet name.
    if (empty(trim($values['name']))) {
      $form_state->setErrorByName('name', $this->t('Rate sheet name is required.'));
    }

    // Validate markup percentage.
    $markup = $values['retail_markup_percentage'] ?? NULL;
    if (!is_numeric($markup) || $markup < 1) {
      $form_state->setErrorByName('retail_markup_percentage', $this->t('Markup percentage must be at least 1.'));
    }

    // Validate effective date.
    $effective_date = $values['attribute_date'] ?? NULL;
    if (empty($effective_date)) {
      $form_state->setErrorByName('attribute_date', $this->t('Effective date is required.'));
    }
    else {
      $date_timestamp = strtotime($effective_date);
      $today = strtotime(date('Y-m-d'));
      if ($date_timestamp < $today) {
        $form_state->setErrorByName('attribute_date', $this->t('Effective date cannot be in the past.'));
      }
    }

    // Validate JSON payload.
    $payload = $values['rate_sheet_item_ranges_payload'] ?? '';
    if (!empty($payload)) {
      $decoded = json_decode($payload, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $form_state->setErrorByName('rate_sheet_item_ranges_payload', $this->t('Invalid range data format.'));
      }
      elseif (!is_array($decoded)) {
        $form_state->setErrorByName('rate_sheet_item_ranges_payload', $this->t('Range data must be an array.'));
      }
      else {
        // Validate range values.
        foreach ($decoded as $attribute_id => $ranges) {
          if (!is_array($ranges)) {
            continue;
          }
          foreach ($ranges as $range_index => $range) {
            if (!is_array($range)) {
              continue;
            }

            $from = $range['from_range'] ?? NULL;
            $to = $range['to_range'] ?? NULL;

            if (!is_numeric($from) || $from < 0) {
              $form_state->setError($form, $this->t('Invalid "from" range value for attribute @id.', ['@id' => $attribute_id]));
            }

            if ($to != -1 && (!is_numeric($to) || $to < $from)) {
              $form_state->setError($form, $this->t('Invalid "to" range value for attribute @id. Must be greater than "from" or -1 for unbounded.', ['@id' => $attribute_id]));
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $rate_sheet_id = $values['rate_sheet_id'];
    $payload = $values['rate_sheet_item_ranges_payload'] ?? '';
    $submitted_ranges = [];

    // Parse the JSON payload.
    if (!empty($payload)) {
      $decoded_payload = json_decode($payload, TRUE);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_payload)) {
        $submitted_ranges = $decoded_payload;
      }
    }

    // Fallback to form values if payload is empty.
    if (empty($submitted_ranges)) {
      $submitted_ranges = $values['rate_sheet_item_ranges'] ?? [];
    }

    $transaction = $this->database->startTransaction();

    try {
      // Update rate sheet.
      $this->database->update('rate_sheet')
        ->fields([
          'name' => $values['name'],
          'markup_retail' => $values['retail_markup_percentage'],
          'effective_date' => strtotime($values['attribute_date']),
        ])
        ->condition('id', $rate_sheet_id)
        ->execute();

      // Update rate sheet item ranges.
      $rate_sheet_items = $this->database->select('rate_sheet_item', 'rsi')
        ->fields('rsi', ['id', 'api_attribute_id'])
        ->condition('rate_sheet_id', $rate_sheet_id)
        ->execute()
        ->fetchAllKeyed(1, 0);

      foreach ($submitted_ranges as $attribute_id => $ranges) {
        if (!isset($rate_sheet_items[$attribute_id])) {
          continue;
        }

        $rate_sheet_item_id = $rate_sheet_items[$attribute_id];

        // Delete existing ranges for this item.
        $this->database->delete('rate_sheet_item_range')
          ->condition('rate_sheet_item_id', $rate_sheet_item_id)
          ->execute();

        // Update tiered calculation.
        $tiered_calculation = count($ranges) > 1 ? 1 : 0;
        $this->database->update('rate_sheet_item')
          ->fields(['tiered_calculation' => $tiered_calculation])
          ->condition('id', $rate_sheet_item_id)
          ->execute();

        // Insert new ranges.
        $range_items = array_filter($ranges, 'is_array');
        ksort($range_items, SORT_NUMERIC);
        $last_range_key = !empty($range_items) ? array_key_last($range_items) : NULL;

        foreach ($range_items as $range_index => $range_item) {
          if (!is_array($range_item)) {
            continue;
          }

          $to_range = ((string) $range_index === (string) $last_range_key) ? -1 : ($range_item['to_range'] ?? 0);

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
              $to_range,
              $range_item['success_rate'] ?? 0,
              $range_item['partial_range'] ?? 0,
            ])
            ->execute();
        }
      }

      // Update solved status for reject comments.
      $reject_comments_values = $values['reject_comments'] ?? [];
      foreach ($reject_comments_values as $comment_id => $solved) {
        $this->database->update('action_log')
          ->fields(['solved' => $solved ? 1 : 0])
          ->condition('id', $comment_id)
          ->execute();
      }

      // Log the action.
      $this->database->insert('action_log')
        ->fields([
          'action_type',
          'entity_target_type',
          'entity_target_id',
          'created_by',
          'created_date',
          'log_data',
        ])
        ->values([
          'STATUS_UPDATE',
          'RATE_SHEET',
          $rate_sheet_id,
          $this->currentUser->id(),
          \Drupal::time()->getRequestTime(),
          "User {$this->currentUser->id()} updated rate sheet {$rate_sheet_id}.",
        ])
        ->execute();

      // Send email notification to approvers.
      $this->sendUpdateEmail($rate_sheet_id, $values);

      unset($transaction);

      $this->messenger->addStatus($this->t('Rate sheet "@name" has been updated successfully.', [
        '@name' => $values['name'],
      ]));

      $form_state->setRedirect('zcs_api_attributes.rate_sheet_list');
    }
    catch (\Exception $e) {
      if (isset($transaction)) {
        $transaction->rollBack();
      }

      $this->messenger->addError($this->t('An error occurred while updating the rate sheet: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Form submission handler for cancelling a rate sheet.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function cancelRateSheet(array &$form, FormStateInterface $form_state) {
    $rate_sheet_id = $form_state->getValue('rate_sheet_id');

    try {
      $this->rateSheetService->cancelRateSheet($rate_sheet_id, $this->currentUser->id());

      $this->messenger->addStatus($this->t('Rate sheet has been cancelled successfully. This action cannot be undone.'));

      $form_state->setRedirect('zcs_api_attributes.rate_sheet_list');
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('An error occurred while cancelling the rate sheet: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Sends an email notification to approvers about rate sheet update.
   *
   * @param int $rate_sheet_id
   *   The rate sheet ID.
   * @param array $values
   *   The form values.
   */
  protected function sendUpdateEmail($rate_sheet_id, array $values) {
    try {
      $userMails = [];
      $users = $this->entityTypeManager->getStorage('user')->loadByProperties(
        ['roles' => [
          'financial_rate_sheet_approval_level_1',
          'financial_rate_sheet_approval_level_2',
        ], 'status' => 1]
      );
      foreach ($users as $user) {
        if ($user) {
          $userMails[] = $user->mail->value;
        }
      }

      if (empty($userMails)) {
        return;
      }

      $mailManager = \Drupal::service('plugin.manager.mail');

      $modulePath = \Drupal::service('extension.path.resolver')->getPath('module', 'zcs_api_attributes');
      $path = $modulePath . '/templates/rate_sheet_update_mail.html.twig';

      // Check if template exists, otherwise use approval template.
      if (!file_exists($path)) {
        $path = $modulePath . '/templates/rate_sheet_approval_mail.html.twig';
      }

      $rendered = \Drupal::service('twig')->load($path)->render([
        'user' => $this->entityTypeManager->getStorage('user')->load($this->currentUser->id())->mail->value,
        'effective_date' => $values['attribute_date'],
        'rate_sheet_name' => $values['name'],
        'approval' => Link::createFromRoute('Review', 'zcs_api_attributes.rate_sheet_list')->toString(),
        'site_name' => $this->config('system.site')->get('name'),
      ]);

      $params['message'] = Markup::create(nl2br($rendered));
      $params['subject'] = $this->t('Rate Sheet "@name" has been updated', [
        '@name' => $values['name'],
      ]);
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = TRUE;

      foreach ($userMails as $mail) {
        $mailManager->mail('zcs_api_attributes', 'rate_sheet_update', $mail, $langcode, $params, NULL, $send);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('zcs_api_attributes')->error('Error sending update email: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
