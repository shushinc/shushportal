<?php

declare(strict_types=1);

namespace Drupal\sam_ldap\Plugin\SsoProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\sam\Plugin\SsoProvider\SsoProviderInterface;
use Drupal\sam\SsoAppInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * LDAP authentication provider.
 *
 * @SsoProvider(
 *   id = "ldap",
 *   label = @Translation("LDAP"),
 *   name = @Translation("LDAP"),
 *   description = @Translation("Authenticate users through an LDAP directory."),
 *   weight = 0
 * )
 */
final class LdapProvider extends PluginBase implements SsoProviderInterface {

  use StringTranslationTrait;


  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return (string) $this->t('LDAP');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->t('Authenticate users through an LDAP directory.');
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationUrl(array $options = []): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request, SsoAppInterface $app = NULL) {
    throw new \LogicException('LDAP authentication is not implemented yet.');
  }

  /**
   * {@inheritdoc}
   */
  public function handleCallback(Request $request, SsoAppInterface $app) {
    throw new \LogicException('LDAP does not use an OIDC callback.');
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigured(): bool {
    return TRUE;
  }

  /**
   * Indicates whether this provider supports connection testing.
   *
   * @return bool
   *   TRUE when connection testing is supported.
   */
  public function supportsConnectionTest(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationForm(array $form, FormStateInterface $form_state, SsoAppInterface $app = NULL): array {
    $settings = $app ? $app->getSettings() : [];

    return [
      'connection' => [
        '#type' => 'details',
        '#title' => $this->t('Connection'),
        '#open' => TRUE,
        'host' => [
          '#type' => 'textfield',
          '#title' => $this->t('Host'),
          '#description' => $this->t('LDAP server hostname or IP address, for example ldap.example.org or openldap.'),
          '#default_value' => $settings['details']['connection']['host'] ?? '',
          '#required' => TRUE,
        ],
        'port' => [
          '#type' => 'number',
          '#title' => $this->t('Port'),
          '#default_value' => $settings['details']['connection']['port'] ?? 389,
          '#required' => TRUE,
          '#min' => 1,
          '#max' => 65535,
        ],
        'encryption' => [
          '#type' => 'select',
          '#title' => $this->t('Encryption'),
          '#options' => [
            'none' => $this->t('No encryption'),
            'starttls' => $this->t('STARTTLS'),
            'ldaps' => $this->t('LDAPS'),
          ],
          '#default_value' => $settings['details']['connection']['encryption'] ?? 'none',
          '#required' => TRUE,
        ],
      ],
      'directory' => [
        '#type' => 'details',
        '#title' => $this->t('Directory'),
        '#open' => TRUE,
        'base_dn' => [
          '#type' => 'textfield',
          '#title' => $this->t('Base DN'),
          '#description' => $this->t('The directory base used when searching for users. Example: dc=example,dc=org'),
          '#default_value' => $settings['details']['directory']['base_dn'] ?? '',
          '#required' => TRUE,
        ],
      ],
      'service_account' => [
        '#type' => 'details',
        '#title' => $this->t('Service Account'),
        '#open' => TRUE,
        'bind_dn' => [
          '#type' => 'textfield',
          '#title' => $this->t('Bind DN'),
          '#description' => $this->t('Example: cn=admin,dc=example,dc=org'),
          '#default_value' => $settings['details']['service_account']['bind_dn'] ?? '',
          '#required' => TRUE,
        ],
        'bind_password_key' => [
          '#type' => 'textfield',
          '#title' => $this->t('Drupal Key ID for Bind Password'),
          '#description' => $this->t('The Drupal Key entity ID that stores the LDAP service account password. Example: sam_ldap_bind_password'),
          '#default_value' => $settings['details']['service_account']['bind_password_key'] ?? '',
          '#required' => TRUE,
        ],
      ],
      'user_search' => [
        '#type' => 'details',
        '#title' => $this->t('User Search'),
        '#open' => TRUE,
        'search_filter' => [
          '#type' => 'textfield',
          '#title' => $this->t('Search filter'),
          '#description' => $this->t('LDAP search filter used to locate the user. Use {email} as the email placeholder.'),
          '#default_value' => $settings['search_filter'] ?? '(mail={email})',
          '#required' => TRUE,
        ],
      ],
      'attribute_mapping' => [
        '#type' => 'details',
        '#title' => $this->t('Attribute Mapping'),
        '#open' => TRUE,
        'email_attribute' => [
          '#type' => 'textfield',
          '#title' => $this->t('Email attribute'),
          '#default_value' => $settings['email_attribute'] ?? 'mail',
          '#required' => TRUE,
        ],
        'username_attribute' => [
          '#type' => 'textfield',
          '#title' => $this->t('Username attribute'),
          '#default_value' => $settings['username_attribute'] ?? 'uid',
          '#required' => TRUE,
        ],
        'display_name_attribute' => [
          '#type' => 'textfield',
          '#title' => $this->t('Display name attribute'),
          '#default_value' => $settings['display_name_attribute'] ?? 'cn',
          '#required' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $host = trim((string) $form_state->getValue(['settings', 'details', 'connection', 'host']));
    $port = (int) $form_state->getValue(['settings', 'details', 'connection', 'port']);
    $encryption = strtolower(trim((string) $form_state->getValue(['settings', 'details', 'connection', 'encryption'])));
    $base_dn = trim((string) $form_state->getValue(['settings', 'details', 'directory', 'base_dn']));
    $bind_dn = trim((string) $form_state->getValue(['settings', 'details', 'service_account', 'bind_dn']));
    $bind_password_key = trim((string) $form_state->getValue(['settings', 'details', 'service_account', 'bind_password_key']));
    $search_filter = trim((string) $form_state->getValue(['settings', 'details', 'user_search', 'search_filter']));
    $email_attribute = trim((string) $form_state->getValue(['settings', 'details', 'attribute_mapping', 'email_attribute']));
    $username_attribute = trim((string) $form_state->getValue(['settings', 'details', 'attribute_mapping', 'username_attribute']));
    $display_name_attribute = trim((string) $form_state->getValue(['settings', 'details', 'attribute_mapping', 'display_name_attribute']));

    // Validate host.
    if ($host === '') {
      $form_state->setErrorByName('settings][details][connection][host', $this->t('Host is required.'));
    }

    // Validate port.
    if ($port < 1 || $port > 65535) {
      $form_state->setErrorByName('settings][details][connection][port', $this->t('Port must be between 1 and 65535.'));
    }

    // Validate encryption.
    if (!in_array($encryption, ['none', 'starttls', 'ldaps'], TRUE)) {
      $form_state->setErrorByName('settings][details][connection][encryption', $this->t('Encryption must be one of: none, starttls, or ldaps.'));
    }

    // Validate base DN.
    if ($base_dn === '') {
      $form_state->setErrorByName('settings][details][directory][base_dn', $this->t('Base DN is required.'));
    }

    // Validate bind DN.
    if ($bind_dn === '') {
      $form_state->setErrorByName('settings][details][service_account][bind_dn', $this->t('Bind DN is required.'));
    }

    // Validate bind password key.
    if ($bind_password_key === '') {
      $form_state->setErrorByName('settings][details][service_account][bind_password_key', $this->t('Bind password Key ID is required.'));
    }
    else {
      // Check if the Key exists.
      $key_repository = \Drupal::service('key.repository');
      $key = $key_repository->getKey($bind_password_key);

      if ($key === NULL) {
        $form_state->setErrorByName('settings][details][service_account][bind_password_key', $this->t('Drupal Key "@key" was not found.', ['@key' => $bind_password_key]));
      }
      else {
        $key_value = $key->getKeyValue();
        if (!is_string($key_value) || $key_value === '') {
          $form_state->setErrorByName('settings][details][service_account][bind_password_key', $this->t('Drupal Key "@key" is empty.', ['@key' => $bind_password_key]));
        }
      }
    }

    // Validate search filter.
    if ($search_filter === '') {
      $form_state->setErrorByName('settings][details][user_search][search_filter', $this->t('Search filter is required.'));
    }
    elseif (strpos($search_filter, '{email}') === FALSE) {
      $form_state->setErrorByName('settings][details][user_search][search_filter', $this->t('Search filter must contain {email} placeholder.'));
    }

    // Validate attribute names.
    if ($email_attribute === '') {
      $form_state->setErrorByName('settings][details][attribute_mapping][email_attribute', $this->t('Email attribute is required.'));
    }

    if ($username_attribute === '') {
      $form_state->setErrorByName('settings][details][attribute_mapping][username_attribute', $this->t('Username attribute is required.'));
    }

    if ($display_name_attribute === '') {
      $form_state->setErrorByName('settings][details][attribute_mapping][display_name_attribute', $this->t('Display name attribute is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, SsoAppInterface $ssoApp = NULL): array {

    return [
      'host' => trim((string) $form_state->getValue(['settings', 'details', 'connection', 'host'])),
      'port' => (int) $form_state->getValue(['settings', 'details', 'connection', 'port']),
      'encryption' => strtolower(trim((string) $form_state->getValue(['settings', 'details', 'connection', 'encryption']))),
      'base_dn' => trim((string) $form_state->getValue(['settings', 'details', 'directory', 'base_dn'])),
      'bind_dn' => trim((string) $form_state->getValue(['settings', 'details', 'service_account', 'bind_dn'])),
      'bind_password_key' => trim((string) $form_state->getValue(['settings', 'details', 'service_account', 'bind_password_key'])),
      'search_filter' => trim((string) $form_state->getValue(['settings', 'details', 'user_search', 'search_filter'])),
      'email_attribute' => trim((string) $form_state->getValue(['settings', 'details', 'attribute_mapping', 'email_attribute'])),
      'username_attribute' => trim((string) $form_state->getValue(['settings', 'details', 'attribute_mapping', 'username_attribute'])),
      'display_name_attribute' => trim((string) $form_state->getValue(['settings', 'details', 'attribute_mapping', 'display_name_attribute'])),
    ];
  }

}
