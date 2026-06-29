<?php

namespace Drupal\zcs_api_attributes\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\zcs_api_attributes\Service\ApiAccessTokenService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for regenerating an API access token.
 */
class ApiAccessTokenRegenerateConfirmForm extends ConfirmFormBase {

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
   * Constructs an ApiAccessTokenRegenerateConfirmForm object.
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
    return 'api_access_token_regenerate_confirm_form';
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

    $token_result = $form_state->getTemporaryValue('token_result');

    if ($token_result) {
      $form['warning'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
      ];

      $form['warning']['message'] = [
        '#markup' => '<h2>' . $this->t('API token regenerated successfully.') . '</h2>' .
                     '<p><strong>' . $this->t('Copy this token now. It will never be displayed again.') . '</strong></p>',
      ];

      $form['token'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Your API Token'),
        '#value' => $token_result['full_token'],
        '#rows' => 3,
        '#attributes' => [
          'readonly' => 'readonly',
          'style' => 'font-family: monospace; font-size: 14px;',
        ],
      ];

      $form['actions'] = [
        '#type' => 'actions',
      ];

      $form['actions']['done'] = [
        '#type' => 'link',
        '#title' => $this->t('Done'),
        '#url' => $this->getCancelUrl(),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];

      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to regenerate the token "@name"?', [
      '@name' => $this->token->name,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Regenerating this token will immediately invalidate the previous token. Any applications using the old token will need to be updated with the new token.');
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
    $result = $this->tokenService->regenerateToken($this->tokenId);

    if ($result) {
      $form_state->setTemporaryValue('token_result', $result);
      $form_state->setRebuild(TRUE);
    }
    else {
      $this->messenger()->addError($this->t('Failed to regenerate token.'));
      $form_state->setRedirect('zcs_api_attributes.api_access_token.list');
    }
  }

}
