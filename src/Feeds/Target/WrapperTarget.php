<?php

namespace Drupal\feeds_para_mapper\Feeds\Target;


use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\feeds\Annotation\FeedsTarget;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\Plugin\Type\FeedsPluginManager;
use Drupal\feeds\Plugin\Type\Target\ConfigurableTargetInterface;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
use Drupal\feeds_para_mapper\Mapper;
use Drupal\field\FieldConfigInterface;

/**
 * Defines a wrapper target around a paragraph bundle's target field.
 *
 * @FeedsTarget(
 *   id = "wrapper_target",
 *   field_types = {
 *     "entity_reference_revisions",
 *   },
 *   arguments = {
 *     "@messenger",
 *     "@plugin.manager.feeds.target",
 *     "@feeds_para_mapper.mapper",
 *   }
 * )
 */
class WrapperTarget extends FieldTargetBase implements ConfigurableTargetInterface
{
  /**
   * @var MessengerInterface
   */
  protected $messenger;
  /**
   * @var FeedsPluginManager
   */
  protected $plugin_manager;

  /**
   * @var Mapper
   */
  protected $mapper;

  /**
   * @var FieldConfigInterface
   */
  protected $field;

  /**
   * @var FieldTargetBase
   */
  protected $targetInstance;

  public function __construct(array $configuration,
                              $plugin_id,
                              array $plugin_definition,
                              MessengerInterface $messenger,
                              FeedsPluginManager $plugin_manager,
                              Mapper $mapper)
  {
    $this->messenger = $messenger;
    $this->plugin_manager = $plugin_manager;
    $this->mapper = $mapper;
    $this->configuration = $configuration;
    $this->targetDefinition = $configuration['target_definition'];
    $this->field = $this->targetDefinition->getFieldDefinition();
    $this->targetInstance = $this->createTargetInstance();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }


