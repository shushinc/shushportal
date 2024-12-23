<?php

declare(strict_types=1);

namespace Drupal\zcs_user_management\Form;

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


/**
 * Provides a User Management list form.
 */
final class UserInviteListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'zcs_user_management_user_invite_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $query = \Drupal::database()->select('zcs_user_invitations', 'ui');
    $query->fields('ui', ['email']);
    $query->fields('ui', ['role']);
    $query->fields('ui', ['status']);
    $query->fields('ui', ['user_name']);
    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);
    $invited_members = $pager->execute()->fetchAll();

   $users = [];
    foreach($invited_members as $key => $members) {
      $users[] = [
        'user_first_name' => 'girish',
        'user_last_name' => 'v',
        'user_name' => $members->user_name,
        'user_email' => $members->email,
        'user_role' => $members->role,
        'user_status' => $members->status,
      ];
    }
    $header = [
      'first_name' => $this->t('First Name'),
      'last_name' => $this->t('Last Name'),
      'user_name' => $this->t('User Name'),
      'email' => $this->t('Email'),
      'role' => $this->t('Role'),
      'status' => $this->t('Status'),
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $users,
      '#empty' => $this->t('No data available.'),
    ];
    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // @endcode
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
   
  }
 
}
