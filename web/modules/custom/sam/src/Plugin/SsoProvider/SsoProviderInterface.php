<?php

namespace Drupal\sam\Plugin\SsoProvider;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines an interface for SSO provider plugins.
 */
interface SsoProviderInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Gets the human-readable name of the SSO provider.
   *
   * @return string
   *   The provider name.
   */
  public function getName();

  /**
   * Gets the provider description.
   *
   * @return string
   *   The provider description.
   */
  public function getDescription();

  /**
   * Checks if the provider is properly configured.
   *
   * @return bool
   *   TRUE if configured, FALSE otherwise.
   */
  public function isConfigured();

  /**
   * Gets the authentication URL for this provider.
   *
   * @param array $options
   *   Additional options for the authentication URL.
   *
   * @return string
   *   The authentication URL.
   */
  public function getAuthenticationUrl(array $options = []);

  /**
   * Handles the authentication request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response, typically a redirect to the SSO provider.
   */
  public function authenticate(Request $request);

  /**
   * Handles the callback from the SSO provider.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The callback request from the SSO provider.
   *
   * @return array
   *   User data array with keys: email, name, uid (external), roles, etc.
   *
   * @throws \Exception
   *   When authentication fails.
   */
  public function handleCallback(Request $request);

  /**
   * Gets configuration form elements for this provider.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Form elements for provider configuration.
   */
  public function getConfigurationForm(array $form, FormStateInterface $form_state);

  /**
   * Validates the configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state);

  /**
   * Submits the configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state);

}
