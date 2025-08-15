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


class PriceRateSheet extends FormBase {

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
    return 'rate_sheet';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'financial_rate_sheet_approval_level_1']);
    foreach ($users as $user) {
      if ($user) {
        $userMails[] = $user->mail->value;
      }
    }
    $defaultCurrency = 'en_US';
    if (!empty($this->getRequest()->get('cur'))) {
      $defaultCurrency = $this->getRequest()->get('cur');
    }

    $defaultCurrency = \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US';
    // show the right currency symbol based on the chosen one.
    $number = new NumberFormatter($defaultCurrency, NumberFormatter::CURRENCY);
    $symbol = $number->getSymbol(NumberFormatter::CURRENCY_SYMBOL);


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
     //  '#default_value' => $defaultCurrency,
       '#default_value' => \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US',
       '#disabled' => TRUE, // disables the field
       '#weight' => 0,
     ];
 
     $form['attribute_date'] = [
       '#type' => 'date',
       '#default_value' => date('Y-m-d'),
       '#weight' => 1,
     ];

    $nids =[];
    $contents = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        $nids[] = $content->id();
        $form['price' . $content->id()] = [
          '#type' => 'number',
          '#min' => 0,
          '#default_value' => $content->field_standard_price->value ?? 0.000,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
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


    $form['#theme'] = 'rate_sheet';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet';
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Proposed API Pricing',
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
      $price = $values['price' . $nid];
      if($values['price' . $nid]== 0) {
       $price =  number_format(isset($values['price' . $nid]) ? $values['price' . $nid] : 0.000, 3);
      }
      else {
        if (!preg_match('/^\d+\.\d{3}$/', $values['price' . $nid])) {
          $price = number_format((float)$values['price' . $nid], 3, '.', '');
         }
      }
      $json[$nid] =  $price;
    }
    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'financial_rate_sheet_approval_level_1', 'status' => 1]);
    foreach ($users as $user) {
      if ($user) {
        $userMails[] = $user->mail->value;
      }
    }
    $this->database->insert('attributes_page_data')
      ->fields(['submit_by', 'currency_locale', 'effective_date', 'effective_date_integer', 'page_data', 'approver1_uid', 'approver1_status', 'approver2_uid', 'approver2_status', 'attribute_status', 'created', 'updated'])
      ->values([$this->currentUser()->id(), $values['currencies'], $values['attribute_date'], strtotime($values['attribute_date']), Json::encode($json), 0, 1, 0, 1, 1, \Drupal::time()->getRequestTime(), \Drupal::time()->getRequestTime()])
      ->execute();
    $mailManager = \Drupal::service('plugin.manager.mail');

    $modulePath = \Drupal::service('extension.path.resolver')->getPath('module', 'zcs_api_attributes');
    $path = $modulePath . '/templates/attributes_approval_mail.html.twig';

    $rendered = \Drupal::service('twig')->load($path)->render([
      'user' => $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id())->mail->value,
      'effective_date' => $values['attribute_date'],
      'approval' => Link::createFromRoute('Approval', 'zcs_api_attributes.rate_sheet')->toString(),
      'site_name' => $this->config('system.site')->get('name')
    ]);
  

    $params['message'] = Markup::create(nl2br($rendered));
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = true;

    foreach ($userMails as $mail) {
      $emails[] = $mailManager->mail('zcs_api_attributes', 'rate_sheet', $mail, $langcode, $params, NULL, $send);
    }

    if (reset($emails)['result'] != true && end($emails)['result'] != true) {
      $this->messenger()->addError(t('There was a problem sending your email notification.'));
    } else {
      $this->messenger()->addStatus(t('An email notification has been sent.'));
    }
    $form_state->setRedirect('zcs_api_attributes.pricing_history');
  }
}