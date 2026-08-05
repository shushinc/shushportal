<?php

namespace Drupal\sam\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sam\SsoProviderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SsoAppForm extends EntityForm {

  /**
   * The SSO provider manager.
   *
   * @var \Drupal\sam\SsoProviderManager
   */
  protected $providerManager;

  public function __construct(SsoProviderManager $providerManager) {
    $this->providerManager = $providerManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('sam.provider_manager')
    );
  }

  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\sam\Entity\SsoApp $app */
    $app = $this->entity;

    // Get route provider if available.
    $route_provider = $this->getRouteMatch()->getParameter('provider');

    $form['is_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Active'),
      '#default_value' => $app->status() ? 1 : 0,
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App name'),
      '#default_value' => $app->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $app->id(),
      '#machine_name' => [
        'exists' => '\Drupal\sam\Entity\SsoApp::load',
      ],
      '#disabled' => !$app->isNew(),
    ];

    $form['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email domain'),
      '#description' => $this->t('Domain used to route users to this SSO app (e.g. shush.com).'),
      '#default_value' => $app->getDomain(),
      '#required' => TRUE,
    ];

    $form['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('SSO Provider'),
      '#options' => $this->providerManager->getProviderOptions(),
      '#default_value' => $app->getProvider(),
      '#empty_option' => $this->t('- Select a provider -'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::providerAjaxCallback',
        'wrapper' => 'provider-settings-wrapper',
        'event' => 'change',
      ],
    ];

    // Disable provider field if coming from dedicated route.
    if ($route_provider) {
      $form['provider']['#disabled'] = TRUE;
      $form['provider']['#default_value'] = $route_provider;
    }

    $form['settings'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'provider-settings-wrapper'],
      '#tree' => TRUE,
    ];

    // Resolve provider ID in order of precedence.
    $provider_id = $form_state->getValue('provider');

    if (!$provider_id) {
      $input = $form_state->getUserInput();
      $provider_id = $input['provider'] ?? NULL;
    }
    if (!$provider_id) {
      $provider_id = $app->getProvider();
    }
    if (!$provider_id && $route_provider) {
      $provider_id = $route_provider;
    }

    if ($provider_id && $this->providerManager->hasDefinition($provider_id)) {
      $provider = $this->providerManager->getProvider($provider_id);

      $form['settings']['details'] = [
        '#type' => 'details',
        '#title' => $this->t('@provider configuration', [
          '@provider' => $this->providerManager->getProviderOptions()[$provider_id],
        ]),
        '#open' => TRUE,
      ];

      // Add provider configuration fields.
      foreach ($provider->getConfigurationForm($form, $form_state, $app) as $key => $element) {
        $form['settings']['details'][$key] = $element;
      }

      // Add Test Connection button for LDAP.
      if (method_exists($provider, 'supportsConnectionTest') && $provider->supportsConnectionTest()) {
        $form['settings']['test_connection_wrapper'] = [
          '#type' => 'container',
          '#attributes' => ['id' => 'test-connection-wrapper'],
        ];

        $form['settings']['test_connection'] = [
          '#type' => 'button',
          '#value' => $this->t('Test Connection'),
          '#ajax' => [
            'callback' => '::testConnectionCallback',
            'wrapper' => 'test-connection-wrapper',
            'event' => 'click',
          ],
          '#limit_validation_errors' => [],
        ];
      }
    }

    return parent::form($form, $form_state);
  }

  public function providerAjaxCallback(array &$form, FormStateInterface $form_state): array {
    return $form['settings'];
  }

  /**
   * AJAX callback for Test Connection button.
   */
  public function testConnectionCallback(array &$form, FormStateInterface $form_state): array {
    $messenger = \Drupal::messenger();
    
    try {
      // Collect LDAP configuration from form state.
      $configuration = [
        'host' => trim((string) $form_state->getValue(['settings', 'details', 'connection', 'host'])),
        'port' => (int) $form_state->getValue(['settings', 'details', 'connection', 'port']),
        'encryption' => strtolower(trim((string) $form_state->getValue(['settings', 'details', 'connection', 'encryption']))),
        'base_dn' => trim((string) $form_state->getValue(['settings', 'details', 'directory', 'base_dn'])),
        'bind_dn' => trim((string) $form_state->getValue(['settings', 'details', 'service_account', 'bind_dn'])),
        'bind_password_key' => trim((string) $form_state->getValue(['settings', 'details', 'service_account', 'bind_password_key'])),
      ];

      // Check if sam_ldap module is enabled.
      if (!\Drupal::moduleHandler()->moduleExists('sam_ldap')) {
        $messenger->addError($this->t('The sam_ldap module must be enabled to test LDAP connections.'));
      }
      else {
        // Get the LDAP connection service.
        $ldap_connection = \Drupal::service('sam_ldap.connection');
        
        // Test the connection.
        $ldap_connection->testConnection($configuration);
        
        $messenger->addStatus($this->t('Successfully connected to the LDAP server.'));
      }
    }
    catch (\Exception $e) {
      $messenger->addError($this->t('Unable to connect to the LDAP server: @message', [
        '@message' => $e->getMessage(),
      ]));
    }

    return $form['settings']['test_connection_wrapper'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $domain = strtolower(trim($form_state->getValue('domain')));
    $form_state->setValue('domain', $domain);

    // Domain must be unique.
    $storage = $this->entityTypeManager->getStorage('sam_sso_app');
    $apps = $storage->loadByProperties(['domain' => $domain]);

    if ($apps && ($this->entity->isNew() || !isset($apps[$this->entity->id()]))) {
      $form_state->setErrorByName('domain', $this->t('An SSO app for this domain already exists.'));
    }

    $provider_id = $form_state->getValue('provider');
    $provider = $this->providerManager->getProvider($provider_id);

    if ($provider) {
      $provider->validateConfigurationForm($form, $form_state);
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\sam\Entity\SsoApp $app */
    $app = $this->entity;

    // Get route provider if available.
    $route_provider = $this->getRouteMatch()->getParameter('provider');

    $app->set('label', $form_state->getValue('label'));
    $app->set('domain', $form_state->getValue('domain'));
    
    // Use submitted provider or fall back to route provider.
    $provider_id = $form_state->getValue('provider') ?: $route_provider;
    $app->set('provider', $provider_id);
    
    $app->set('is_enabled',$app->get('is_enabled') ? TRUE : FALSE);
    $app->set('status',$app->get('is_enabled') ? TRUE : FALSE);

    $provider = $this->providerManager->getProvider($app->getProvider());
    if ($provider) {
      $app->set('settings', $provider->submitConfigurationForm($form, $form_state, $app));
    }


    $form_state->setRedirect('entity.sam_sso_app.collection');

    parent::submitForm($form, $form_state);
  }

}
