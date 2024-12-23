<?php

declare(strict_types=1);

namespace Drupal\zcs_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure zcs_custom settings for this site.
 */
final class PortalSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_custom_portal_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['zcs_custom.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {


    $form['kong_details'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Kong Details'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('Add the configuration for kong details.'),
      '#tree' => TRUE,
    ];
    $form['kong_details']['kong_endpoint'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Kong Endpoint'),
      '#default_value' => $this->config('zcs_custom.settings')->get('kong_endpoint'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if ($form_state->getValue('example') === 'wrong') {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('The value is not correct.'),
    //     );
    //   }
    // @endcode
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('zcs_custom.settings')
      ->set('kong_endpoint', $form_state->getValue('kong_details')['kong_endpoint'])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
