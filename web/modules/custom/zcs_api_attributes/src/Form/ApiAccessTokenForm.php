<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\zcs_api_attributes\Service\ApiAccessTokenService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating API access tokens.
 */
class ApiAccessTokenForm extends FormBase {

  /**
   * The API access token service.
   *
   * @var \Drupal\zcs_api_attributes\Service\ApiAccessTokenService
   */
  protected $tokenService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an ApiAccessTokenForm object.
   *
   * @param \Drupal\zcs_api_attributes\Service\ApiAccessTokenService $token_service
   *   The token service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ApiAccessTokenService $token_service,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->tokenService = $token_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('zcs_api_attributes.api_access_token_service'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_access_token_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $token_result = $form_state->getTemporaryValue('token_result');

    if ($token_result) {
      $form['warning'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
      ];

      $form['warning']['message'] = [
        '#markup' => '<h2>' . $this->t('API token created successfully.') . '</h2>' .
                     '<p><strong>' . $this->t('Copy this token now. It will never be displayed again.') . '</strong></p>',
      ];

      $form['token'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Your API Token'),
        '#value' => $token_result['full_token'],
        '#rows' => 3,
        '#attributes' => [
          'readonly' => 'readonly',
          'style' => 'font-family: monospace; font-size: 14px;',
        ],
      ];

      $form['actions'] = [
        '#type' => 'actions',
      ];

      $form['actions']['done'] = [
        '#type' => 'link',
        '#title' => $this->t('Done'),
        '#url' => Url::fromRoute('zcs_api_attributes.api_access_token.list'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];

      return $form;
    }

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token Name'),
      '#description' => $this->t('A human-readable name for this token (e.g., "Production Integration", "QA Integration").'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['client_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Client'),
      '#description' => $this->t('Select the client this token will be associated with.'),
      '#target_type' => 'group',
      '#selection_settings' => [
        'target_bundles' => ['partner'],
      ],
      '#required' => TRUE,
    ];

    $form['expiration_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Expiration'),
      '#options' => [
        'never' => $this->t('Never expires'),
        'date' => $this->t('Expires on a specific date'),
      ],
      '#default_value' => 'never',
      '#required' => TRUE,
    ];

    $form['expires_at'] = [
      '#type' => 'date',
      '#title' => $this->t('Expiration Date'),
      '#states' => [
        'visible' => [
          ':input[name="expiration_type"]' => ['value' => 'date'],
        ],
        'required' => [
          ':input[name="expiration_type"]' => ['value' => 'date'],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Token'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('zcs_api_attributes.api_access_token.list'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $expiration_type = $form_state->getValue('expiration_type');
    
    if ($expiration_type === 'date') {
      $expires_at = $form_state->getValue('expires_at');
      
      if (empty($expires_at)) {
        $form_state->setErrorByName('expires_at', $this->t('Expiration date is required when "Expires on a specific date" is selected.'));
      }
      else {
        $expiration_timestamp = strtotime($expires_at);
        if ($expiration_timestamp < time()) {
          $form_state->setErrorByName('expires_at', $this->t('Expiration date cannot be in the past.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('name');
    $client_id = $form_state->getValue('client_id');
    $expiration_type = $form_state->getValue('expiration_type');
    
    $expires_at = NULL;
    if ($expiration_type === 'date') {
      $expires_at = strtotime($form_state->getValue('expires_at'));
    }

    $result = $this->tokenService->createToken($client_id, $name, $expires_at);

    $form_state->setTemporaryValue('token_result', $result);
    $form_state->setRebuild(TRUE);
  }

}
