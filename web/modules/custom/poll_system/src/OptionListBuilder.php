<?php

declare(strict_types=1);

namespace Drupal\poll_system;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

class OptionListBuilder extends EntityListBuilder
{

  public function buildHeader(): array
  {
    $header['id'] = $this->t('ID');
    $header['title'] = $this->t('Title');
    $header['question'] = $this->t('Question');
    $header['weight'] = $this->t('Weight');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity): array
  {
    /** @var \Drupal\poll_system\Entity\Option $entity */
    $row['id'] = $entity->id();
    $row['title'] = $entity->label();
    $row['question'] = $entity->get('question')->entity?->label();
    $row['weight'] = $entity->get('weight')->value;
    return $row + parent::buildRow($entity);
  }
}
