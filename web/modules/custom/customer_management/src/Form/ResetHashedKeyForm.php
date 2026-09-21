<?php

namespace Drupal\customer_management\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * "Are you sure?" confirmation for regenerating a customer's hashed key.
 */
class ResetHashedKeyForm extends ConfirmFormBase {

  /**
   * The customer node being reset.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'customer_management_reset_hashed_key_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $node = NULL) {
    $this->node = $node;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Reset the hashed key for %name?', ['%name' => $this->node->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This immediately invalidates the current hashed key — any phone-number hashes already computed with it will stop matching. A new key will be generated and emailed to @email, along with the usual integration snippet. This cannot be undone.', [
      '@email' => $this->node->get('field_contact_email')->value,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Reset hashed key');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.node.edit_form', ['node' => $this->node->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\customer_management\Service\CredentialGenerator $generator */
    $generator = \Drupal::service('customer_management.credential_generator');
    $new_key = $generator->generateHashedKey();

    $this->node->set('field_hashed_key', $new_key);
    $this->node->save();

    $email = $this->node->get('field_contact_email')->value;
    if (!empty($email)) {
      \Drupal::service('plugin.manager.mail')->mail(
        'customer_management',
        'hashed_key_reset',
        $email,
        \Drupal::currentUser()->getPreferredLangcode(),
        [
          'customer_name' => $this->node->label(),
          'contact_name' => $this->node->get('field_contact_name')->value,
          'client_id' => $this->node->get('field_client_id')->value,
          'hashed_key' => $new_key,
        ]
      );
    }

    customer_management_log_event(
      (int) $this->node->id(),
      'hashed_key_reset',
      $this->t('Hashed key reset by @user.', ['@user' => $this->currentUser()->getAccountName()])
    );

    $this->messenger()->addStatus($this->t('The hashed key has been reset and emailed to the contact.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
