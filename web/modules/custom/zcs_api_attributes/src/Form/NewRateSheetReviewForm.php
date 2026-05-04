<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\group\Entity\Group;

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

    $form['#theme'] = 'new_rate_sheet_review';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';
    
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
