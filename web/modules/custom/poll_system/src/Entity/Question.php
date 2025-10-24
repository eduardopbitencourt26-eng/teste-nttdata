<?php

declare(strict_types=1);

namespace Drupal\poll_system\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * @ContentEntityType(
 *   id = "poll_question",
 *   label = @Translation("Poll Question"),
 *   handlers = {
 *     "list_builder" = "Drupal\poll_system\QuestionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\poll_system\Form\QuestionForm",
 *       "edit" = "Drupal\poll_system\Form\QuestionForm",
 *       "delete" = "Drupal\poll_system\Form\QuestionDeleteForm"
 *     }
 *   },
 *   base_table = "poll_question",
 *   admin_permission = "administer poll system",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "title"
 *   },
 *   links = {
 *     "collection" = "/admin/content/polls",
 *     "add-form" = "/admin/content/polls/add",
 *     "edit-form" = "/admin/content/polls/{poll_question}/edit",
 *     "delete-form" = "/admin/content/polls/{poll_question}/delete"
 *   }
 * )
 */
class Question extends ContentEntityBase {
  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')->setLabel(t('ID'))->setReadOnly(TRUE);
    $fields['uuid'] = BaseFieldDefinition::create('uuid')->setLabel(t('UUID'))->setReadOnly(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setSettings(['max_length' => 255]);

    $fields['show_results'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Show results after vote'))
      ->setDefaultValue(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Enabled'))
      ->setDefaultValue(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getCurrentUserId');

    $fields['created'] = BaseFieldDefinition::create('created')->setLabel(t('Created'));
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel(t('Changed'));

    return $fields;
  }

  public static function getCurrentUserId(): array {
    return [\Drupal::currentUser()->id()];
  }
}
