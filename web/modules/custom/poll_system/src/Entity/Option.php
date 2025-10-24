<?php

declare(strict_types=1);

namespace Drupal\poll_system\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * @ContentEntityType(
 *   id = "poll_option",
 *   label = @Translation("Poll Option"),
 *   handlers = {
 *     "list_builder" = "Drupal\poll_system\OptionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\poll_system\Form\OptionForm",
 *       "edit" = "Drupal\poll_system\Form\OptionForm",
 *       "delete" = "Drupal\poll_system\Form\OptionDeleteForm"
 *     }
 *   },
 *   base_table = "poll_option",
 *   admin_permission = "administer poll system",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "title"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/polls/{poll_question}/options/add",
 *     "edit-form" = "/admin/content/polls/options/{poll_option}/edit",
 *     "delete-form" = "/admin/content/polls/options/{poll_option}/delete"
 *   }
 * )
 */
class Option extends ContentEntityBase
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

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setSettings(['max_length' => 255]);

    $fields['description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Description'))
      ->setRequired(FALSE);

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Image'))
      ->setRequired(FALSE)
      ->setSettings([
        'file_extensions' => 'png jpg jpeg webp',
        'alt_field_required' => FALSE,
      ]);

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDefaultValue(0);

    return $fields;
  }
}
