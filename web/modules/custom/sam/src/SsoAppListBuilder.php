<?php

namespace Drupal\sam;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

class SsoAppListBuilder extends ConfigEntityListBuilder {

  public function buildHeader(): array {
    $header = parent::buildHeader();

    $operations = $header['operations'] ?? NULL;
    unset($header['operations']);

    $header['domain'] = $this->t('Domain');
    $header['provider'] = $this->t('Provider');
    $header['is_enabled'] = $this->t('Status');

    if ($operations) {
      $header['operations'] = $operations;
    }

    return $header;
  }

  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\sam\Entity\SsoApp $entity */

    $row = parent::buildRow($entity);

    $operations = $row['operations'] ?? NULL;
    unset($row['operations']);

    $row['domain'] = $entity->getDomain();
    $row['provider'] = $entity->getProvider();
    $row['is_enabled'] = $entity->isEnabled()
      ? $this->t('Active')
      : $this->t('Disabled');

    // Re-append operations.
    if ($operations) {
      $row['operations'] = $operations;
    }

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    $operations = parent::getDefaultOperations($entity);
    return [
      ['title' => $this->t('Edit'), 'weight' => 10, 'url' => $entity->toUrl('edit-form')],
      ['title' => $this->t('Delete'), 'weight' => 10, 'url' => $entity->toUrl('delete-form')],
    ];
  }

}