  /**
   * {@inheritdoc}
   */
  public static function targets(array &$targets, FeedTypeInterface $feed_type, array $definition)
  {
    $processor = $feed_type->getProcessor();
    $entity_type = $processor->entityType();
    $bundle = $processor->bundle();
    /**
     * @var Mapper $mapper
     */
    $mapper = \Drupal::service('feeds_para_mapper.mapper');
    $sub_fields = $mapper->getTargets($entity_type,$bundle);
    foreach ($sub_fields as $field) {
      $field->set('field_type', 'entity_reference_revisions');

      $wrapper_target = self::prepareTarget($field);
      if (!isset($wrapper_target)) {
        continue;
      }
      $properties = $wrapper_target->getProperties();
      $mapper->updateInfo($field, 'properties', $properties);
      $wrapper_target->setPluginId("wrapper_target");
      $path = $mapper->getInfo($field,'path');
      $last_host = end($path);
      $wrapper_target->setPluginId("wrapper_target");
      $entity_type = $field->get('entity_type');
      $id = $last_host['host_field'] . ".entity:" . $entity_type . "." . $field->getName();
      $exist = isset($targets[$id]);
      $num = 0;
      while($exist){
        $num++;
        $id .=  "_" . $num;
        $exist = isset($targets[$id]);
      };

      // Every $field->getType() is entity_reference_revisions
      // $field->getFieldStorageDefinition()->getType() is better
     /* if ($field->getFieldStorageDefinition()->getType() != 'bigint'
        && $field->getFieldStorageDefinition()->getType() != 'boolean'
        && $field->getFieldStorageDefinition()->getType() != 'string'
        && $field->getFieldStorageDefinition()->getType() != 'datetime'
        && $field->getFieldStorageDefinition()->getType() != 'string') {
        \Drupal::logger('feeds_para_mapper')
          ->notice('Created feed target @target', ['@target' => $id]);
      }*/
      $targets[$id] = $wrapper_target;
    }
  }
  public function createTargetInstance(){
    $mapper = $this->getMapper();
    $plugin = $mapper->getInfo($this->field,'plugin');
    $class = $plugin['class'];
    if ($class == 'EntityReference' && $this->field->get('field_name' == 'field_bond_exchanges')) {
      $class = 'Drupal\cbi_feed_alter\Feeds\Target';
    }
    $target = $class::prepareTarget($this->field);
    if (!isset($target)) {
      return null;
    }
    $target->setPluginId($plugin['id']);
    $instance = null;
    try {
      $instance = $this->plugin_manager->createInstance($plugin['id'], $this->configuration);
    } catch (PluginException $e) {
    }

    // Change the class for Taxonomy entity reference.
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function setTarget(FeedInterface $feed, EntityInterface $entity, $field_name, array $values)
  {
    $empty = $this->valuesAreEmpty($values);
    if ($empty) {
      return;
    }
    $target = $this->targetDefinition;
    $target = $target->getFieldDefinition();
    $type = $this->mapper->getInfo($target,'type');
    $target->set('field_type', $type);
    try{
      $importer = \Drupal::service('feeds_para_mapper.importer');
      $importer->import($this->feedType, $feed, $entity, $target, $this->configuration, $values, $this->targetInstance);
    } catch (\Exception $exception){
      \Drupal::logger('feeds_para_mapper')->warning('@e', ['@e' => $exception->getMessage()]);
      $this->messenger->addError($exception);
    }
  }

  /**
   * Checks whether the values are empty.
   *
   * @param array $values
   *   The values
   *
   * @return bool
   *   True if the values are empty.
   */
  public function valuesAreEmpty(array $values){
    $properties = $this->targetDefinition->getProperties();
    $emptyValues = 0;
    foreach ($values as $value) {
      $currentProperties = array_keys($value);
      $emptyProps = [];
      foreach ($properties as $property) {
        foreach ($currentProperties as $currentProperty) {
          if ($currentProperty === $property) {
              if (is_array($value[$currentProperty])) {
                  $emptySubValues = 0;
                  foreach ($value[$currentProperty] as $subValue) {
                      if (!strlen($subValue)) {
                          $emptySubValues++;
                      }
                  }
                  if ($emptySubValues === count($value[$currentProperty])) {
                      $emptyProps[] = $currentProperty;
                  }
              }
              else {
                  if (!strlen($value[$currentProperty])) {
                      $emptyProps[] = $currentProperty;
                  }
              }
          }
        }
      }
      if (count($emptyProps) === count($properties)) {
        $emptyValues++;
      }
    }
    return $emptyValues === count($values);
  }

  /**
   *
   * Rules for picking a plugin https://www.drupal.org/project/feeds/issues/2933361
   *
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    // Mapper does web/modules/contrib/feeds/src/Entity/FeedType.php
    $mapper = \Drupal::service('feeds_para_mapper.mapper');
    $field_type = $mapper->getInfo($field_definition,'type');
    $plugin = $mapper->getInfo($field_definition,'plugin');
/*    if ($plugin['id'] != 'bigint' && $plugin['id'] != 'boolean' && $plugin['id'] != 'string' && $plugin['id'] != 'datetime') {
      \Drupal::logger("feeds_para_mapper")->notice('Plugin found for class @class with name @name', ['@class' => $plugin['class'], '@name' => $field_definition->getName()]);
    }*/
    $path = $mapper->getInfo($field_definition,'path');
    if(!isset($field_type) || !isset($plugin)){
      return null;
    }
    $class = $plugin['class'];
   if ($field_type == 'cbi_entity_reference' && $class == 'Drupal\feeds\Feeds\Target\EntityReference') {
      $class = 'Drupal\cbi_feed_alter\Feeds\Target\CbiTaxonomyEntityReference';
    }

    $field_definition->set('field_type', $field_type);

    // Taxonomy web/modules/contrib/feeds/src/Feeds/Target/ConfigEntityReference.php. Doesn't implement ConfigEntityInterface
    // \Drupal::entityTypeManager()->getDefinition($type)->entityClassImplements(EntityInterface::class)

    $targetDef = $class::prepareTarget($field_definition);
    if (!isset($targetDef)) {
      return null;
    }
    $label = $field_definition->getLabel();
    $label .= ' (';
    foreach ($path as $i => $host) {
      if($i +1 === count($path)){
        $label .= $host['host_field'];
      }else{
        $label .= $host['host_field'] . ":";
      }
    }
    $label .= ')';
    $field_definition->set('label', $label);
    $field_definition->set('field_type','entity_reference_revisions');
    return $targetDef;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration()
  {
    $mapper = $this->getMapper();
    $config = $this->targetInstance->defaultConfiguration();
    $has_settings = $mapper->getInfo($this->field, 'has_settings');
    if($has_settings){
      $config['max_values'] = $mapper->getMaxValues($this->field, $this->configuration);
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->targetInstance->buildConfigurationForm($form,$form_state);
    $has_settings = $this->mapper->getInfo($this->field,'has_settings');
    if ($has_settings) {
      $escaped = array('@field' => $this->field->getName());
      $des = $this->t('When @field field exceeds this number of values,
     a new paragraph entity will be created to hold the remaining values.', $escaped);
      $element = array(
        '#type' => 'textfield',
        '#title' => $this->t("Maximum Values"),
        '#default_value' => $this->configuration['max_values'],
        '#description' => $des,
      );
      $config['max_values'] = $element;
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    $delta = $form_state->getTriggeringElement()['#delta'];
    $configuration = $form_state->getValue(['mappings', $delta, 'settings']);
    $this->targetInstance->submitConfigurationForm($form,$form_state);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary()
  {
    $mapper = $this->getMapper();
    $sum = null;
    if ($this->targetInstance instanceof ConfigurableTargetInterface) {
      $sum = $this->targetInstance->getSummary();
    }
    $has_settings = $mapper->getInfo($this->field, 'has_settings');
    $final_str = $sum;
    if ($has_settings) {
      $temp_sum = "Maximum values: " . $this->configuration['max_values'];
      if(isset($sum) && $sum instanceof TranslatableMarkup) {
        $final_str = $sum->getUntranslatedString();
        $final_str .= "<br>" . $temp_sum;
        $args = $sum->getArguments();
        if (isset($args)) {
          $final_str = $this->t($final_str, $args);
        }
        else {
          $final_str = $this->t($final_str);
        }
      }
      else {
        $final_str = $sum . "<br>" . $this->t($temp_sum);
      }
    }
    return $final_str;
  }
  /**
   * Gets the mapper object.
   *
   * @return Mapper
   */
  private function getMapper(){
    if(isset($this->mapper)){
      return $this->mapper;
    }
    return \Drupal::service('feeds_para_mapper.mapper');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies()
  {
    $this->dependencies = parent::calculateDependencies();
    // Add the configured field as a dependency.
    $field_definition = $this->targetDefinition
      ->getFieldDefinition();
    // We need to add all parent fields as dependencies
    $fields = $this->mapper->loadParentFields($field_definition);
    $fields[] = $field_definition;
    foreach ($fields as $field) {
      if ($field && $field instanceof EntityInterface) {
        $this->dependencies['config'][] = $field->getConfigDependencyName();
      }
    }
    return $this->dependencies;
  }


  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    return parent::onDependencyRemoval($dependencies);
  }

}
