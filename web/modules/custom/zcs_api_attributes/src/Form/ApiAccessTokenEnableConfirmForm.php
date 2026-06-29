<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\zcs_api_attributes\Service\ApiAccessTokenService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for enabling an API access token.
 */
class ApiAccessTokenEnableConfirmForm extends ConfirmFormBase {

  /**
   * The API access token service.
   *
   * @var \Drupal\zcs_api_attributes\Service\ApiAccessTokenService
   */
  protected $tokenService;

  /**
   * The token ID.
   *
   * @var int
   */
  protected $tokenId;

  /**
   * The token object.
   *
   * @var object
   */
  protected $token;

  /**
   * Constructs an ApiAccessTokenEnableConfirmForm object.
   *
   * @param \Drupal\zcs_api_attributes\Service\ApiAccessTokenService $token_service
   *   The token service.
   */
  public function __construct(ApiAccessTokenService $token_service) {
    $this->tokenService = $token_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('zcs_api_attributes.api_access_token_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_access_token_enable_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $token_id = NULL) {
    $this->tokenId = $token_id;
    $this->token = $this->tokenService->loadToken($token_id);

    if (!$this->token) {
      $this->messenger()->addError($this->t('Token not found.'));
      return $this->redirect('zcs_api_attributes.api_access_token.list');
    }

    if ($this->tokenService->isExpired($this->token)) {
      $this->messenger()->addError($this->t('Token is expired and cannot be enabled.'));
      return $this->redirect('zcs_api_attributes.api_access_token.list');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to enable the token "@name"?', [
      '@name' => $this->token->name,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('zcs_api_attributes.api_access_token.list');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($this->tokenService->enableToken($this->tokenId)) {
      $this->messenger()->addStatus($this->t('Token "@name" has been enabled.', [
        '@name' => $this->token->name,
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Failed to enable token.'));
    }

    $form_state->setRedirect('zcs_api_attributes.api_access_token.list');
  }

}
