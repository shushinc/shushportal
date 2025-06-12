<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use NumberFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;


class ApiAttributeSheet extends FormBase {

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
    return 'create_api_attribute_sheet';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'api_attribute_approval_level_1']);
    foreach ($users as $user) {
      if ($user) {
        $userMails[] = $user->mail->value;
      }
    }

    $contents = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        if($content->field_successfully_integrated_cn->value == 'yes') {
          $network_connected = 1;
        }
        else {
          $network_connected = 0;
        }

        if($content->field_able_to_be_used->value == 'yes') {
          $field_able_to_be_used = 1;
        }
        else {
          $field_able_to_be_used  = 0;
        }
        $nids[] = $content->id();
        $form['network_connected' . $content->id()] = [
          '#type' => 'checkbox',
          '#default_value' => $network_connected,
        ];
        $form['current_standard_price' . $content->id()] = [
          '#type' => 'checkbox',
          '#default_value' => $field_able_to_be_used,
        ];
      }
    }

    $form['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(",", $nids),
    ];


    $existing = $this->database->select('attributes_page_data', 'apd')
      ->fields('apd', ['id', 'submit_by'])
      ->condition('attribute_status', '1')
      ->execute()->fetchObject();
    $hide = false;
    if ($existing) {
      $form['message'] = [
        '#type' => 'markup',
        '#markup' => "There is one edit made by <b>" . $this->entityTypeManager->getStorage('user')->load($existing->submit_by)->mail->value . "</b> that is awaiting approval or rejection, so editing is not possible."
      ];
      $hide = true;
    }


    $form['#theme'] = 'api_attribute_sheet';
    //$form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Create API Attribute Sheet',
      '#disabled' => $hide,
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
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'api_attribute_approval_level_1', 'status' => 1]);
    foreach ($users as $user) {
      if ($user) {
        $userMails[] = $user->mail->value;
      }
    }
    // To do: insert the review data.
    // To do: sent the email
    // integrate the email template.
    // set the message.
  }
}