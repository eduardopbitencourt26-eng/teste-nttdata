<?php

declare(strict_types=1);

namespace Drupal\poll_system\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * @ContentEntityType(
 *   id = "poll_vote",
 *   label = @Translation("Poll Vote"),
 *   handlers = {
 *     "list_builder" = "Drupal\poll_system\VoteListBuilder",
 *     "storage_schema" = "Drupal\poll_system\Entity\Schema\VoteStorageSchema"
 *   },
 *   base_table = "poll_vote",
 *   admin_permission = "administer poll system",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class Vote extends ContentEntityBase
{
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array
  {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')->setLabel(t('ID'))->setReadOnly(TRUE);
    $fields['uuid'] = BaseFieldDefinition::create('uuid')->setLabel(t('UUID'))->setReadOnly(TRUE);

    $fields['question'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Question'))
      ->setSetting('target_type', 'poll_question')
      ->setRequired(TRUE);

    $fields['option'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Option'))
      ->setSetting('target_type', 'poll_option')
      ->setRequired(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')->setLabel(t('Created'));

    return $fields;
  }
}
