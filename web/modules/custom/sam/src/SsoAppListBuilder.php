<?php

namespace Drupal\sam;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

class SsoAppListBuilder extends ConfigEntityListBuilder {

  public function buildHeader(): array {
    $header = parent::buildHeader();
    $header['domain'] = $this->t('Domain');
    $header['provider'] = $this->t('Provider');
    $header['is_enabled'] = $this->t('Status');
    return $header;
  }

  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\sam\Entity\SsoApp $entity */
    $row = parent::buildRow($entity);
    $row['domain'] = $entity->getDomain();
    $row['provider'] = $entity->getProvider();
    $row['is_enabled'] = $entity->isEnabled() ? $this->t('Active') : $this->t('Disabled');
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    return [
      ['title' => $this->t('Edit'), 'url' => $entity->toUrl('edit-form')],
      ['title' => $this->t('Delete'), 'url' => $entity->toUrl('delete-form')],
    ];
  }

}
