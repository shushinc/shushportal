<?php

namespace Drupal\customer_management\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\customer_management\Service\CustomerManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for regenerating a customer's hashed key.
 */
class ResetHashedKeyForm extends ConfirmFormBase {

  /**
   * The customer node being reset.
   *
   * @var \Drupal\node\NodeInterface|null
   */
  protected $node;

  /**
   * The customer manager.
   *
   * @var \Drupal\customer_management\Service\CustomerManager
   */
  protected $customerManager;

  /**
   * Constructs the form.
   */
  public function __construct(CustomerManager $customer_manager) {
    $this->customerManager = $customer_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('customer_management.manager')
    );
  }

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
    if ($node instanceof NodeInterface && $node->bundle() === 'customer') {
      $this->node = $node;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Reset the hashed key for %name?', ['%name' => $this->node ? $this->node->label() : '']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $email = ($this->node && $this->node->hasField('field_contact_email')) ? $this->node->get('field_contact_email')->value : '';
    return $this->t('This will invalidate the current hashed key immediately. A new key will be generated and emailed to @email. Integrations using the previous key must be updated. This cannot be undone.', [
      '@email' => $email,
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
    return Url::fromRoute('customer_management.edit_customer', ['node' => $this->node ? $this->node->id() : 0]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->node) {
      $this->customerManager->resetHashedKey((int) $this->node->id());
      $this->messenger()->addStatus($this->t('The hashed key has been reset and emailed to the contact.'));
      $form_state->setRedirect('customer_management.edit_customer', ['node' => $this->node->id()]);
      return;
    }

    $form_state->setRedirect('customer_management.list_customer');
  }

}
