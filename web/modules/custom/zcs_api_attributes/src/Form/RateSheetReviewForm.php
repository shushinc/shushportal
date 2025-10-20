<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class RateSheetReviewForm extends FormBase {

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
    return 'attribute_review';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = 0) {

    $data = $this->database->select('attributes_page_data', 'apd')
      ->fields('apd', ['approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status', 'currency_locale', 'effective_date', 'attribute_status', 'page_data'])
      ->condition('id', $id)
      ->execute()->fetchObject();

    $currency = \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US';
    // Show the right currency symbol based on the chosen one.
    $number = new \NumberFormatter($currency, \NumberFormatter::CURRENCY);
    $symbol = $number->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

    if (!empty($data)) {
      foreach (Json::decode($data->page_data) as $key => $value) {
        $nids[] = $key;
        $form['price' . $key] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => ($value['price'] ?? ($value ?? 0.000)),
          '#step' => 0.001,
          '#field_prefix' => $symbol,
          '#disabled' => TRUE,
        ];
        $form['domestic_price' . $key] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => $value['domestic_price'] ?? 0.000,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
          '#disabled' => TRUE,
        ];
      }
    }
    // To fetch currencies.
    $currencies = [];
    foreach ($this->list as $list) {
      if (!empty($list['locale'])) {
        $currencies[$list['locale']] = $list['currency'] . ' (' . $list['alphabeticCode'] . ')';
      }
    }
    $form['currencies'] = [
      '#type' => 'select',
      '#options' => $currencies,
      '#default_value' => \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US',
      // '#default_value' => $data->currency_locale ?? 'en_US',
      '#disabled' => TRUE,
    ];

    $form['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(",", $nids),
    ];

    $form['apid'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    $form['attribute_date'] = [
      '#type' => 'date',
      '#default_value' => $data->effective_date,
      '#disabled' => TRUE,
    ];

    $by = '';
    $approvers = ['approver1', 'approver2'];
    if ($data->approver1_status == 1) {
      if (in_array('financial_rate_sheet_approval_level_1', $this->currentUser()->getRoles())) {
        $by = 'approver1';
      }
    }
    else {
      if ($data->approver2_status == 1) {
        if (in_array('financial_rate_sheet_approval_level_2', $this->currentUser()->getRoles())) {
          $by = 'approver2';
        }
      }
    }

    $form['approved_by'] = [
      '#type' => 'hidden',
      '#value' => $by,
    ];
    $otherApprover = array_diff($approvers, [$by]);

    $form['another_approver_status'] = [
      '#type' => 'hidden',
      '#value' => $data->{end($otherApprover) . '_status'},
    ];

    $form['#theme'] = 'rate_sheet_review';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-review';

    if (((in_array('financial_rate_sheet_approval_level_1', $this->currentUser()->getRoles()) && !$data->approver1_uid) ||
         (in_array('financial_rate_sheet_approval_level_2', $this->currentUser()->getRoles()) && !$data->approver2_uid && $data->approver1_uid)) &&
         $data->attribute_status == 1 && $data->{$by . '_status'} == 1) {
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
    $updatedFields['updated'] = \Drupal::time()->getRequestTime();
    $updatedFields[$values['approved_by'] . '_status'] = (int) $values['status'];
    $updatedFields[$values['approved_by'] . '_uid'] = (int) $this->currentUser()->id();
    if ($values['another_approver_status'] == 2 && $values['status'] == 2) {
      $updatedFields['attribute_status'] = 2;
      foreach (explode(",", $values['nodes']) as $id) {
        $node = Node::load($id);
        if ($node instanceof NodeInterface) {
          $node->set('field_standard_price', $values['price' . $id]);
          $node->set('field_domestic_standard_price', $values['domestic_price' . $id]);
          $node->save();
        }
      }
    }
    elseif ($values['another_approver_status'] == 3 || $values['status'] == 3) {
      $updatedFields['attribute_status'] = 3;
    }
    $this->database->update('attributes_page_data')
      ->fields($updatedFields)
      ->condition('id', $values['apid'])
      ->execute();
    $this->messenger()->addStatus('Status submitted successfully');

    // Sending the email for approver2.
    if ($values['approved_by'] == 'approver1' && $values['status'] == 2) {
      $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'financial_rate_sheet_approval_level_2', 'status' => 1]);
      foreach ($users as $user) {
        if ($user) {
          $userMails[] = $user->mail->value;
        }
      }

      $mailManager = \Drupal::service('plugin.manager.mail');
      $modulePath = \Drupal::service('extension.path.resolver')->getPath('module', 'zcs_api_attributes');
      $path = $modulePath . '/templates/attributes_approval_mail.html.twig';
      $rendered = \Drupal::service('twig')->load($path)->render([
        'user' => $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id())->mail->value,
        'effective_date' => $values['attribute_date'],
        'approval' => Link::createFromRoute('Approval', 'zcs_api_attributes.rate_sheet')->toString(),
        'site_name' => $this->config('system.site')->get('name'),
      ]);
      $params['message'] = Markup::create(nl2br($rendered));
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = TRUE;

      foreach ($userMails as $mail) {
        $emails[] = $mailManager->mail('zcs_api_attributes', 'rate_sheet', $mail, $langcode, $params, NULL, $send);
      }

      if (reset($emails)['result'] != TRUE && end($emails)['result'] != TRUE) {
        $this->messenger()->addError($this->t('There was a problem sending your email notification.'));
      }
      else {
        $this->messenger()->addStatus($this->t('An email notification has been sent.'));
      }
    }

    $form_state->setRedirect('zcs_api_attributes.rate_sheet.approval');
  }

}
