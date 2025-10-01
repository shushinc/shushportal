<?php

namespace Drupal\zcs_api_attributes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Database\Connection;


/**
 * Class DeleteApiAttributeController.
 */
class ApprovalsDelete extends ControllerBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
    );
  }


  /**
   * {@inheritdoc}
   */
  public function apiAttributedelete($id) {
    // Delete from 'api_attributes_page_data' table.
    $num_deleted = $this->database->delete('api_attributes_page_data')
    ->condition('id', $id) // Replace with the actual ID or condition.
    ->execute();

    \Drupal::messenger()->addMessage('API attribute Request is Successfully Deleted');
    $response = new RedirectResponse(Url::fromRoute('zcs_api_attributes.manage_api_attributes')->toString());
    return $response->send();
  }

  /**
   * {@inheritdoc}
   */
  public function pricingHistorydelete($id) {
    // Delete from 'api_attributes_page_data' table.
    $num_deleted = $this->database->delete('attributes_page_data')
    ->condition('id', $id) // Replace with the actual ID or condition.
    ->execute();

    \Drupal::messenger()->addMessage('Pricing History Request is Successfully Deleted');
    $response = new RedirectResponse(Url::fromRoute('zcs_api_attributes.manage_pricing_history')->toString());
    return $response->send();
  }
  
}
