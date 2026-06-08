<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\zcs_api_attributes\Service\RateSheetService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;

/**
 * Provides the create rate sheet form.
 */
class CreateRateSheetForm extends FormBase {

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
   * Constructs a CreateRateSheetForm object.
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
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $date_formatter,
    RateSheetService $rate_sheet_service,
    MessengerInterface $messenger
  ) {
    $this->list = require __DIR__ . '/../../resources/currencies.php';
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->rateSheetService = $rate_sheet_service;
    $this->messenger = $messenger;
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
      $container->get('messenger')
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
      '#default_value' => $config->get('currency') ?? 'en_US',
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
          '#attributes' => [
            'data-rate-sheet-range-field' => 'from_range',
            'data-attribute-id' => $attribute_id,
            'data-range-index' => 0,
          ],
        ];

        $form['rate_sheet_item_ranges'][$attribute_id][0]['to_range'] = [
          '#type' => 'number',
          '#min' => -1,
          '#default_value' => -1,
          '#step' => 0.001,
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
          // '#field_prefix' => $symbol,
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
          // '#field_prefix' => $symbol,
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

    $nids = array_filter(explode(',', $values['nodes'] ?? ''));

    try {
      $rate_sheet_id = $this->rateSheetService->createRateSheet([
        'name' => $values['name'],
        'currency' => $values['currencies'],
        'markup_retail' => $values['retail_markup_percentage'],
        'effective_date' => strtotime($values['attribute_date']),
        'attribute_ids' => $nids,
        'ranges' => $submitted_ranges,
      ]);

      if ($rate_sheet_id) {
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

        $mailManager = \Drupal::service('plugin.manager.mail');

        $modulePath = \Drupal::service('extension.path.resolver')->getPath('module', 'zcs_api_attributes');
        $path = $modulePath . '/templates/rate_sheet_approval_mail.html.twig';

        $rendered = \Drupal::service('twig')->load($path)->render([
          'user' => $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id())->mail->value,
          'effective_date' => $values['attribute_date'],
          'approval' => Link::createFromRoute('Approval', 'zcs_api_attributes.rate_sheet_list')->toString(),
          'site_name' => $this->config('system.site')->get('name'),
        ]);

        $params['message'] = Markup::create(nl2br($rendered));
        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $send = TRUE;

        foreach ($userMails as $mail) {
          $emails[] = $mailManager->mail('zcs_api_attributes', 'rate_sheet', $mail, $langcode, $params, NULL, $send);
        }

        $this->messenger->addStatus($this->t('Rate sheet "@name" has been created successfully. An email notification has been sent.', [
          '@name' => $values['name'],
        ]));

        $form_state->setRedirect('zcs_api_attributes.rate_sheet_list');
      }
      else {
        $this->messenger->addError($this->t('Failed to create rate sheet. Please try again.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('An error occurred while creating the rate sheet: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

}
