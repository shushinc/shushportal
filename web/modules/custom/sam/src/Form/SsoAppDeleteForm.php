<?php

namespace Drupal\sam\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;

class SsoAppDeleteForm extends EntityConfirmFormBase {

  public function getQuestion(): string {
    return $this->t('Delete the SSO app %label?', ['%label' => $this->entity->label()]);
  }

  public function getConfirmText(): string {
    return $this->t('Delete');
  }

  public function getCancelUrl() {
    return $this->entity->toUrl('collection');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->entity->delete();
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
