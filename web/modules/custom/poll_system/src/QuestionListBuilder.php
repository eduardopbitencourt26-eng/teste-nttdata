<?php

declare(strict_types=1);

namespace Drupal\poll_system;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for questions.
 */
class QuestionListBuilder extends EntityListBuilder
{

  /**
   * Quantidade de registros por página.
   *
   * @var int
   */
  protected $limit = 5;

  /**
   * Define os cabeçalhos da tabela.
   */
  public function buildHeader(): array
  {
    $header['id'] = $this->t('ID');
    $header['title'] = $this->t('Title');
    $header['show_results'] = $this->t('Show results');
    $header['status'] = $this->t('Enabled');
    return $header + parent::buildHeader();
  }

  /**
   * Define as linhas de cada registro.
   */
  public function buildRow(EntityInterface $entity): array
  {
    /** @var \Drupal\poll_system\Entity\Question $entity */
    $row['id'] = $entity->id();
    $row['title'] = $entity->label();
    $row['show_results'] = $entity->get('show_results')->value ? 'Yes' : 'No';
    $row['status'] = $entity->get('status')->value ? 'Enabled' : 'Disabled';
    return $row + parent::buildRow($entity);
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
