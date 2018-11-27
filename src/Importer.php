<?php

namespace Drupal\feeds_para_mapper;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
use Drupal\feeds\Plugin\Type\Target\TargetBase;
use Drupal\feeds_para_mapper\Utility\TargetInfo;
use Drupal\field\FieldConfigInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\Entity\Paragraph;

class Importer {

  /**
   * @var FeedInterface
   */
  protected $feed;

  /**
   * @var EntityInterface
   */
  protected $entity;

  /**
   * @var FieldConfigInterface
   */
  protected $target;

  /**
   * @var array
   */
  protected $configuration;

  /**
   * @var array
   */
  protected $values;

  /**
   * @var TargetInfo
   */
  protected $targetInfo;

  /**
   * @var string
   */
  protected $language;

  /**
   * The paragraph storage.
   *
   * @var EntityStorageInterface
   */
  protected $paragraph_storage;

  /**
   * The field manager.
   *
   * @var EntityFieldManagerInterface
   */
  protected $field_manager;

  /**
   * @var Mapper
   */
  protected $mapper;
  /**
   * @var FieldTargetBase
   */
  protected $instance;
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager, Mapper $mapper) {
    $this->language           = LanguageInterface::LANGCODE_DEFAULT;
    $this->paragraph_storage  = $entity_type_manager->getStorage('paragraph');
    $this->field_manager      = $field_manager;
    $this->mapper             = $mapper;
  }

  public function import(FeedInterface $feed, EntityInterface $entity, FieldConfigInterface $target, array $configuration, array $values, FieldTargetBase $instance){
    $this->feed           = $feed;
    $this->entity         = $entity;
    $this->target         = $target;
    $this->configuration  = $configuration;
    $this->values         = $values;
    $this->targetInfo     = $target->get('target_info');
    $this->instance       = $instance;
    //@todo: remove explode()
    //$this->explode();
    $paragraphs = $this->initHostParagraphs();
    foreach ($paragraphs as $paragraph) {
      $attached = $this->getTarget($paragraph['paragraph'], $this->target);
      $this->setValue($attached[0], $paragraph['value']);
      if(!$this->entity->isNew()){
        $this->appendToUpdate($attached[0]);
      }
    }
  }

  /**
   * @param Paragraph $paragraph
   * @param $value
   */
  private function setValue($paragraph, $value){
    $target = $this->target->getName();
    // Reset the values of the target:
    $paragraph->{$target} = NULL;
    // We call setTarget on the target plugin instance, and it will call prepareValues,
    // which will eventually set the value for the field.
    $this->instance->setTarget($this->feed, $paragraph, $target, $value);
  }

  /**
   * Mark updated Paragraphs entity for creating new revision.
   *
   * @param Paragraph $paragraph
   *   The Paragraphs entity to mark for revisioning.
   *
   * @see Importer::createRevision()
   */
  private function appendToUpdate($paragraph){
    // Add to the entity some information about the current target:
    $paragraphs = array();
    if(count($this->targetInfo->paragraphs)){
      $paragraphs = $this->targetInfo->paragraphs;
    }
    $paragraphs[] = $paragraph;
    $this->targetInfo->paragraphs = $paragraphs;
    $this->target->set('target_info', $this->targetInfo);
    $fpm_targets = array();
    if(isset($this->entity->fpm_targets)){
      $fpm_targets = $this->entity->fpm_targets;
    }
    $current_target = $this->target->getName();
    $fpm_targets[$current_target] = $this->target;
    $this->entity->fpm_targets = $fpm_targets;
  }

  /**
   * A start point for creating revisions and cleaning up.
   *
   * @param EntityInterface $entity
   */
  public static function finalize($entity){
    if(!isset($entity->fpm_targets)){
      return;
    }
    foreach ($entity->fpm_targets as $fieldInstance) {
      $target_info = $fieldInstance->get('target_info');
      self::checkUpdates($target_info->paragraphs);
    }
    self::cleanUp($entity,$entity->fpm_targets);
  }

  /**
   * @param Paragraph[] $updates
   */
  public static function checkUpdates(array $updates){
    $toUpdate = array_filter($updates, function (Paragraph $update){
      return !$update->isNew();
    });
    foreach ($toUpdate as $update) {
      self::createRevision($update);
    }
  }

  /**
   *  Creates a revision.
   *
   * @param Paragraph $paragraph
   */
  public static function createRevision($paragraph){
    $paragraph->setNewRevision(TRUE);
    $paragraph->isDefaultRevision(TRUE);
    try {
      $paragraph->save();
    } catch (EntityStorageException $e) {
    }
    // @see https://www.drupal.org/project/entity_reference_revisions/issues/2984540
    // until this issue is fixed, we need to manually tell the parent entity to use this revision
    self::updateParentRevision($paragraph);
  }

  /**
   * Updates the parent target's revision id.
   *
   * @param Paragraph $paragraph
   */
  public static function updateParentRevision($paragraph){
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
      drupal_set_message(t("Failed to update host entity"), 'error');
      drupal_set_message($e, 'error');
    }
  }


  /**
   *  Cleans up the entity and its paragraphs before saving the update.
   *
   * @param EntityInterface $entity
   * @param FieldConfigInterface[] $fields
   */
  public static function cleanUp($entity, array $fields){
    try {
      $storage = \Drupal::entityTypeManager()->getStorage('paragraph');
    }
    catch (InvalidPluginDefinitionException $e) {
      drupal_set_message(t('Failed to clean up entities'), 'error');
      drupal_set_message($e,'error');
    }
    catch (PluginNotFoundException $e) {
      drupal_set_message($e,'error');
    }
    if(!isset($storage)){
      return;
    }
    // Load all attached entities for the target field:
    $loaded = array();
    foreach ($fields as $field_name => $field) {
      $loaded[$field_name] = self::loadTarget($entity,$field,$storage);
    }

    // Check for any unused entities:
    foreach ($loaded as $field_name => $attached) {
      $targetInfo = $fields[$field_name]->get('target_info');
      $used_entities = $targetInfo->paragraphs;
      if(count($attached) > count($used_entities)){
        self::removeUnused($used_entities,$attached, $fields[$field_name]);
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
  private static function removeUnused($used_entities, $attached, $field){
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
      self::createRevision($parent);
    }
  }
  private function explode(){
    $values = array();
    $final = [$this->values];
    if(strpos($this->values[0]['value'],'|') !== FALSE){
      $values = explode('|', $this->values[0]['value']);
    }
    if (is_array($values)) {
      $final = array();
      foreach ($values as $value) {
        $list = explode(',', $value);
        foreach ($list as $item) {
          $val = ['value' => $item];
          $final[] = $val;
        }
      }
    }
    $this->values = $final;
  }

  /**
   * Creates empty host Paragraphs entities or gets the existing ones.
   *
   * @return array
   *   The newly created paragraphs items.
   */
  private function initHostParagraphs() {
    $attached = NULL;
    $should_create = FALSE;
    $max = $this->mapper->getMaxValues($this->target, $this->configuration);
    if ($max > -1) {
      $slices = array_chunk($this->values, $max);
    }
    else {
      $slices = array($this->values);
    }
    // If the node entity is new, find the attached (non-saved) Paragraphs:
    if ($this->entity->isNew()) {
      // Get the existing Paragraphs entity:
      $attached = $this->getTarget($this->entity, $this->target);
    }
    else {
      // Load existing paragraph:
      $attached = $this->loadTarget($this->entity,$this->target,$this->paragraph_storage);
    }
    if (count($attached)) {
      // Check if we should create new Paragraphs entities:
      $should_create = $this->shouldCreateNew($attached[0], $slices);
    }
    if (count($attached) && !$should_create) {
      // If we loaded or found attached Paragraphs entities,
      // and don't need to create new entities:
      $items = $this->updateParagraphs($attached, $slices);
    }
    elseif (count($attached) && $should_create) {
      // If we loaded or found attached Paragraphs entities,
      // and we DO NEED to create new entities:
      $items = $this->appendParagraphs($attached, $slices);
    }
    else {
      // We didn't find any attached paragraph.
      // Get the allowed values per each paragraph entity.
      $items = $this->createParagraphs($this->entity, $slices);
    }
    return $items;
  }

  /**
   * Searches through nested Paragraphs entities for the target entities.
   *
   * @param EntityInterface $entity
   *   A node or paragraph object.
   * @param FieldConfigInterface $targetConfig
   *   A node or paragraph object.
   * @param array $result
   *   The previous result.
   *
   * @return array
   *   The found paragraphs.
   */
  private function getTarget($entity, $targetConfig, array $result = array()) {
    $path = $this->mapper->getInfo($targetConfig,'path');
    $last_key = count($path) -1;
    $last_host_field = $path[$last_key]['host_field'];
    $target = $targetConfig->getName();
    if(count($path) > 1){
      $exist = $entity->hasField($last_host_field);
      if ($exist) {
        $values = $entity->get($last_host_field)->getValue();
        foreach ($values as $value) {
          $result[] = $value['entity'];
        }
      }
      elseif($exist = $entity->hasField($target)){
        $result[] = $entity;
      }
      else {
        foreach ($path as $host_info) {
          $field_exist = $entity->hasField($host_info['host_field']);
          if($field_exist){
            $values = $entity->get($host_info['host_field'])->getValue();
            foreach ($values as $value) {
              $result = self::getTarget($value['entity'], $targetConfig,$result);
            }
          }
        }
      }
    }
    return $result;
  }

  /**
   * Loads the existing paragraphs from db.
   *
   * @param EntityInterface $entity
   *   The host entity.
   * @param FieldConfigInterface $targetInstance
   *   The target field instance.
   * @param EntityStorageInterface $storage
   *   The entity storage.
   * @param array $result
   *   The previously loaded paragraphs to append to.
   *
   * @return Paragraph[]
   *   The loaded Paragraphs entities.
   */
  private static function loadTarget($entity, $targetInstance, $storage,  array $result = array()){
    $targetInfo = $targetInstance->get('target_info');
    $path = $targetInfo->path;
    $target = $targetInstance->getName();
    if (count($path) > 1){
      $path[] = array(
        'host_field' => $target,
      );
      if ($exist = $entity->hasField($target)){
        $result[] = $entity;
      }
      else {
        foreach ($path as $host_info) {
          if($exist = $entity->hasField($host_info['host_field'])){
            $values = $entity->get($host_info['host_field'])->getValue();
            foreach ($values as $value) {
              $paragraph = $storage->load($value['target_id']);
              if($paragraph){
                $result = self::loadTarget($paragraph, $targetInstance, $storage, $result);
              }
            }
          }
        }
      }
    }
    return $result;
  }

  /**
   * Creates new Paragraphs entities, and marks others for values changes.
   *
   * @param EntityInterface $entity
   *   The entity that is being edited or created.
   * @param array $slices
   *   The sliced values based on user choice & the field cardinality.
   *
   * @return array
   *   The created Paragraphs entities based on the $slices
   */
  private function createParagraphs($entity, array $slices) {
    $items = array();
    for ($i = 0; $i < count($slices); $i++) {
      if ($i === 0) {
        // Create the first host Paragraphs entity/entities.
        $par = $this->createParents($entity);
      }
      else {
        // Instead of creating another series of host entity/entities,
        // duplicate the last created host entity.
        $attached_targets = $this->getTarget($entity, $this->target);
        $last_key = count($attached_targets) - 1;
        $last = $attached_targets[$last_key];
        $par = $this->duplicateExisting($last);
      }
      if ($par) {
        $items[] = array(
          'paragraph' => $par,
          'value' => $slices[$i],
        );
      }
    }
    return $items;
  }

  /**
   * Removes unwanted Paragraphs entities, and marks others for values changes.
   *
   * @param Paragraph[] $entities
   *   The existing Paragraphs entities.
   * @param array $slices
   *   The sliced values based on user choice & the field cardinality.
   *
   * @return array
   *   The updated entities.
   */
  function updateParagraphs($entities, array $slices) {
    $items = array();
    $slices = $this->checkValuesChanges($slices, $entities);
    for ($i = 0; $i < count($slices); $i++) {
      // we should never delete data, if we should remove the entity then just ignore it,
      // we use cleanUp() to to set the fields values to null.
      if ($slices[$i]['state'] !== "remove") {
        $state = $slices[$i]['state'];
        unset($slices[$i]['state']);
        $items[] = array(
          'paragraph' => $entities[$i],
          'value' => $slices[$i],
          'state' => $state
        );
      }
    }
    return $items;
  }

  /**
   * Creates and updates new paragraphs entities when needed.
   *
   * Creates and marks other paragraphs entities for values changes.
   *
   * @param Paragraph[] $entities
   *   The existing Paragraphs entities that are attached to the $entity.
   * @param array $slices
   *   The sliced values based on user choice & the field cardinality.
   *
   * @return array
   *   The newly created and updated entities.
   */
  function appendParagraphs(array $entities, array $slices) {
    $items = array();
    $slices = $this->checkValuesChanges($slices, $entities);
    for ($i = 0; $i < count($slices); $i++) {
      $state = $slices[$i]['state'];
      unset($slices[$i]['state']);
      $last_item = $entities[count($entities) - 1];
      if ($state === 'new') {
        // Instead of creating another series of host entity/entities,
        // duplicate the last created host entity:
        $paragraph = $this->duplicateExisting($last_item);
      }
      else {
        $paragraph = $entities[$i];
      }
      $items[] = array(
        'paragraph' => $paragraph,
        'value' => $slices[$i],
        'state' => $state,
      );
    }
    return $items;
  }

  /**
   * Creates a paragraph entity and its parents.
   *
   * @param object $entity
   *   The entity that is being edited or created.
   *
   * @return EntityInterface
   *   The created paragraph entity.
   */
  private function createParents($entity) {
    $parents = $this->targetInfo->path;
    $first_host_field = $parents[0]['host_field'];
    $last_host_field = $parents[count($parents) -1]['host_field'];
    $target = $this->target->getName();
    $or_count = count($parents);
    $p = $this->removeExistingParents($parents);
    $parents = $p['parents'];
    $last = $entity;
    if (count($parents) < $or_count) {
      $last = end($p['removed']);
    }
    // For first bundle fields, determine the real host:
//    if (!isset($this->targetInfo->host_field)) {
//      $filtered = array_filter($parents, function ($item) use ($first_host_field) {
//        return $item['host_field'] === $first_host_field;
//      });
//      if (count($filtered)) {
//        $parents = [];
//        $parents[] = $filtered[0];
//      }
//    }
    $parents = array_filter($parents, function ($item) {
      return isset($item['host_entity']);
    });
    foreach ($parents as $parent) {
      $last = $this->createParagraph($parent['host_field'], $parent['bundle'], $last);
      if (!isset($first)) {
        $first = $last;
      }
    }
    return $first;
  }

  /**
   * Duplicates an existing paragraph entity.
   *
   * @param Paragraph $existing
   *   Information about the target field and the target paragraph.
   *
   * @return Paragraph
   *   The duplicated entity, or null on failure.
   */
  private function duplicateExisting($existing) {
    if($existing->isNew()){
      $host_info = $existing->host_info;
    }
    else {
      $host_info = array();
      $parent = $existing->getParentEntity();
      $host_info['bundle'] = $existing->getType();
      $host_info['field'] = $existing->parent_field_name->getValue()[0]['value'];
      $host_info['entity'] = $parent;
    }
    return $this->createParagraph($host_info['field'],$host_info['bundle'],$host_info['entity']);
  }

  /**
   * Remove the created parents of the target field from the parents array.
   *
   * @param array $parents
   *   The parents of the field @see Mapper::buildPath().
   *
   * @return array
   *   The non-existing parents array.
   */
  private function removeExistingParents(array $parents) {
    $findByField = function ($entity, $field) use (&$findByField) {
      $p_c = Paragraph::class;
      $found = NULL;
      if (get_class($entity) === $p_c) {
        if ($exist = $entity->hasField($field) && count($entity->get($field)->getValue())) {
          $found = $entity;
        }
      }
      else if($exist = $entity->hasField($field) && count($entity->get($field)->getValue())){
        $found = $entity;
      }
      else {
        $fields = $entity->getFieldDefinitions();
        $fields = array_filter($fields, function ($field){
          return $field instanceof FieldConfigInterface;
        });
        foreach ($fields as $field_name => $entity_field) {
          if($entity_field->getType() === 'entity_reference_revisions'){
            $values = $entity->get($field_name)->getValue();
            foreach ($values as $value) {
              $found = $findByField($value['entity'], $field);
            }
          }
        }
      }
      return $found;
    };
    $removed = [];
    $to_remove = [];
    for ($i = 0; $i < count($parents); $i++) {
      $par = $findByField($this->entity, $parents[$i]['host_field']);
      if ($par) {
        $removed[] = $par;
        $to_remove[] = $parents[$i]['host_field'];
      }
    }
    $parents = array_filter($parents, function ($item) use ($to_remove) {
      return !in_array($item['host_field'], $to_remove);
    });
    usort($parents, function ($a, $b) {
      return ($a['order'] < $b['order']) ? -1 : 1;
    });
    return [
      'parents' => $parents,
      'removed' => $removed,
    ];
  }

  /**
   * Creates and attaches a Paragraphs entity to another entity.
   *
   * @param string $field
   *   The host field.
   * @param string $bundle
   *   The host bundle.
   * @param ContentEntityInterface $host_entity
   *   The host entity.
   *
   * @return Paragraph
   *   The created Paragraphs entity
   */
  private function createParagraph($field, $bundle, $host_entity) {
    $created = Paragraph::create([
      "type" => $bundle,
    ]);
    $host_entity->{$field}->appendItem($created);
    $host_info = array(
      'type' => $host_entity->getEntityTypeId(),
      'entity' => $host_entity,
      'bundle' => $bundle,
      'field' => $field,
    );
    $created->host_info = $host_info;
    return $created;
  }

  /**
   * Checks whether we should create new Paragraphs.
   *
   * When we find existing attached paragraphs entities while updating,
   * we use this to determine if we can create new paragraph entities.
   *
   * @param Paragraph $currentParagraph
   *   The currently attached Paragraphs entity.
   * @param array $slices
   *   The sliced values based on user choice & the field cardinality.
   *
   * @return bool
   *   TRUE if we should create new Paragraphs entity.
   */
  private function shouldCreateNew(Paragraph $currentParagraph, array $slices) {
    $path = $this->mapper->getInfo($this->target,'path');
    if (count($path) > 1) {
      $host_field = $path[count($path) -1]['host_field'];
      $host = $currentParagraph->getParentEntity();
    }
    else {
      $target = $path[0]['host_field'];
      $host_field = $target;
      $host = $this->entity;
    }
    $current_values = $host->get($host_field)->getValue();
    $host_field_storage = $host->get($host_field)->getFieldDefinition()->getFieldStorageDefinition();
    $allowed = (int) $host_field_storage->getCardinality();
    $skip_check = $allowed === -1;
    // If the parent cannot hold more than 1 value, we should not:
    if ($allowed <= 1 && !$skip_check) {
      return FALSE;
    }
    // If values changed, or we have more than existed, we should:
    if (count($current_values) < count($slices)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Determines whether the values are new, updated, or should be removed.
   *
   * @param array $slices
   *   The sliced values based on user choice & the field cardinality.
   * @param array $entities
   *   The existing Paragraphs entities.
   *
   * @return array
   *   Information about each value state.
   */
  function checkValuesChanges(array $slices, array $entities) {
    $target = $this->target->getName();
    $lang = $this->language;
    $getParagraph = function ($index) use ($entities) {
      if (isset($entities[$index])) {
        return $entities[$index];
      }
      return NULL;
    };
    $getValuesState = function ($paragraph, $chunk) use ($target, $lang) {
      $state = "new";
      if (!isset($paragraph)) {
        return $state;
      }
      if (!isset($paragraph->{$target})) {
        return "shareable";
      }
      $foundValues = array();
      $targetValues = $paragraph->{$target}->getValue();
      foreach ($chunk as $index => $chunkVal) {
        $found_sub_fields = array();
        $changed = NULL;
        if(isset($targetValues[$index])){
          $targetValue =  $targetValues[$index];
          foreach ($chunkVal as $sub_field => $sub_field_value) {
            if(isset($targetValue[$sub_field])){
              $found_sub_fields[] = $sub_field;
              $changed = $targetValue[$sub_field] !== $sub_field_value;
            }
          }
        }
        if(count($found_sub_fields) === count(array_keys($chunkVal))){
          $value = [
            "chunk_value" => $chunkVal,
            'state' => $changed ? "changed": "unchanged",
          ];
          $foundValues[] = $value;
        }
      }
      $changed = FALSE;
      foreach ($foundValues as $foundValue) {
        if($foundValue['state'] === "changed"){
          $changed = TRUE;
          break;
        }
      }
      $state = $changed ? "changed" : "unchanged";
      return $state;
    };
    for ($i = 0; $i < count($slices); $i++) {
      $par = $getParagraph($i);
      $state = $getValuesState($par, $slices[$i]);
      $slices[$i]['state'] = $state;
    }
    // Search for empty paragraphs:
    for ($i = 0; $i < count($entities); $i++) {
      $has_common = FALSE;
      $in_common = $this->mapper->getInfo($this->target,'in_common');
      if (isset($in_common)) {
        $has_common = TRUE;
        $empty_commons = array();
        foreach ($in_common as $fieldInfo) {
          if (!isset($entities[$i]->{$field['name']})) {
            $empty_commons[] = $fieldInfo;
          }
        }
        // If all other fields are empty, we should delete this entity:
        if (count($empty_commons) === count($in_common)) {
          $has_common = FALSE;
        }
      }
      if (!isset($slices[$i]) && !$has_common) {
        $slices[$i] = array(
          'state' => 'remove',
        );
      }
    }
    return $slices;
  }
}