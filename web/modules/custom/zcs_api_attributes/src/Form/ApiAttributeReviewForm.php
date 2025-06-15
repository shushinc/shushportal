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
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;


class ApiAttributeReviewForm extends FormBase {

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
    return 'api_attribute_review';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id=0) {

    $data = $this->database->select('api_attributes_page_data', 'apd')
      ->fields('apd', ['approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status', 'attribute_status', 'page_data'])
      ->condition('id', $id)
      ->execute()->fetchObject();
 

    if (!empty($data)) {
      foreach (Json::decode($data->page_data) as $key => $value) {
        $nids[] = $key;
        $form['network_connected' . $key] = [
          '#type' => 'checkbox',
          '#default_value' => $value['network_connected'],
          '#disabled' => TRUE,
        ];
        $form['able_to_be_used' . $key] = [
          '#type' => 'checkbox',
          '#default_value' => $value['able_to_be_used'],
          '#disabled' => TRUE,
        ];
      }
    }
 
    $form['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(",", $nids),
    ];

    $form['apid'] = [
      '#type' => 'hidden',
      '#value' => $id,
    ];

    $by = '';
    $approvers = ['approver1', 'approver2'];
    if (in_array('api_attribute_approval_level_1', $this->currentUser()->getRoles())) {
      $by = 'approver1';
    }
    if (in_array('api_attribute_approval_level_2', $this->currentUser()->getRoles())) {
      $by = 'approver2';
    }
    $form['approved_by'] = [
      '#type' => 'hidden',
      '#value' => $by
    ];
    $otherApprover = array_diff($approvers, [$by]);

    $form['another_approver_status'] = [
      '#type' => 'hidden',
      '#value' => $data->{end($otherApprover).'_status'}
    ];

    $form['#theme'] = 'api_attribute_status_review';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-review';

    if (((in_array('api_attribute_approval_level_1', $this->currentUser()->getRoles()) && !$data->approver1_uid) || (in_array('api_attribute_approval_level_2', $this->currentUser()->getRoles()) && !$data->approver2_uid && $data->approver1_uid)) && $data->attribute_status == 1 && $data->{$by . '_status'} == 1) {
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
    $updatedFields[$values['approved_by'] . '_uid'] = (int) $this->currentUser()->id();
    if ($values['another_approver_status'] == 2 && $values['status'] == 2) {
      $updatedFields['attribute_status'] = 2;
      foreach (explode(",", $values['nodes']) as $id) {
        $node = Node::load($id);
        if ($node instanceof NodeInterface) {
          if($values['network_connected' . $id] == '1') {
           $network_connected = 'yes';
          }
          else {
            $network_connected = 'no';
          }
          if($values['able_to_be_used' . $id] == '1') {
            $able_to_be_used = 'yes';
           }
           else {
             $able_to_be_used = 'no';
           }
          $node->set('field_successfully_integrated_cn', $network_connected);
          $node->set('field_able_to_be_used', $able_to_be_used);
          $node->save();
        }
      }
    } 
    elseif ($values['another_approver_status'] == 3 || $values['status'] == 3) {
      $updatedFields['attribute_status'] = 3;
    }
    $this->database->update('api_attributes_page_data')
      ->fields($updatedFields)
      ->condition('id', $values['apid'])
      ->execute();
    $this->messenger()->addStatus('Status submitted successfully');

    // Sending the email for approver2
    if ($values['approved_by'] == 'approver1' && $values['status'] == 2) {
      $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'api_attribute_approval_level_2', 'status' => 1]);
      foreach ($users as $user) {
        if ($user) {
          $userMails[] = $user->mail->value;
        }
      }

      $mailManager = \Drupal::service('plugin.manager.mail');
      $modulePath = \Drupal::service('extension.path.resolver')->getPath('module', 'zcs_api_attributes');
      $path = $modulePath . '/templates/api_attributes_status_approval_mail.html.twig';
      $rendered = \Drupal::service('twig')->load($path)->render([
        'user' => $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id())->mail->value,
        'approval' => Link::createFromRoute('Approval', 'zcs_api_attributes.rate_sheet')->toString(),
        'site_name' => $this->config('system.site')->get('name')
      ]);
      $params['message'] = Markup::create(nl2br($rendered));
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = true;

      foreach ($userMails as $mail) {
        $emails[] = $mailManager->mail('zcs_api_attributes', 'api_attribute_sheet', $mail, $langcode, $params, NULL, $send);
      }

      if (reset($emails)['result'] != true && end($emails)['result'] != true) {
        $this->messenger()->addError(t('There was a problem sending your email notification.'));
      } else {
        $this->messenger()->addStatus(t('An email notification has been sent.'));
      }
    }

    $form_state->setRedirect('zcs_api_attributes.api_attribute_sheet.approval');
  }
}