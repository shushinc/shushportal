<?php

namespace Drupal\sam;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\sam\Annotation\SsoProvider;
use Drupal\sam\Plugin\SsoProvider\SsoProviderInterface;

/**
 * Plugin manager for SSO providers.
 */
class SsoProviderManager extends DefaultPluginManager {

  /**
   * Constructs a new SsoProviderManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/SsoProvider',
      $namespaces,
      $module_handler,
      SsoProviderInterface::class,
      SsoProvider::class
    );

    $this->alterInfo('sam_provider_info');
    $this->setCacheBackend($cache_backend, 'sam_providers');
  }

  /**
   * Gets all enabled SSO providers.
   *
   * @return array
   *   Array of enabled provider instances.
   */
  public function getEnabledProviders() {
    $config = \Drupal::config('sam.settings');
    $enabled = $config->get('enabled_providers') ?? [];

    $providers = [];
    foreach ($enabled as $provider_id) {
      if ($this->hasDefinition($provider_id)) {
        $providers[$provider_id] = $this->createInstance($provider_id);
      }
    }

    return $providers;
  }

  /**
   * Gets a specific provider by ID.
   *
   * @param string $provider_id
   *   The provider ID.
   *
   * @return \Drupal\sam\Plugin\SsoProvider\SsoProviderInterface|null
   *   The provider instance or NULL if not found.
   */
  public function getProvider($provider_id) {
    if ($this->hasDefinition($provider_id)) {
      return $this->createInstance($provider_id);
    }
    return NULL;
  }

  /**
   * Gets provider options for form select elements.
   *
   * @return array
   *   Array of provider options keyed by provider ID.
   */
  public function getProviderOptions() {
    $options = [];
    foreach ($this->getDefinitions() as $provider_id => $definition) {
      $options[$provider_id] = $definition['name'];
    }
    return $options;
  }

}
