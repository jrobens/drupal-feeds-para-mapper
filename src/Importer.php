<?php

namespace Drupal\feeds_para_mapper;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Language\LanguageInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
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
    $this->language = LanguageInterface::LANGCODE_DEFAULT;
    $this->field_manager = $field_manager;
    $this->mapper = $mapper;
    try {
      $this->paragraph_storage = $entity_type_manager->getStorage('paragraph');
    } catch (\Exception $e) {
      throw $e;
    }
  }

  public function import(FeedTypeInterface $feedType, FeedInterface $feed, EntityInterface $entity, FieldConfigInterface $target, array $configuration, array $values, FieldTargetBase $instance) {
    $this->feed = $feed;
    $this->entity = $entity;
    $this->target = $target;
    $this->configuration = $configuration;
    $this->values = $values;
    $this->targetInfo = $target->get('target_info');
    $this->instance = $instance;
    // @todo: handle taking a value from $values and adding it to a different bundle field
    $this->resetTypes($feedType->getMappingTargets());
    $paragraphs = $this->initHostParagraphs();
    foreach ($paragraphs as $paragraph) {
      $the_paragraph = $paragraph['paragraph'];
      /*  if ($the_paragraph->get('host_info')) {

        }*/
      if ($the_paragraph->bundle() == 'paragraph_other_information') {
        $debug_me = TRUE;
      }

      $parent_type = $the_paragraph->get('parent_type');
      $parent_field_list = $the_paragraph->get('parent_id');
      $parent = $the_paragraph->getParentEntity();
      $paragraph_type = $the_paragraph->getParagraphType();
      $the_paragraph->getTypedData()->getEntity();

      $attached = $this->getTarget($paragraph['paragraph'], $this->target);
      $this->setValue($attached[0], $paragraph['value']);
      if (!$this->entity->isNew()) {
        $this->appendToUpdate($attached[0]);
      }
    }
  }

  /**
   * Resets the fields types to the original types.
   *
   * In order to avoid the created entity having the wrong fields types (e.g
   * 'entity_reference_revisions'), we need to reset them back to the original
   * types
   *
   * @param FieldTargetDefinition[] $targets
   *  The targets that are being mapped.
   */
  private function resetTypes($targets) {
    foreach ($targets as $target) {
      if ($target instanceof FieldTargetDefinition) {
        $field = $target->getFieldDefinition();
        if ($field instanceof FieldConfigInterface && $info = $field->get('target_info')) {
          $field->set('field_type', $info->type);
        }
      }
    }
  }

  /**
   * @param Paragraph $paragraph
   * @param $value
   */
  private function setValue($paragraph, $value) {
    $target = $this->target->getName();
    // Reset the values of the target:
    $paragraph->{$target} = NULL;


    // We call setTarget on the target plugin instance, and it will call prepareValues,
    // which will eventually set the value for the field.
    // @todo: changing the field type is causing the paragraph entity to have wrong field type,
    // even after changing it later to the original type,
    // maybe before creating the paragraph entity we need to change all the field types we are going to map

    // Value is target_id = string for entity_reference. I can't see the mapping to target_id in mapper. Makes sense as it is
    // a value setter rather than field mapper exercise. How to properly remap to value

    // @jrobens
    // FieldTargetBase does a merge, so recursive call looks OK.
    // web/modules/contrib/feeds/src/Plugin/Type/Target/FieldTargetBase.php
    // web/modules/custom/cbi_feed_alter/src/Feeds/Target/CbiTaxonomyEntityReference.php


    // Strings web/modules/contrib/feeds/src/Plugin/Type/Target/FieldTargetBase.php expects $values[0]['value']
    // Entity Reference expects $values[0]['target_id'] web/modules/contrib/feeds/src/Feeds/Target/EntityReference.php

    if ($target == 'field_bond_stock_exchanges') {
      $debug = TRUE;
    }
    if ($target == 'field_bond_issuer') {
      $debug = TRUE;
    }
    foreach ($value as $check_item) {
      if (is_string($check_item)) {
        \Drupal::logger('feeds_para_mapper')
          ->notice("Item is a string @check_item", ['@check_item' => $check_item]);
      }
    }

    if ($target == 'feeds_item') {
      $debug = true;
    }

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
  private function appendToUpdate($paragraph) {
    // Add to the entity some information about the current target:
    $paragraphs = [];
    if (count($this->targetInfo->paragraphs)) {
      $paragraphs = $this->targetInfo->paragraphs;
    }
    $paragraphs[] = $paragraph;
    $this->targetInfo->paragraphs = $paragraphs;
    $this->target->set('target_info', $this->targetInfo);
    $fpm_targets = [];
    if (isset($this->entity->fpm_targets)) {
      $fpm_targets = $this->entity->fpm_targets;
    }
    $current_target = $this->target->getName();
    $fpm_targets[$current_target] = $this->target;
    $this->entity->fpm_targets = $fpm_targets;
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
    $slices = $this->sliceValues();
    // If the node entity is new, find the attached (non-saved) Paragraphs:
    if ($this->entity->isNew()) {
      // Get the existing Paragraphs entity:
      $attached = $this->getTarget($this->entity, $this->target);
    }
    else {
      // Load existing paragraph:
      $attached = $this->loadTarget($this->entity, $this->target);
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

    // Debug why there is no parent entity on the paragraph
    $parentEntity = $items[0]['paragraph']->getParentEntity();
    if (!$parentEntity) {
      \Drupal::logger('feeds_para_mapper')
        ->notice('Failed to load parent for paragraph @id', ['@id' => $items[0]['paragraph']->bundle()]);
      return $items;
    }

    $parentEntityId = $items[0]['paragraph']->getParentEntity()->id();
    if (!$parentEntityId) {
      \Drupal::logger('feeds_para_mapper')
        ->notice('Failed to load parent for paragraph @id', ['@id' => $parentEntityId]);
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
   * @return Paragraph[]
   *   The found paragraphs.
   */
  private function getTarget($entity, $targetConfig, array $result = []) {
    $path = $this->mapper->getInfo($targetConfig, 'path');
    $last_key = count($path) - 1;
    $last_host_field = $path[$last_key]['host_field'];
    $target = $targetConfig->getName();
    if (count($path) > 1) {
      $exist = $entity->hasField($last_host_field);
      if ($exist) {
        $values = $entity->get($last_host_field)->getValue();
        foreach ($values as $value) {
          $result[] = $value['entity'];
        }
      }
      elseif ($exist = $entity->hasField($target)) {
        $result[] = $entity;
      }
      else {
        foreach ($path as $host_info) {
          $field_exist = $entity->hasField($host_info['host_field']);
          if ($field_exist) {
            $values = $entity->get($host_info['host_field'])->getValue();
            foreach ($values as $value) {
              $result = self::getTarget($value['entity'], $targetConfig, $result);
            }
          }
        }
      }
    }
    else {
      if (!($entity instanceof Paragraph) && $entity->hasField($path[0]['host_field'])) {
        $values = $entity->get($path[0]['host_field'])->getValue();
        foreach ($values as $value) {
          $result[] = $value['entity'];
        }
      }
      else {
        if ($entity instanceof Paragraph && $exists = $entity->hasField($target)) {
          $result[] = $entity;
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
   *   The entity storage.
   * @param array $result
   *   The previously loaded paragraphs to append to.
   *
   * @return Paragraph[]
   *   The loaded Paragraphs entities.
   */
  public function loadTarget($entity, $targetInstance, array $result = []) {
    $targetInfo = $targetInstance->get('target_info');
    $path = $targetInfo->path;
    $target = $targetInstance->getName();
    if (count($path) > 1) {
      $path[] = [
        'host_field' => $target,
      ];
      if ($exist = $entity->hasField($target)) {
        $result[] = $entity;
      }
      else {
        foreach ($path as $host_info) {
          if ($exist = $entity->hasField($host_info['host_field'])) {
            $values = $entity->get($host_info['host_field'])->getValue();
            foreach ($values as $value) {
              $paragraph = $this->paragraph_storage->load($value['target_id']);
              if ($paragraph) {
                $result = $this->loadTarget($paragraph, $targetInstance, $result);
              }
            }
          }
        }
      }
    }
    elseif ($entity->hasField($path[0]['host_field'])) {
      $values = $entity->get($path[0]['host_field'])->getValue();
      foreach ($values as $value) {
        $paragraph = $this->paragraph_storage->load($value['target_id']);
        if ($paragraph) {
          $result[] = $paragraph;
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
    $items = [];
    for ($i = 0; $i < count($slices); $i++) {
      $should_create = $this->shouldCreateNew($entity, $slices, $slices[$i]);
      if (!$should_create) {
        return $items;
      }
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
        $items[] = [
          'paragraph' => $par,
          'value' => $slices[$i],
        ];
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
  private function updateParagraphs($entities, array $slices) {
    $items = [];
    $slices = $this->checkValuesChanges($slices, $entities);
    for ($i = 0; $i < count($slices); $i++) {
      if (!isset($entities[$i])) {
        continue;
      }
      // we should never delete data, if we should remove the entity then just ignore it,
      // we use cleanUp() to to set the fields values to null.
      if ($slices[$i]['state'] !== "remove") {
        $state = $slices[$i]['state'];
        unset($slices[$i]['state']);
        $items[] = [
          'paragraph' => $entities[$i],
          'value' => $slices[$i],
          'state' => $state,
        ];
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
  private function appendParagraphs(array $entities, array $slices) {
    $items = [];
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
      $items[] = [
        'paragraph' => $paragraph,
        'value' => $slices[$i],
        'state' => $state,
      ];
    }
    return $items;
  }

  /**
   * Creates a paragraph entity and its parents entities.
   *
   * @param object $parent
   *   The entity that is being edited or created.
   *
   * @return EntityInterface
   *   The created paragraph entity.
   */
  private function createParents($parent) {
    $parents = $this->targetInfo->path;
    $or_count = count($parents);
    $p = $this->removeExistingParents($parents);
    $parents = $p['parents'];
    $host = $parent;
    if (count($parents) < $or_count) {
      $removed = end($p['removed']);
      if ($parents[0]['host_entity'] === $removed->getEntityTypeId()) {
        $host = end($p['removed']);
      }
    }
    $parents = array_filter($parents, function ($item) {
      return isset($item['host_entity']);
    });
    $first = NULL;
    foreach ($parents as $parent_item) {
      $host = $this->createParagraph($parent_item['host_field'], $parent_item['bundle'], $host);
      if (!isset($first)) {
        $first = $host;
      }
    }
    return $first;
  }

  /**
   * Duplicates an existing paragraph entity.
   *
   * @param Paragraph $existing
   *   The existing Paragraph entity.
   *
   * @return EntityInterface
   *   The duplicated entity, or null on failure.
   */
  private function duplicateExisting($existing) {
    if ($existing->isNew()) {
      $host_info = $existing->host_info;
    }
    else {
      $host_info = [];
      $parent = $existing->getParentEntity();
      $host_info['bundle'] = $existing->getType();
      $host_info['field'] = $existing->get('parent_field_name')
        ->getValue()[0]['value'];
      $host_info['entity'] = $parent;
    }
    return $this->createParagraph($host_info['field'], $host_info['bundle'], $host_info['entity']);
  }

  /**
   * Remove the created parents of the target field from the parents array.
   *
   * @param array $parents
   *   The parents of the field @return array
   *   The non-existing parents array.
   *
   * @see Mapper::buildPath().
   *
   */
  private function removeExistingParents(array $parents) {
    $field_manager = $this->field_manager;

    $findByField = function ($entity, $field) use (&$findByField, $field_manager) {
      $p_c = Paragraph::class;
      $found = NULL;

      if (get_class($entity) === $p_c) {
        if ($exist = $entity->hasField($field) && count($entity->get($field)
            ->getValue())) {
          $found = $entity;
        }
      }
      else {
        if ($exist = $entity->hasField($field) && count($entity->get($field)
            ->getValue())) {
          $found = $entity;
        }
        else {
          $type = $entity->getEntityTypeId();
          $bundle = $entity->bundle();
          $fields = $field_manager->getFieldDefinitions($type, $bundle);
          $fields = array_filter($fields, function ($field) {
            return $field instanceof FieldConfigInterface;
          });
          foreach ($fields as $field_name => $entity_field) {
            if ($entity_field->getType() === 'entity_reference_revisions') {
              $values = $entity->get($field_name)->getValue();
              foreach ($values as $value) {
                // $value = ['target_id' => 511, target_revision_id => 871]
                // Empty paragraph container. @jrobens could be completely wrong, picking values that aren't entities.
                if (array_key_exists('entity', $value)) {
                  $found = $findByField($value['entity'], $field);
                }
              }
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
   * @return EntityInterface
   *   The created Paragraphs entity
   */
  private function createParagraph($field, $bundle, $host_entity) {
    $created = $this->paragraph_storage->create(["type" => $bundle]);
    $host_entity->get($field)->appendItem($created);
    $host_info = [
      'type' => $host_entity->getEntityTypeId(),
      'entity' => $host_entity,
      'bundle' => $bundle,
      'field' => $field,
    ];
    $created->host_info = $host_info;
    return $created;
  }

  /**
   * Checks whether we should create new Paragraphs.
   *
   * When we find existing attached paragraphs entities while updating,
   * we use this to determine if we can create new paragraph entities.
   *
   * @param EntityInterface $entity
   *   The currently attached Paragraphs entity.
   * @param array $slices
   *   The sliced values based on user choice & the field cardinality.
   * @param array $futureValue
   *   The value that the target field will hold.
   *
   * @return bool
   *   TRUE if we should create new Paragraphs entity.
   */
  private function shouldCreateNew($entity, array $slices, array $futureValue = []) {
    $path = $this->targetInfo->path;
    if (count($path) > 1 && $entity instanceof Paragraph) {
      $host_field = $path[count($path) - 1]['host_field'];
      $host = $entity->getParentEntity();
    }
    else {
      $target = $path[0]['host_field'];
      $host_field = $target;
      $host = $this->entity;
    }
    $current_values = $host->get($host_field)->getValue();
    $host_field_storage = $host->get($host_field)
      ->getFieldDefinition()
      ->getFieldStorageDefinition();
    $allowed = (int) $host_field_storage->getCardinality();
    $skip_check = $allowed === -1;
    // If the parent cannot hold anymore values, we should not:
    if ($allowed === count($current_values) && !$skip_check) {
      return FALSE;
    }
    $exceeded = TRUE;
    if ($skip_check) {
      $max = $this->mapper->getMaxValues($this->target, $this->configuration);
      $allowed = $max;
      // Compare the child entity values with max values allowed:
      // Compare the child entity values with max values allowed:
      if (count($futureValue)) {
        if (count($futureValue) < $max) {
          $exceeded = FALSE;
        }
      }
      else {
        $exceeded = FALSE;
      }
    }
    // If the parent or the child entity can hold more values (children),
    // and the child cannot hold values, we should:
    if (count($current_values) < count($slices) && $allowed > count($current_values) && $exceeded) {
      return TRUE;
    }
    if ($skip_check) {
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
  private function checkValuesChanges(array $slices, array $entities) {
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
      $foundValues = [];
      $targetValues = $paragraph->get($target)->getValue();
      foreach ($chunk as $index => $chunkVal) {
        $found_sub_fields = [];
        $changed = NULL;
        if (isset($targetValues[$index])) {
          $targetValue = $targetValues[$index];
          foreach ($chunkVal as $sub_field => $sub_field_value) {
            if (isset($targetValue[$sub_field])) {
              $found_sub_fields[] = $sub_field;
              $changed = $targetValue[$sub_field] !== $sub_field_value;
            }
            else {
              $changed = TRUE;
              break;
            }
          }
        }
        if (count($found_sub_fields) <= count(array_keys($chunkVal))) {
          $value = [
            "chunk_value" => $chunkVal,
            'state' => $changed ? "changed" : "unchanged",
          ];
          $foundValues[] = $value;
        }
      }
      $changed = FALSE;
      foreach ($foundValues as $foundValue) {
        if ($foundValue['state'] === "changed") {
          $changed = TRUE;
          break;
        }
      }
      if (!count($targetValues)) {
        $changed = TRUE;
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
      $in_common = $this->mapper->getInfo($this->target, 'in_common');
      if (isset($in_common)) {
        $has_common = TRUE;
        $empty_commons = [];
        foreach ($in_common as $fieldInfo) {
          if (is_null($entities)) {
            \Drupal::logger('feeds_para_mapper')
              ->warning('checkValuesChanges. Entities is null');
          }
          if (is_null($fieldInfo)) {
            \Drupal::logger('feeds_para_mapper')
              ->warning('checkValuesChanges. Field is null');
          }
          if (!isset($entities[$i]->{$fieldInfo['name']})) {
            $empty_commons[] = $fieldInfo;
          }
        }
        // If all other fields are empty, we should delete this entity:
        if (count($empty_commons) === count($in_common)) {
          $has_common = FALSE;
        }
      }
      if (!isset($slices[$i]) && !$has_common) {
        $slices[$i] = [
          'state' => 'remove',
        ];
      }
    }
    return $slices;
  }

  /**
   * Slices values.
   *
   * @return array
   */
  private function sliceValues() {
    $max = $this->mapper->getMaxValues($this->target, $this->configuration);
    if ($max > -1) {
      // if has sub values:
      if (count($this->values) > 1) {
        $flattened = $this->flattenArray($this->values);
        $slices = array_chunk($flattened, $max);
      }
      else {
        $slices = array_chunk($this->values, $max);
      }
    }
    else {
      $slices = [$this->values];
    }
    return $slices;
  }

  private function flattenArray($arr, $property = NULL) {
    $properties = $this->targetInfo->properties;
    if (!is_array($arr)) {
      $stop = NULL;
      if (isset($property)) {
        return [
          $property => $arr,
        ];
      }
    }
    $items = [];
    foreach ($arr as $item) {
      if (is_array($item)) {
        foreach ($properties as $prop) {
          if (array_key_exists($prop, $item)) {
            $items = array_merge($items, $this->flattenArray($item, $prop));
          }
          elseif (isset($property)) {
            $items = array_merge($items, $this->flattenArray($item, $property));
          }
        }
      }
      else {
        if (isset($property)) {
          $items[] = [
            $property => $item,
          ];
        }
        else {
          $items[] = $item;
        }
      }
    }
    return $items;
  }

}
