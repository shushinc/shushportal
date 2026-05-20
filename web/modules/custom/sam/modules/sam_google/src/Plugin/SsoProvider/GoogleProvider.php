<?php

namespace Drupal\sam_google\Plugin\SsoProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\key\KeyRepositoryInterface;
use Drupal\sam\Security\SamStateTokenService;
use Drupal\sam\SsoAppInterface;
use Drupal\sam_oidc\Plugin\SsoProvider\AbstractOidcProvider;
use Drupal\sam_oidc\Service\OidcDiscoveryService;
use Drupal\sam_oidc\Service\OidcTokenService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Google OpenID Connect SSO provider.
 *
 * @SsoProvider(
 *   id = "google",
 *   name = @Translation("Google"),
 *   description = @Translation("Google OpenID Connect authentication provider"),
 *   weight = 0
 * )
 */
final class GoogleProvider extends AbstractOidcProvider implements ContainerFactoryPluginInterface {

  /**
   * The Drupal Key repository service.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected KeyRepositoryInterface $keyRepository;

  /**
   * Constructs the Google OIDC provider.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    OidcDiscoveryService $discovery,
    SessionInterface $session,
    SamStateTokenService $state_token,
    OidcTokenService $token_service,
    EntityTypeManagerInterface $entity_type_manager,
    KeyRepositoryInterface $key_repository,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $config_factory,
      $discovery,
      $session,
      $state_token,
      $token_service,
      $entity_type_manager,
    );

    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('sam_oidc.discovery'),
      $container->get('session'),
      $container->get('sam.state_token'),
      $container->get('sam_oidc.token_service'),
      $container->get('entity_type.manager'),
      $container->get('key.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return (string) $this->t('Google');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->t('Authenticate users using Google OpenID Connect.');
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationUrl(array $options = []): string {
    return Url::fromRoute('sam.authenticate')->toString();
  }

  /**
   * {@inheritdoc}
   */
  protected function getIssuer(SsoAppInterface $app): string {
    return (string) $app->getSetting('issuer_url');
  }

  /**
   * {@inheritdoc}
   */
  protected function getClientId(SsoAppInterface $app): string {
    $key_name = trim((string) $app->getSetting('client_id'));

    if ($key_name === '') {
      throw new \RuntimeException(
        'Google Client ID Key name is not configured.'
      );
    }

    $key = $this->keyRepository->getKey($key_name);

    if ($key === NULL) {
      throw new \RuntimeException(sprintf(
        'Drupal Key "%s" was not found.',
        $key_name
      ));
    }

    $value = trim((string) $key->getKeyValue());

    if ($value === '') {
      throw new \RuntimeException(sprintf(
        'Drupal Key "%s" is empty.',
        $key_name
      ));
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function getClientSecret(SsoAppInterface $app): string {
    $key_name = trim((string) $app->getSetting('client_secret'));

    if ($key_name === '') {
      throw new \RuntimeException(
        'Google Client Secret Key name is not configured.'
      );
    }

    $key = $this->keyRepository->getKey($key_name);

    if ($key === NULL) {
      throw new \RuntimeException(sprintf(
        'Drupal Key "%s" was not found.',
        $key_name
      ));
    }

    $value = trim((string) $key->getKeyValue());

    if ($value === '') {
      throw new \RuntimeException(sprintf(
        'Drupal Key "%s" is empty.',
        $key_name
      ));
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCallbackUri(SsoAppInterface $app): string {
    return (string) ($app->getSetting('callback_uri') ?: $app->getSetting('callback_url'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getHostedDomain(SsoAppInterface $app): string|NULL {
    return (string) $app->getDomain();
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigured(): bool {
    // V1: assume provider is configured.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationForm(array $form, FormStateInterface $form_state, SsoAppInterface $app = NULL): array {
    $settings = $app ? $app->getSettings() : [];

    return [
      'issuer_url' => [
        '#type' => 'textfield',
        '#title' => $this->t('Issuer URL'),
        '#default_value' => $settings['issuer_url'] ?? '',
      ],
      'client_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Client ID Drupal Key ID'),
        '#description' => $this->t('The Drupal Key entity ID that stores the Google Client ID.'),
        '#default_value' => $settings['client_id'] ?? '',
      ],
      'client_secret' => [
        '#type' => 'textfield',
        '#title' => $this->t('Client Secret Drupal Key ID'),
        '#description' => $this->t('The Drupal Key entity ID that stores the Google Client Secret.'),
        '#default_value' => $settings['client_secret'] ?? '',
      ],
      'callback_uri' => [
        '#type' => 'textfield',
        '#title' => $this->t('Callback URI'),
        '#default_value' => $settings['callback_uri'] ?? '',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $issuer_url = $form_state->getValue(['settings', 'details', 'issuer_url']);
    $client_id = $form_state->getValue(['settings', 'details', 'client_id']);
    $client_secret = $form_state->getValue(['settings', 'details', 'client_secret']);
    $callback_uri = $form_state->getValue(['settings', 'details', 'callback_uri']);

    if (empty($issuer_url)) {
      $form_state->setErrorByName('settings][details][issuer_url', $this->t('Google APP Issuer URL is required.'));
    }

    if (empty($client_id)) {
      $form_state->setErrorByName('settings][details][client_id', $this->t('Google Client ID Drupal Key ID is required.'));
    }

    if (empty($client_secret)) {
      $form_state->setErrorByName('settings][details][client_secret', $this->t('Google Client Secret Drupal Key ID is required.'));
    }

    if (empty($callback_uri)) {
      $form_state->setErrorByName('settings][details][callback_uri', $this->t('The callback URI is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state, SsoAppInterface $soApp = NULL): array {
    return [
      'issuer_url' => $form_state->getValue(['settings', 'details', 'issuer_url']),
      'client_id' => trim((string) $form_state->getValue(['settings', 'details', 'client_id'])),
      'client_secret' => trim((string) $form_state->getValue(['settings', 'details', 'client_secret'])),
      'callback_uri' => $form_state->getValue(['settings', 'details', 'callback_uri']),
    ];
  }

}
