<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
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

    $currency_label = $data->currency;
    foreach ($this->list as $currency) {
      if (!empty($currency['locale']) && $currency['locale'] === $data->currency) {
        $currency_label = $currency['currency'] . ' (' . $currency['alphabeticCode'] . ')';
        break;
      }
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
      '#default_value' => $currency_label,
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
    $rateSheetService = \Drupal::service('zcs_api_attributes.rate_sheet_service');
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
        '#attributes' => [
          'class' => ['rate-sheet-review-status-select'],
        ],
      ];
      $form['reject_comment'] = [
        '#type' => 'hidden',
        '#default_value' => '',
        '#attributes' => [
          'data-reject-comment-field' => '',
        ],
      ];
      $form['approve'] = [
        '#type' => 'submit',
        '#value' => 'Save',
        '#attributes' => [
          'class' => ['rate-sheet-review-submit'],
        ],
      ];
    }

    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-ranges';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-ux';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-review';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-reject-modal';
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
    $reject_comment = $form_state->getValue('reject_comment');

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

    // Validate reject comment if status is reject (3).
    if ($status == 3 && empty(trim((string) $reject_comment))) {
      \Drupal::messenger()->addError($this->t('A comment is required when rejecting a rate sheet.'));
      return;
    }

    // Use the RateSheetService to insert the new status.
    $rateSheetService = \Drupal::service('zcs_api_attributes.rate_sheet_service');
    $rateSheetService->insertRateSheetStatus(intval($rate_sheet_id), $status, $user_id, $reject_comment);

    // Send email notification to the rate sheet creator.
    $this->sendStatusChangeEmail($rate_sheet_id, $status, $reject_comment);

    // Log the action.
    \Drupal::logger('zcs_api_attributes')->notice('User @user_id submitted status @status for rate sheet @rate_sheet_id.', [
      '@user_id' => $user_id,
      '@status' => $status,
      '@rate_sheet_id' => $rate_sheet_id,
    ]);

    // Redirect to the rate sheet list.
    $form_state->setRedirect('zcs_api_attributes.rate_sheet_list');
  }

  /**
   * Sends an email notification to the rate sheet creator about status change.
   *
   * @param int $rate_sheet_id
   *   The rate sheet ID.
   * @param int $status
   *   The new status (2 = Approved, 3 = Rejected).
   * @param string|null $reject_comment
   *   Optional rejection comment.
   */
  protected function sendStatusChangeEmail($rate_sheet_id, $status, $reject_comment = NULL) {
    try {
      // Get rate sheet details.
      $rate_sheet = $this->database->select('rate_sheet', 'rs')
        ->fields('rs', ['name', 'created_by', 'effective_date'])
        ->condition('id', $rate_sheet_id)
        ->execute()
        ->fetchObject();

      if (!$rate_sheet) {
        \Drupal::logger('zcs_api_attributes')->warning('Rate sheet @id not found for email notification.', ['@id' => $rate_sheet_id]);
        return;
      }

      // Load the creator user.
      $creator = $this->entityTypeManager->getStorage('user')->load($rate_sheet->created_by);
      if (!$creator || !$creator->getEmail()) {
        \Drupal::logger('zcs_api_attributes')->warning('Creator user not found or has no email for rate sheet @id.', ['@id' => $rate_sheet_id]);
        return;
      }

      // Load the reviewer user.
      $reviewer = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());
      $reviewer_email = $reviewer ? $reviewer->getEmail() : 'Unknown';

      // Prepare email parameters.
      $status_text = $status == 2 ? 'Approved' : 'Rejected';
      $effective_date = date('M d, Y', $rate_sheet->effective_date);

      $modulePath = \Drupal::service('extension.path.resolver')->getPath('module', 'zcs_api_attributes');
      $templatePath = $modulePath . '/templates/rate_sheet_status_change_mail.html.twig';

      $rendered = \Drupal::service('twig')->load($templatePath)->render([
        'rate_sheet_name' => $rate_sheet->name,
        'status' => $status_text,
        'effective_date' => $effective_date,
        'reviewer_email' => $reviewer_email,
        'reject_comment' => $reject_comment,
        'rate_sheet_list_url' => \Drupal\Core\Url::fromRoute('zcs_api_attributes.rate_sheet_list', [], ['absolute' => TRUE])->toString(),
        'site_name' => $this->config('system.site')->get('name'),
      ]);

      $params['message'] = \Drupal\Core\Render\Markup::create($rendered);
      $params['subject'] = $this->t('Rate Sheet "@name" has been @status', [
        '@name' => $rate_sheet->name,
        '@status' => $status_text,
      ]);

      $mailManager = \Drupal::service('plugin.manager.mail');
      $langcode = $creator->getPreferredLangcode();

      $result = $mailManager->mail(
        'zcs_api_attributes',
        'rate_sheet_status_change',
        $creator->getEmail(),
        $langcode,
        $params,
        NULL,
        TRUE
      );

      if ($result['result']) {
        \Drupal::logger('zcs_api_attributes')->info('Status change email sent to @email for rate sheet @id.', [
          '@email' => $creator->getEmail(),
          '@id' => $rate_sheet_id,
        ]);
      }
      else {
        \Drupal::logger('zcs_api_attributes')->error('Failed to send status change email to @email for rate sheet @id.', [
          '@email' => $creator->getEmail(),
          '@id' => $rate_sheet_id,
        ]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('zcs_api_attributes')->error('Error sending status change email: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
