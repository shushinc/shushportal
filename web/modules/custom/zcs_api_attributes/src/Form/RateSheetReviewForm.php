<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use NumberFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

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
  public function buildForm(array $form, FormStateInterface $form_state, $id=0) {

    $data = $this->database->select('attributes_page_data', 'apd')
      ->fields('apd', ['approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status','currency_locale', 'effective_date', 'attribute_status', 'page_data'])
      ->condition('id', $id)
      ->execute()->fetchObject();

    // show the right currency symbol based on the chosen one.
    $number = new NumberFormatter($data->currency_locale ?? 'en_US', NumberFormatter::CURRENCY);
    $symbol = $number->getSymbol(NumberFormatter::CURRENCY_SYMBOL);

    if (!empty($data)) {
      foreach (Json::decode($data->page_data) as $key => $value) {
        $nids[] = $key;
        $form['price' . $key] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => $value ?? 0.00,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
          '#disabled' => TRUE,
        ];
      }
    }
    // to fetch currencies.
    $currencies = [];
    foreach ($this->list as $list) {
      if (!empty($list['locale'])) {
        $currencies[$list['locale']] = $list['currency'] .' ('. $list['alphabeticCode'] .')';
      }
    }
    $form['currencies'] = [
      '#type' => 'select',
      '#options' => $currencies,
      '#default_value' => $data->currency_locale ?? 'en_US',
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
    if ($this->currentUser()->id() === $data->approver1_uid){
      $by = 'approver1';
    }
    if ($this->currentUser()->id() === $data->approver2_uid){
      $by = 'approver2';
    }
    $form['approved_by'] = [
      '#type' => 'hidden',
      '#value' => $by
    ];

    $form['another_approver_status'] = [
      '#type' => 'hidden',
      '#value' => $data->{end(array_diff($approvers, [$by])).'_status'}
    ];

    $form['#theme'] = 'rate_sheet_review';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-review';

    if (in_array($this->currentUser()->id(), [$data->approver1_uid, $data->approver2_uid]) && $data->attribute_status == 1 && $data->{$by . '_status'} == 1) {
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
    $updatedFields['updated'] =  \Drupal::time()->getRequestTime();
    $updatedFields[$values['approved_by'] . '_status'] = (int) $values['status'];
    if ($values['another_approver_status'] == 2 && $values['status'] == 2) {
      $updatedFields['attribute_status'] = 2;
      foreach (explode(",", $values['nodes']) as $id) {
        $node = Node::load($id);
        if ($node instanceof NodeInterface) {
          $node->set('field_standard_price', $values['price' . $id]);
          $node->save();
        }
      }
    } elseif ($values['another_approver_status'] == 3 || $values['status'] == 3) {
      $updatedFields['attribute_status'] = 3;
    }
    $this->database->update('attributes_page_data')
      ->fields($updatedFields)
      ->condition('id', $values['apid'])
      ->execute();
    $this->messenger()->addStatus('Status submitted successfully');
    $form_state->setRedirect('zcs_api_attributes.rate_sheet.approval');
  }
}