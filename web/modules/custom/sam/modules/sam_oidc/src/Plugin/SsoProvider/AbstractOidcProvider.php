<?php

namespace Drupal\sam_oidc\Plugin\SsoProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\sam\Plugin\SsoProvider\SsoProviderInterface;
use Drupal\sam_oidc\Service\OidcDiscoveryService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Base class for OIDC-based SSO providers.
 */
abstract class AbstractOidcProvider extends PluginBase implements SsoProviderInterface, ContainerFactoryPluginInterface {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The OIDC discovery service.
   *
   * @var \Drupal\sam_oidc\Service\OidcDiscoveryService
   */
  protected OidcDiscoveryService $discovery;

  /**
   * Constructs the OIDC provider.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    OidcDiscoveryService $discovery
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->discovery = $discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('sam_oidc.discovery')
    );
  }

  /**
   * Returns the issuer URL for the provider.
   */
  abstract protected function getIssuer(): string;

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    $discovery = $this->discovery->discover($this->getIssuer());

    dump($discovery);
    die('OIDC discovery successful.');
  }

  /**
   * {@inheritdoc}
   */
  public function handleCallback(Request $request) {
    // Not implemented yet.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigured(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationForm(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {}

}
