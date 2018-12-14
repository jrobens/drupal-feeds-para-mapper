<?php

namespace Drupal\feeds_para_mapper;


use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\Entity\Paragraph;

class RevisionHandler
{
  use StringTranslationTrait;

  /**
   * @var MessengerInterface
   *   The messenger service.
   */
  private $messenger;

  /**
   * @var Importer
   *   The entity importer service.
   */
  private $importer;

  /**
   * @var EntityInterface
   *   The entity that holds the paragraphs
   */
  private $entity;

  /**
   * RevisionHandler constructor.
   *
   * @param MessengerInterface $messenger
   *    The entity importer service.
   * @param Importer $importer
   *    The entity importer service.
   */
  public function __construct(MessengerInterface $messenger, Importer $importer)
  {
    $this->messenger = $messenger;
    $this->importer = $importer;
    $stop = null;
  }

  /**
   * @param EntityInterface $entity
   *   The entity that holds the paragraphs
   */
  public function handle(EntityInterface $entity){
    $this->entity = $entity;
    if(!isset($this->entity->fpm_targets)){
      return;
    }
    foreach ($this->entity->fpm_targets as $fieldInstance) {
      $target_info = $fieldInstance->get('target_info');
      $this->checkUpdates($target_info->paragraphs);
    }
    $this->cleanUp($entity->fpm_targets);
  }


  /**
   * @param Paragraph[] $updates
   */
  protected function checkUpdates(array $updates){
    $toUpdate = array_filter($updates, function (Paragraph $update){
      return !$update->isNew();
    });
    foreach ($toUpdate as $update) {
      $this->createRevision($update);
    }
  }

  /**
   *  Creates a revision.
   *
   * @param Paragraph $paragraph
   */
  protected function createRevision($paragraph){
    $paragraph->setNewRevision(TRUE);
    $paragraph->isDefaultRevision(TRUE);
    try {
      $paragraph->save();
    } catch (EntityStorageException $e) {
    }
    // @see https://www.drupal.org/project/entity_reference_revisions/issues/2984540
    // until this issue is fixed, we need to manually tell the parent entity to use this revision
    $this->updateParentRevision($paragraph);
  }

  /**
   * Updates the parent target's revision id.
   *
   * @param Paragraph $paragraph
   */
  protected function updateParentRevision($paragraph){
    $host_field = $paragraph->parent_field_name->getValue()[0]['value'];
    $revision_id = $paragraph->updateLoadedRevisionId()->getRevisionId();
    $parent = $paragraph->getParentEntity();
    $values = $parent->{$host_field}->getValue();
    foreach ($values as $index => $value) {
      if(isset($value['target_id']) && $value['target_id'] === $paragraph->id()){
        $value['target_revision_id'] = $revision_id;
        $parent->{$host_field}->set($index,$value);
      }
    }
    try {
      $parent->save();
    } catch (EntityStorageException $e) {
      $this->messenger->addError($this->t("Failed to update host entity"));
      $this->messenger->addError($e);
    }
  }


  /**
   *  Cleans up the entity and its paragraphs before saving the update.
   *
   * @param FieldConfigInterface[] $fields
   */
  protected function cleanUp(array $fields){
    // Load all attached entities for the target field:
    $loaded = array();
    foreach ($fields as $field_name => $field) {
      $loaded[$field_name] = $this->importer->loadTarget($this->entity, $field);
    }

    // Check for any unused entities:
    foreach ($loaded as $field_name => $attached) {
      $targetInfo = $fields[$field_name]->get('target_info');
      $used_entities = $targetInfo->paragraphs;
      if(count($attached) > count($used_entities)){
        $this->removeUnused($used_entities,$attached, $fields[$field_name]);
      }
    }
  }

  /**
   * Removes any unused entities.
   *
   * @param Paragraph[] $used_entities
   * @param Paragraph[] $attached
   * @param FieldConfigInterface $field
   */
  protected function removeUnused($used_entities, $attached, $field){
    // Collect the entities that we should remove:
    $toRemove = array();
    for ($i = 0; $i < count($attached); $i++){
      if(!isset($used_entities[$i])){
        $toRemove[$i] = $attached[$i];
      }
    }
    // Check that fields in common are not using any of the entities we intend to remove:
    $targetInfo = $field->get('target_info');
    $in_common_fields = $targetInfo->in_common;
    foreach ($in_common_fields as $in_common_field) {
      foreach ($toRemove as $key => $paragraph){
        $values = $paragraph->get($in_common_field['name'])->getValue();
        if(count($values)){
          unset($toRemove[$key]);
        }
      }
    }
    $parent = $attached[0]->getParentEntity();
    $parent_field = $attached[0]->get('parent_field_name')->getValue()[0]['value'];
    $removed = 0;
    foreach ($toRemove as $paragraph) {
      $parent_values = $parent->get($parent_field)->getValue();
      foreach ($parent_values as $index => $parent_value) {
        if(isset($parent_value['target_id']) && $parent_value['target_id'] === $paragraph->id()){
          $parent->get($parent_field)->removeItem($index);
          $removed++;
        }
      }
    }
    if($removed > 0){
     $this->createRevision($parent);
    }
  }
}