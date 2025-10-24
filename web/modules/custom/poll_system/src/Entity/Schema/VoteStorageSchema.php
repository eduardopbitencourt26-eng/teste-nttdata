<?php

declare(strict_types=1);

namespace Drupal\poll_system\Entity\Schema;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Ajusta o schema SQL da entidade poll_vote.
 */
final class VoteStorageSchema extends SqlContentEntityStorageSchema
{

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE)
  {
    // schema padrão gerado pelo core
    $schema = parent::getEntitySchema($entity_type, $reset);
    $base_table = $this->storage->getBaseTable();

    if (isset($schema[$base_table])) {
      $schema[$base_table]['unique keys']['question_uid'] = ['question', 'uid'];
      $schema[$base_table]['indexes']['question'] = ['question'];
      $schema[$base_table]['indexes']['option']   = ['option'];
      $schema[$base_table]['indexes']['uid']      = ['uid'];
    }

    return $schema;
  }
}
