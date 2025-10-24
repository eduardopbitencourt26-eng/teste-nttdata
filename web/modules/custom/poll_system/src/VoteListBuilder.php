<?php

declare(strict_types=1);

namespace Drupal\poll_system;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

final class VoteListBuilder extends EntityListBuilder {

  /**
   * Quantidade de registros por página.
   *
   * @var int
   */
  protected $limit = 5;

  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['question'] = $this->t('Question');
    $header['option'] = $this->t('Option');
    $header['user'] = $this->t('User');
    $header['created'] = $this->t('Created');
    return $header;
  }

  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\poll_system\Entity\Vote $entity */
    $q   = $entity->get('question')->entity;
    $opt = $entity->get('option')->entity;
    $user= $entity->get('uid')->entity;

    $question_link = $q
      ? $q->toLink($q->label(), 'edit-form')->toRenderable()
      : ['#markup' => '-'];

    $user_link = $user
      ? $user->toLink()->toRenderable()
      : ['#markup' => '-'];

    $row['id'] = $entity->id();
    $row['question'] = ['data' => $question_link];
    $row['option'] = $opt ? $opt->label() : '-';
    $row['user'] = ['data' => $user_link];
    $row['created'] = \Drupal::service('date.formatter')->format($entity->get('created')->value, 'short');
    return $row;
  }

  /**
   * Aplica o pager() na query base da listagem.
   */
  protected function getEntityIds()
  {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(FALSE)
      ->sort($this->entityType->getKey('id'), 'DESC');

    // 🔹 Ativa o pager se o limite for maior que zero.
    if ($this->limit > 0) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

  /**
   * Adiciona o pager ao render final.
   */
  public function render(): array
  {
    $build = parent::render();

    // 🔹 Renderiza o pager abaixo da tabela.
    if ($this->limit > 0) {
      $build['pager'] = [
        '#type' => 'pager',
      ];
    }

    return $build;
  }
}
