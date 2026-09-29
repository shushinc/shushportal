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
 * Confirmation form for resending customer credentials.
 */
class ResendCredentialsForm extends ConfirmFormBase {

  /**
   * The customer node.
   *
   * @var \Drupal\node\NodeInterface|null
   */
  protected $node;

  /**
   * Constructs the form.
   */
  public function __construct(
    protected CustomerManager $customerManager
  ) {}

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
    return 'customer_management_resend_credentials_form';
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
    return $this->t('Resend credentials for %name?', [
      '%name' => $this->node ? $this->node->label() : '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $email = ($this->node && $this->node->hasField('field_contact_email'))
      ? (string) $this->node->get('field_contact_email')->value
      : '';

    return $this->t('The current Client ID and hashed key will be emailed to @email. The hashed key will not be displayed in the UI.', [
      '@email' => $email,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Resend credentials');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('customer_management.edit_customer', [
      'node' => $this->node ? $this->node->id() : 0,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->node) {
      $this->customerManager->resendCredentials((int) $this->node->id());
      $this->messenger()->addStatus($this->t('The customer credentials have been emailed to the contact.'));
      $form_state->setRedirect('customer_management.edit_customer', [
        'node' => $this->node->id(),
      ]);
      return;
    }

    $form_state->setRedirect('customer_management.list_customer');
  }

}
