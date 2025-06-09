<?php

namespace Drupal\sam_saml\Plugin\SsoProvider;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Url;
use Drupal\sam\Plugin\SsoProvider\SsoProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * SAML SSO provider plugin.
 *
 * @SsoProvider(
 *   id = "saml",
 *   name = @Translation("SAML"),
 *   description = @Translation("SAML 2.0 Single Sign-On authentication"),
 *   weight = 10
 * )
 */
class SamlProvider extends PluginBase implements SsoProviderInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new SamlProvider object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->t('SAML');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('SAML 2.0 Single Sign-On authentication');
  }

  /**
   * {@inheritdoc}
   */
  public function isConfigured() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationUrl(array $options = []) {
    return Url::fromRoute('sam_saml.login')->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    $response = new RedirectResponse(Url::fromRoute('sam_saml.login')->toString());
    $response->setStatusCode(302);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function handleCallback(Request $request) {
    $response = new RedirectResponse(Url::fromRoute('sam_saml.callback')->toString());
    $response->setStatusCode(302);
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

}
