<?php

declare(strict_types=1);

namespace Drupal\customer_management\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\customer_management\Service\CustomerHistoryService;
use Drupal\customer_management\Service\CustomerManager;
use Drupal\node\NodeInterface;
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
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL): array {
  
    $customer_nid = $node ? $node->id() : 0;
    $is_edit = $node instanceof NodeInterface && $node->bundle() === 'customer';
    $demand_partner_options = $this->customerManager->getDemandPartnerOptions();

    if ($customer_nid > 0) {
      $tempstoreService = \Drupal::service('tempstore.private')->get('customer_management');
      $new_customer_credentials = $tempstoreService->get('new_customer_credentials_' . $customer_nid);

      if ($new_customer_credentials) {
        $tempstoreService->delete('new_customer_credentials_' . $customer_nid);
        $form['#attached']['drupalSettings']['customerManagement']['newCustomerCredentials'] = [
          'clientId' => $new_customer_credentials['client_id'],
          'hashedKey' => $new_customer_credentials['hashed_key'],
        ];
      }
    }
  
    $selected_partners = $form_state->getValue('demand_partners');
    if (!is_array($selected_partners)) {
      $selected_partners = [];
      if ($is_edit && $node->hasField('field_demand_partners')) {
        foreach ($node->get('field_demand_partners')->getValue() as $item) {
          $selected_partners[] = (string) $item['target_id'];
        }
      }
    }

    $selected_partners = array_values(array_map('strval', array_filter($selected_partners, static function ($value): bool {
      return $value !== '' && $value !== NULL;
    })));

    $demand_partners_data = [];
    foreach ($demand_partner_options as $term_id => $label) {
      $demand_partners_data[] = [
        'id' => (string) $term_id,
        'label' => $label,
      ];
    }

    $selected_demand_partners = [];
    foreach ($selected_partners as $term_id) {
      if (isset($demand_partner_options[$term_id])) {
        $selected_demand_partners[] = [
          'id' => (string) $term_id,
          'label' => $demand_partner_options[$term_id],
        ];
      }
    }

    $form['customer_nid'] = [
      '#type' => 'hidden',
      '#value' => $is_edit ? (int) $node->id() : 0,
    ];

    $form['customer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Customer Name'),
      '#default_value' => $form_state->getValue('customer_name') ?? ($is_edit ? $node->label() : ''),
      '#required' => TRUE,
    ];

    $form['contact_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Name'),
      '#default_value' => $form_state->getValue('contact_name') ?? ($is_edit && $node->hasField('field_contact_name') ? (string) $node->get('field_contact_name')->value : ''),
      '#required' => TRUE,
    ];

    $form['contact_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Contact Email'),
      '#default_value' => $form_state->getValue('contact_email') ?? ($is_edit && $node->hasField('field_contact_email') ? (string) $node->get('field_contact_email')->value : ''),
      '#required' => TRUE,
    ];

    $form['contact_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact Phone'),
      '#default_value' => $form_state->getValue('contact_phone') ?? ($is_edit && $node->hasField('field_contact_phone') ? (string) $node->get('field_contact_phone')->value : ''),
      '#required' => FALSE,
    ];

    $form['demand_partners_data'] = [
      '#type' => 'hidden',
      '#value' => Json::encode($demand_partners_data),
      '#attributes' => [
        'data-customer-demand-partners-data' => '',
      ],
    ];

    $form['selected_demand_partners_data'] = [
      '#type' => 'hidden',
      '#value' => Json::encode($selected_demand_partners),
      '#attributes' => [
        'data-customer-selected-demand-partners-data' => '',
      ],
    ];

    $form['demand_partners'] = [
      '#type' => 'hidden',
      '#value' => implode(',', $selected_partners),
      '#attributes' => [
        'data-customer-demand-partners-input' => '',
      ],
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
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-clients';
    $form['#attached']['library'][] = 'zcs_api_attributes/rate-sheet-ranges';
    $form['#attached']['library'][] = 'customer_management/hashed-key-toggle';
    $form['#attached']['library'][] = 'customer_management/customer-management-form';

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

    /*
    * Demand Partners are stored in a single hidden field as a
    * comma-separated list of taxonomy term IDs.
    *
    * Example:
    *   "12,18,25"
    *
    * Convert the value back to a clean array before sending it to the
    * CustomerManager.
    */
    $demand_partners_value = $form_state->getValue('demand_partners', '');

    if (is_array($demand_partners_value)) {
      // Defensive fallback in case the form is rebuilt with an array value.
      $demand_partner_ids = $demand_partners_value;
    }
    else {
      $demand_partner_ids = explode(',', (string) $demand_partners_value);
    }

    $demand_partner_ids = array_values(array_unique(array_filter(
      array_map(
        static fn ($value): string => trim((string) $value),
        $demand_partner_ids
      ),
      static fn (string $value): bool => $value !== '' && ctype_digit($value)
    )));

    $values = [
      'customer_name' => trim((string) $form_state->getValue('customer_name')),
      'contact_name' => trim((string) $form_state->getValue('contact_name')),
      'contact_email' => trim((string) $form_state->getValue('contact_email')),
      'contact_phone' => trim((string) $form_state->getValue('contact_phone')),
      'demand_partners' => $demand_partner_ids,
    ];

    /*
    * Update existing customer.
    */
  
    // Stores the new customer credentials for display after redirecting to the edit page.
    $new_customer_credentials = NULL;

    if ($customer_nid > 0) {
  
      $customer = $this->customerManager->updateCustomer(
        $customer_nid,
        $values
      );

      $this->messenger()->addStatus(
        $this->t(
          'Customer "@name" has been updated.',
          [
            '@name' => $customer->label(),
          ]
        )
      );

      $form_state->setRedirect(
        'customer_management.edit_customer',
        [
          'node' => $customer->id(),
        ]
      );

      return;
    }

    /*
    * Create new customer.
    */
    $result = $this->customerManager->createCustomer($values);
    $customer = $result['node'];
    $tempstoreService = \Drupal::service('tempstore.private')->get('customer_management');
    $tempstoreService->set('new_customer_credentials_' . $customer->id(), [
      'client_id' => $result['client_id'],
      'hashed_key' => $result['hashed_key'],
    ]);

    $this->messenger()->addStatus(
      $this->t(
        'Customer "@name" has been created.',
        [
          '@name' => $customer->label(),
        ]
      )
    );

    $form_state->setRedirect(
      'customer_management.edit_customer',
      [
        'node' => $customer->id(),
      ]
    );
  }

}
