<?php

declare(strict_types=1);

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\node\Entity\Node;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;



/**
 * Provides a zcs API Attribute status.
 */
final class UpdateApiAttributeForm extends FormBase {



  protected $request;

  public function __construct(RequestStack $request_stack) {
    $this->request = $request_stack->getCurrentRequest();
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }



  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_api_attribute_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $api_attribute_status_id = $this->request->get('id');
    $node = Node::load($api_attribute_status_id);
    $form['api_category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Category'),
      '#default_value' => \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($node->field_api_attributes_status->target_id)->name->value,
      '#attributes' => ['disabled' => 'disabled'],
    ];
    $form['api_attribute'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Attribute'),
      '#default_value' => $node->getTitle() ?? '',
      '#attributes' => ['disabled' => 'disabled'],
    ];
    $form['integrated_with_carrier_network'] = [
      '#type' => 'select',
      '#title' => $this->t('Integrated with Carrier Network'),
      '#options' => [
       'yes' => 'Yes',
       'no' => 'No',
      ],
      '#default_value' => $node->field_successfully_integrated_cn->value,
      '#attributes' => ['disabled' => 'disabled'],
    ];
    $form['able_to_be_used'] = [
      '#type' => 'select',
      '#title' => $this->t('Able to be used'),
      '#options' => [
       'yes' => 'Yes',
       'no' => 'No',
      ],
      '#default_value' => $node->field_able_to_be_used->value,
      '#attributes' => ['disabled' => 'disabled'],
    ];
    $form['current_standard_pricing'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Current Standard Pricing (USD)'),
      '#default_value' => '$ ' . $node->field_current_standard_pricing->value,
      '#attributes' => ['disabled' => 'disabled'],
    ];

    $form['carrier_enabled_3rd_party_use'] = [
      '#type' => 'select',
      '#title' => $this->t('Carrier Enabled for 3rd Party Use'),
      '#options' => [
       'yes' => 'Yes',
       'no' => 'No',
      ],
      '#default_value' => $node->field_carrier_enabled_3rd_party->value,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Update API Attribute Status'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $api_attribute_id = $this->request->get('id');
    $third_party_use = $form_state->getValue('carrier_enabled_3rd_party_use');
    $node = Node::load($api_attribute_id);
    $node->set('field_carrier_enabled_3rd_party', $third_party_use);
    $node->save();
    $this->messenger()->addMessage('API Attribute updated Successfully'); 
    $form_state->setRedirectUrl(Url::fromRoute('zcs_api_attributes.attribute.page'));
  }


   /**
   *
   */
  public function access(AccountInterface $account) {
    if (\Drupal::currentUser()->hasRole('carrier_admin') || \Drupal::currentUser()->hasRole('administrator')) {
      return AccessResult::allowed();
    }
    else {
      $memberships = \Drupal::service('group.membership_loader')->loadByUser(\Drupal::currentUser());
      if (isset($memberships)) {
        $roles = $memberships[0]->getRoles();
        $group_roles = [];
        foreach($roles as $role) {
          $group_roles[] = $role->id();
        }
        if (in_array('partner-admin', $group_roles)) {
          return AccessResult::allowed();
        }
        else{
          return AccessResult::forbidden();
        }
      }
    }
    return AccessResult::forbidden();
  }
}
