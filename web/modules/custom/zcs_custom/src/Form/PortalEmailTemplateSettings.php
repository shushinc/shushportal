<?php

declare(strict_types=1);

namespace Drupal\zcs_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure zcs_custom settings for this site.
 */
final class PortalEmailTemplateSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_custom_portal_email_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['zcs_custom.portal_email_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['carrier'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Carrier User Management Email Templates'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('Add the configuration for Carrier User Management.'),
      '#tree' => TRUE,
    ];
    $form['carrier']['email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email Subject'),
      '#required' => TRUE,
      '#placeholder' => 'Enter subject for the email',
      '#default_value' => $this->config('zcs_custom.portal_email_settings')->get('email_subject'),
    ];
    $form['carrier']['email_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Email Body'),
      '#required' => TRUE,
      '#default_value' => $this->config('zcs_custom.portal_email_settings')->get('email_body'),
    ];
    $form['client'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Client Email Templates'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#description' => t('Add the configuration for Client Email Templates.'),
      '#tree' => TRUE,
    ];
    $form['client']['client_email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Onboarding Email Subject'),
      '#required' => TRUE,
      '#placeholder' => 'Enter subject for the email',
      '#default_value' => $this->config('zcs_custom.portal_email_settings')->get('client_email_subject'),
    ];
    $form['client']['client_email_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Client Onboarding Email Body'),
      '#required' => TRUE,
      '#default_value' => $this->config('zcs_custom.portal_email_settings')->get('client_email_body'),
    ];
    $form['client']['client_user_invite_email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client User Invite Email Subject'),
      '#required' => TRUE,
      '#placeholder' => 'Enter subject for the email',
      '#default_value' => $this->config('zcs_custom.portal_email_settings')->get('client_user_invite_email_subject'),
    ];
    $form['client']['client_user_invite_email_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Client User Invite Email Body'),
      '#required' => TRUE,
      '#default_value' => $this->config('zcs_custom.portal_email_settings')->get('client_user_invite_email_body'),
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
   $config = $this->config('zcs_custom.portal_email_settings');

   $config->set('email_subject', $form_state->getValue('carrier')['email_subject']);
   $config->set('email_body', $form_state->getValue('carrier')['email_body']);

   $config->set('client_email_subject', $form_state->getValue('client')['client_email_subject']);
   $config->set('client_email_body', $form_state->getValue('client')['client_email_body']);

   $config->set('client_user_invite_email_subject', $form_state->getValue('client')['client_user_invite_email_subject']);
   $config->set('client_user_invite_email_body', $form_state->getValue('client')['client_user_invite_email_body']);

   $config->save();
   parent::submitForm($form, $form_state);
  }

}
