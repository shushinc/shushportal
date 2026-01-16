<?php

namespace Drupal\sam_google\Plugin\SsoProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Url;
use Drupal\sam\Plugin\SsoProvider\SsoProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\sam_oidc\Plugin\SsoProvider\AbstractOidcProvider;

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
final class GoogleProvider extends AbstractOidcProvider {

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->t('Google');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->t('Authenticate users using Google OpenID Connect.');
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthenticationUrl(array $options = []): string {
    /**
     * V1 NOTE:
     * This method will later return the dynamically generated
     * authorization URL based on Google OIDC discovery.
     *
     * For now, we just return a placeholder route.
     */
    return Url::fromRoute('sam.authenticate', [
      'provider' => 'google',
    ])->toString();
  }

  /**
   * {@inheritdoc}
   */
  protected function getIssuer(): string {
    return 'https://accounts.google.com';
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
  public function handleCallback(Request $request) {
    return new RedirectResponse('/user/login');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->configFactory->get('sam_google.settings');
  
    return [
      'client_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Client ID'),
        '#default_value' => $config->get('client_id'),
      ],
      'client_secret' => [
        '#type' => 'textfield',
        '#title' => $this->t('Client Secret'),
        '#default_value' => $config->get('client_secret'),
      ],
    ];
  }
  

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $client_id = $form_state->getValue('client_id');
    $client_secret = $form_state->getValue('client_secret');

    if (empty($client_id)) {
      $form_state->setErrorByName(
        'client_id',
        $this->t('Google Client ID is required.')
      );
    }

    if (empty($client_secret)) {
      $form_state->setErrorByName(
        'client_secret',
        $this->t('Google Client Secret is required.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory
    ->getEditable('sam_google.settings')
    ->set('client_id', $form_state->getValue('client_id'))
    ->set('client_secret', $form_state->getValue('client_secret'))
    ->save();
  }

}
