<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a basic custom form.
 */
class ManagePricingHistory extends FormBase {


  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'manage_pricing_history';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $header = [
      'id' => $this->t('ID'),
      'submit_by' => $this->t('Submitted By'),
      'requested_date' => $this->t('Requested Date'),
      'approver1_uid' => $this->t('Approver1 Email'),
      'approver2_uid' => $this->t('Approver2 Email'),
      'approver1_status' => $this->t('Approver1 Status'),
      'approver2_status' => $this->t('Approver1 Status'),
      'attribute_status' => $this->t('Status'),
      'operations' => $this->t('Operations'),
    ];

    $query = $this->database->select('attributes_page_data', 'at')
      ->fields('at', ['id', 'submit_by', 'approver1_uid', 'approver2_uid', 'approver1_status', 'approver2_status', 'attribute_status', 'created', 'pricing_type'])
      ->orderBy('at.created', 'DESC')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      // 10 items per page
      ->limit(10);

    $results = $query->execute();

    $rows = [];
    foreach ($results as $row) {
      $statusSet = $this->database->select('attribute_status', 'as')
        ->fields('as', ['id', 'status'])
        ->execute()
        ->fetchAll();
      foreach ($statusSet as $status) {
        $states[$status->id] = $status->status;
      }

      $rows[] = [
        'id' => $row->id,
        'submit_by' => $this->getUserMail($row->submit_by),
        'requested_time' => date('M d, Y', $row->created),
        'approver1_uid' => !empty($row->approver1_uid) ? $this->getUserMail($row->approver1_uid) : 'NA',
        'approver2_uid' => !empty($row->approver2_uid) ? $this->getUserMail($row->approver2_uid) : 'NA',
        'approver1_status' => $states[$row->approver1_status],
        'approver2_status' => $states[$row->approver1_status],
        'attribute_status' => $states[$row->attribute_status],
        'url' => Link::createFromRoute('Delete', 'zcs_api_attributes.delete_pricing_history', ['id' => $row->id]),
      ];
    }

    $form['manage_api_attributes'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No API Attributes Found'),
    ];

    // Add pager.
    $form['pager'] = ['#type' => 'pager'];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function getUserMail($uid) {
    // Load the user entity.
    $email = '';
    $user = User::load($uid);
    if ($user) {
      $email = $user->getEmail();
    }
    return $email;
  }

}
