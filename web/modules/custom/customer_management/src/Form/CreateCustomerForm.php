<?php

declare(strict_types=1);

namespace Drupal\customer_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\customer_management\Service\CustomerHistoryService;
use Drupal\customer_management\Service\CustomerManager;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Customer create/edit form.
 */
final class CreateCustomerForm extends FormBase {

  /**
   * The customer manager.
   *
   * @var \Drupal\customer_management\Service\CustomerManager
   */
  protected $customerManager;

  /**
   * The customer history service.
   *
   * @var \Drupal\customer_management\Service\CustomerHistoryService
   */
  protected $customerHistory;

  /**
   * Constructs the form.
   */
  public function __construct(
    CustomerManager $customer_manager,
    CustomerHistoryService $customer_history
  ) {
    $this->customerManager = $customer_manager;
    $this->customerHistory = $customer_history;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('customer_management.manager'),
      $container->get('customer_management.history')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'customer_management_create_customer_form';
  }

  /**
   * Builds the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL): array {
    $is_edit = $node instanceof NodeInterface && $node->bundle() === 'customer';
    $demand_partner_options = $this->customerManager->getDemandPartnerOptions();

    $form['customer_nid'] = [
      '#type' => 'hidden',
      '#value' => $is_edit ? (int) $node->id() : 0,
    ];

    $form['customer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Customer Name'),
      '#default_value' => $is_edit ? $node->label() : '',
      '#required' => TRUE,
    ];

    $form['contact_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Name'),
      '#default_value' => $is_edit && $node->hasField('field_contact_name') ? (string) $node->get('field_contact_name')->value : '',
      '#required' => TRUE,
    ];

    $form['contact_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Contact Email'),
      '#default_value' => $is_edit && $node->hasField('field_contact_email') ? (string) $node->get('field_contact_email')->value : '',
      '#required' => TRUE,
    ];

    $form['contact_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Phone'),
      '#default_value' => $is_edit && $node->hasField('field_contact_phone') ? (string) $node->get('field_contact_phone')->value : '',
      '#required' => FALSE,
    ];

    $selected_partners = [];
    if ($is_edit && $node->hasField('field_demand_partners')) {
      foreach ($node->get('field_demand_partners')->getValue() as $item) {
        $selected_partners[] = (int) $item['target_id'];
      }
    }

    $form['demand_partners'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Demand Partners'),
      '#options' => $demand_partner_options,
      '#default_value' => $selected_partners,
    ];

    if ($is_edit) {
      $client_id = $node->hasField('field_customer_client_id') ? (string) $node->get('field_customer_client_id')->value : '';
      $masked_value = str_repeat('•', 24);

      $form['client_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Client ID'),
        '#default_value' => $client_id,
        '#attributes' => ['readonly' => 'readonly'],
        '#disabled' => TRUE,
      ];

      $form['hashed_key_display'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Hashed Key'),
        '#default_value' => $masked_value,
        '#attributes' => [
          'readonly' => 'readonly',
          'data-hashed-key-display' => '1',
        ],
      ];

      $form['hashed_key_actions'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['hashed-key-actions'],
          'data-customer-id' => (string) $node->id(),
          'data-reveal-url' => Url::fromRoute('customer_management.reveal_key', ['node' => $node->id()])->toString(),
          'data-reset-url' => Url::fromRoute('customer_management.reset_key', ['node' => $node->id()])->toString(),
        ],
      ];

      if ($this->currentUser()->hasPermission('view hashed key')) {
        $form['hashed_key_actions']['show'] = [
          '#type' => 'markup',
          '#markup' => '<button type="button" class="button button--small hashed-key-toggle" data-hashed-key-toggle="1">' . $this->t('Show') . '</button>',
        ];
      }

      if ($this->currentUser()->hasPermission('reset customer hashed key')) {
        $form['hashed_key_actions']['reset'] = [
          '#type' => 'markup',
          '#markup' => '<a class="button button--small hashed-key-reset" href="' . Url::fromRoute('customer_management.reset_key', ['node' => $node->id()])->toString() . '">' . $this->t('Reset') . '</a>',
        ];
      }

      $form['history'] = [
        '#type' => 'details',
        '#title' => $this->t('History'),
        '#open' => FALSE,
      ];

      $form['history']['table'] = $this->customerHistory->buildHistoryTable((int) $node->id());
    }

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'data-rate-sheet-submit-wrapper' => '1',
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $is_edit ? $this->t('Save Customer') : $this->t('Create Customer'),
    ];

    $form['actions']['cancel'] = Link::fromTextAndUrl($this->t('Back to customers'), Url::fromRoute('customer_management.list_customer'))->toRenderable();
    $form['actions']['cancel']['#attributes']['class'][] = 'button';

    if (!$is_edit) {
      $created_credentials = $form_state->get('created_credentials');
      if (is_array($created_credentials)) {
        $form['created_credentials_modal'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['messages', 'messages--status'],
          ],
        ];
        $form['created_credentials_modal']['markup'] = [
          '#markup' => '<div class="customer-created-credentials"><p><strong>' . $this->t('Customer created successfully.') . '</strong></p><p>' . $this->t('Copy and store this secret now. The hashed key is sensitive.') . '</p><p><strong>' . $this->t('Client ID') . ':</strong> ' . $created_credentials['client_id'] . '</p><p><strong>' . $this->t('Hashed Key') . ':</strong> ' . $created_credentials['hashed_key'] . '</p></div>',
        ];
      }
    }

    $form['#theme'] = 'create_customer';
    $form['#attached']['library'][] = 'customer_management/hashed-key-toggle';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $customer_name = trim((string) $form_state->getValue('customer_name'));
    $contact_name = trim((string) $form_state->getValue('contact_name'));
    $contact_email = trim((string) $form_state->getValue('contact_email'));

    if ($customer_name === '') {
      $form_state->setErrorByName('customer_name', $this->t('Customer name is required.'));
    }

    if ($contact_name === '') {
      $form_state->setErrorByName('contact_name', $this->t('Contact name is required.'));
    }

    if ($contact_email === '' || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
      $form_state->setErrorByName('contact_email', $this->t('Please enter a valid contact email.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $customer_nid = (int) $form_state->getValue('customer_nid');
    $values = [
      'customer_name' => trim((string) $form_state->getValue('customer_name')),
      'contact_name' => trim((string) $form_state->getValue('contact_name')),
      'contact_email' => trim((string) $form_state->getValue('contact_email')),
      'contact_phone' => trim((string) $form_state->getValue('contact_phone')),
      'demand_partners' => array_values(array_filter($form_state->getValue('demand_partners', []))),
    ];

    if ($customer_nid > 0) {
      $customer = $this->customerManager->updateCustomer($customer_nid, $values);
      $this->messenger()->addStatus($this->t('Customer "@name" has been updated.', ['@name' => $customer->label()]));
      $form_state->setRedirect('customer_management.edit_customer', ['node' => $customer->id()]);
      return;
    }

    $result = $this->customerManager->createCustomer($values);
    $customer = $result['node'];

    $this->messenger()->addStatus($this->t('Customer "@name" has been created.', ['@name' => $customer->label()]));
    $form_state->setRedirect('customer_management.edit_customer', ['node' => $customer->id()]);
    $this->messenger()->addStatus($this->t('Client ID: @client_id', ['@client_id' => $result['client_id']]));
    $this->messenger()->addWarning($this->t('Hashed Key (store securely now): @hashed_key', ['@hashed_key' => $result['hashed_key']]));
  }

}
