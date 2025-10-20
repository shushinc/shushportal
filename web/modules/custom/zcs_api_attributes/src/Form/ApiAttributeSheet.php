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

/**
 *
 */
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
    return 'api_attribute_sheet';
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

    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'api_attributes')
      ->sort('field_attribute_weight', 'ASC')
      ->accessCheck()
      ->execute();
    $contents = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);

    if (!empty($contents)) {
      foreach ($contents as $content) {
        if ($content->field_successfully_integrated_cn->value == 'yes') {
          $network_connected = 1;
        }
        else {
          $network_connected = 0;
        }

        if ($content->field_able_to_be_used->value == 'yes') {
          $field_able_to_be_used = 1;
        }
        else {
          $field_able_to_be_used = 0;
        }
        $nids[] = $content->id();
        $form['network_connected' . $content->id()] = [
          '#type' => 'checkbox',
          '#default_value' => $network_connected,
        ];
        $form['current_status' . $content->id()] = [
          '#type' => 'checkbox',
          '#default_value' => (strtolower($content->get('field_able_to_be_used')->value) === 'yes') ? 1 : 0,
          '#disabled' => TRUE,
        ];
        $form['able_to_be_used' . $content->id()] = [
          '#type' => 'checkbox',
          '#default_value' => $field_able_to_be_used,
        ];
      }
    }

    $form['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(",", $nids),
    ];

    $existing = $this->database->select('api_attributes_page_data', 'apd')
      ->fields('apd', ['id', 'submit_by'])
      ->condition('attribute_status', '1')
      ->execute()->fetchObject();
    $hide = FALSE;
    if ($existing) {
      $form['message'] = [
        '#type' => 'markup',
        '#markup' => "There is one edit made by <b>" . $this->entityTypeManager->getStorage('user')->load($existing->submit_by)->mail->value . "</b> that is awaiting approval or rejection, so editing is not possible.",
      ];
      $hide = TRUE;
    }

    $form['#theme'] = 'api_attribute_sheet';
    // $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Update API Attributes',
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
    $nids = explode(",", $values['nodes']);
    foreach ($nids as $nid) {
      $json[$nid]['network_connected'] = $values['network_connected' . $nid];
      $json[$nid]['able_to_be_used'] = $values['able_to_be_used' . $nid];
    }
    $this->database->insert('api_attributes_page_data')
      ->fields([
        'submit_by',
        'page_data',
        'approver1_uid',
        'approver1_status',
        'approver2_uid',
        'approver2_status',
        'attribute_status',
        'created',
        'updated',
      ])
      ->values([$this->currentUser()->id(), Json::encode($json), 0, 1, 0, 1, 1, \Drupal::time()->getRequestTime(), \Drupal::time()->getRequestTime()])
      ->execute();

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'api_attribute_approval_level_1', 'status' => 1]);
    foreach ($users as $user) {
      if ($user) {
        $userMails[] = $user->mail->value;
      }
    }

    $mailManager = \Drupal::service('plugin.manager.mail');

    $modulePath = \Drupal::service('extension.path.resolver')->getPath('module', 'zcs_api_attributes');
    $path = $modulePath . '/templates/api_attributes_status_approval_mail.html.twig';

    // Proper approval links needs to be generated.
    $rendered = \Drupal::service('twig')->load($path)->render([
      'user' => $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id())->mail->value,
      'approval' => Link::createFromRoute('Approval', 'zcs_api_attributes.rate_sheet')->toString(),
      'site_name' => $this->config('system.site')->get('name'),
    ]);

    $params['message'] = Markup::create(nl2br($rendered));
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;

    foreach ($userMails as $mail) {
      $emails[] = $mailManager->mail('zcs_api_attributes', 'api_attribute_sheet', $mail, $langcode, $params, NULL, $send);
    }

    if (reset($emails)['result'] != TRUE && end($emails)['result'] != TRUE) {
      $this->messenger()->addError(t('There was a problem sending your email notification.'));
    }
    else {
      $this->messenger()->addStatus(t('An email notification has been sent.'));
    }
    $form_state->setRedirect('zcs_api_attributes.attribute.page');
  }

}
