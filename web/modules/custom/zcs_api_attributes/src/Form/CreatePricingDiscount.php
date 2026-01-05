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
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\AddClassCommand;
use Drupal\Core\Ajax\InvokeCommand;

/**
 *
 */
class CreatePricingDiscount extends FormBase {

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
    return 'create_pricing_discount';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
   // $result = \Drupal::service('zcs_api_attributes.discount_sheet')->DiscountPrice();
    $defaultCurrency = 'en_US';
    if (!empty($this->getRequest()->get('cur'))) {
      $defaultCurrency = $this->getRequest()->get('cur');
    }

    $defaultCurrency = \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US';
    // Show the right currency symbol based on the chosen one.
    $number = new \NumberFormatter($defaultCurrency, \NumberFormatter::CURRENCY);
    $symbol = $number->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

    // To fetch currencies.
    $currencies = [];
    foreach ($this->list as $list) {
      if (!empty($list['locale'])) {
        $currencies[$list['locale']] = $list['currency'] . ' (' . $list['alphabeticCode'] . ')';
      }
    }
    $form['pricing_validation_message'] = [
      '#type' => 'markup',
      '#markup' => '<div id="pricing-validation-message"></div>',
      '#weight' => -100,
    ];


    $form['currencies'] = [
      '#type' => 'select',
      '#options' => $currencies,
      '#default_value' => \Drupal::config('zcs_custom.settings')->get('currency') ?? 'en_US',
      '#disabled' => TRUE,
      '#weight' => 0,
    ];

    $group_type = 'partner';
    $group_storage = \Drupal::entityTypeManager()->getStorage('group');
    $query = $group_storage->getQuery()->condition('type', $group_type); 
    $query = $group_storage->getQuery()->condition('field_partner_type', 'enterprise');
    $group_ids = $query->accessCheck(FALSE)->execute();
    $clients = $group_storage->loadMultiple($group_ids);

    $client_groups = [];
    foreach($clients as $group) {
      $client_groups[$group->get('id')->value] = $group->get('label')->value;
    }
 
    // Show only for carrier admin
    $form['client'] = [
      '#type' => 'select',
      '#options' => $client_groups,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::validateClientPricingAjax',
        'event' => 'change',
      ],
      //'#suffix' => '<div id="pricing-validation-message" class="message"></div>',
    ];
    $form['#theme'] = 'create_pricing_discount';


    $nids = [];
    $contents = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'api_attributes']);
    if (!empty($contents)) {
      foreach ($contents as $content) {
        $nids[] = $content->id();
        $form['discount_price_' . $content->id()] = [
          '#type' => 'number',
          '#min' => 0,
          '#step' => 0.001,
          '#field_prefix' => $symbol,
          '#default_value' => '0.000',
        ];
      }
    }
    $form['nodes'] = [
      '#type' => 'hidden',
      '#value' => implode(",", $nids),
    ];
    $form['#attached']['library'][] = 'zcs_api_attributes/discount-sheet';
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Create Pricing Discount',
    ];

    return $form;
  }


  public function validateClientPricingAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $values = $form_state->getValues();
    $client_id = $values['client'];
    $existing = $this->database->select('discount_pricing_page_data', 'apd')
      ->fields('apd', ['id', 'submit_by'])
      ->condition('client_id', $client_id)
      ->condition('attribute_status', '1')
      ->execute()->fetchObject();
          
     if ($existing) {
       $email = $this->entityTypeManager->getStorage('user')->load($existing->submit_by)->mail->value;
       $message =  "There is one edit made by <b>" . $email . "</b> that is awaiting approval or rejection, so form submission is not possible.";
        // Disable the submit button
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'prop',
          ['disabled', true]
        ));
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'addClass',
          ['is-disabled']
        ));    
        $response->addCommand(new InvokeCommand(
          '#pricing-validation-message',
          'addClass',
          ['message']
        ));
      }
      else {
        // Disable the submit button
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'prop',
          ['disabled', false]
        ));
        $response->addCommand(new InvokeCommand(
          '[data-drupal-selector="edit-submit"]',
          'removeClass',
          ['is-disabled']
        ));
  
        $response->addCommand(new InvokeCommand(
          '#pricing-validation-message',
          'removeClass',
          ['message']
        ));
      }  


      // Update the message container dynamically
    $response->addCommand(new HtmlCommand('#pricing-validation-message', $message));
  
    return $response;
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
    $json = [];
    foreach ($nids as $nid) {
      $json[$nid]['discount_pricing'] = $values['discount_price_' . $nid] ?? 0.000;
    }
    $this->database->insert('discount_pricing_page_data')
      ->fields([
        'submit_by',
        'currency_locale',
        'page_data',
        'client_id',
        'client_name',
        'approver1_uid',
        'approver1_status',
        'approver2_uid',
        'approver2_status',
        'attribute_status',
        'created',
        'updated',
      ])
      ->values([
        $this->currentUser()->id(),
        $values['currencies'],
        Json::encode($json),
        $values['client'],
        $this->getClientName($values['client']),
        0,
        1,
        0,
        1,
        1, 
        \Drupal::time()->getRequestTime(),
        \Drupal::time()->getRequestTime()])
      ->execute();

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['roles' => 'financial_rate_sheet_approval_level_1', 'status' => 1]);
    foreach ($users as $user) {
      if ($user) {
        $userMails[] = $user->mail->value;
      }
    }

    $mailManager = \Drupal::service('plugin.manager.mail');
    $modulePath = \Drupal::service('extension.path.resolver')->getPath('module', 'zcs_api_attributes');
    $path = $modulePath . '/templates/discount_pricing_approval_mail.html.twig';

    // // Proper approval links needs to be generated.
    // link needs to be updated.
    $rendered = \Drupal::service('twig')->load($path)->render([
      'user' => $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id())->mail->value,
      'approval' => Link::createFromRoute('Approval', 'zcs_api_attributes.pricing_discount_review_list')->toString(),
      'site_name' => $this->config('system.site')->get('name'),
    ]);

    $params['message'] = Markup::create(nl2br($rendered));
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;

    foreach ($userMails as $mail) {
      $emails[] = $mailManager->mail('zcs_api_attributes', 'discount_price_sheet', $mail, $langcode, $params, NULL, $send);
    }

    if (reset($emails)['result'] != TRUE && end($emails)['result'] != TRUE) {
      $this->messenger()->addError(t('There was a problem sending your email notification.'));
    }
    else {
      $this->messenger()->addStatus(t('An email notification has been sent.'));
    }
    $form_state->setRedirect('zcs_api_attributes.pricing_discount');
  }


  public function getClientName($client_id) {
    if (!$client_id) {
      return '';
    }
    $group = Group::load($client_id);
    return $group ? $group->label() : '';
  }

}
