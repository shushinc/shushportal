<?php

namespace Drupal\analytics_batch_generator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to create nodes based on date intervals.
 */
class AnalyticsBatchForm extends FormBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a AnalyticsBatchForm object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->messenger = $messenger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'analytics_batch_generator_forms';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['number'] = [
      '#type' => 'number',
      '#title' => $this->t('Number'),
      '#description' => $this->t('Enter a positive integer (0 or greater).'),
      '#required' => TRUE,
      '#min' => 0,
      '#step' => 1,
      '#default_value' => 1,
    ];

    $form['mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Time Period'),
      '#description' => $this->t('Select the time period.'),
      '#options' => [
        'day' => $this->t('Day(s)'),
        'week' => $this->t('Week(s)'),
        'month' => $this->t('Month(s)'),
        'year' => $this->t('Year(s)'),
      ],
      '#required' => TRUE,
      '#default_value' => 'day',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Generate',
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
    $batch_generator = \Drupal::service('analytics_batch_generator.generator');
    $batch_generator->generateNodes($form_state->getValue('number'), $form_state->getValue('mode'));
  }

}
