<?php

namespace Drupal\Tests\feeds_para_mapper\Unit\Helpers;


use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\feeds_para_mapper\Utility\TargetInfo;
use Drupal\field\FieldConfigInterface;
use Prophecy\Argument;
use Prophecy\Prophet;

class FieldHelper
{
  public $fields;
  public $paragraphField;
  public $bundles;
  public $node_bundle;
  protected $prophet;
  protected $targetInfo;
  public function __construct(TargetInfo $targetInfo)
  {
    $this->prophet = new Prophet();
    $this->targetInfo = $targetInfo;
    $this->bundles = array(
      'bundle_one' => 'bundle_one',
      'bundle_two' => 'bundle_two',
    );
    $this->node_bundle = "product";
    $this->fields = $this->getFields();
  }
  protected function getFields(){
    // @todo: add multiple bundles field:
    $fields = array();

    $fieldsConfig = array(
      new FieldConfig(
        'paragraph_field',
        'entity_reference_revisions',
        1,
        1,
        array(
          'handler_settings' => array(
            'target_bundles' => array($this->bundles['bundle_one']),
          ),
        ),
        'node',
        'product'
      ),
      new FieldConfig(
        'bundle_one_bundle_two',
        'entity_reference_revisions',
        2,
        1,
        array(
          'handler_settings' => array(
            'target_bundles' => array($this->bundles['bundle_two']),
          ),
        ),
        'paragraph',
        'bundle_one'
      ),
      new FieldConfig(
        'bundle_two_text',
        'text',
        3,
        -1,
        array(
          'handler_settings' => array(),
        ),
        'paragraph',
        'bundle_two'
      ),

    );
    foreach ($fieldsConfig as $fieldConfig) {
      $field = $this->getField($fieldConfig);
      $fields[] = $field;
    }
    return $fields;
  }

  /**
   * @param FieldConfig $config
   * @return \Prophecy\Prophecy\ObjectProphecy
   */
  public function getField(FieldConfig $config){
    $that = $this;
    $field = $this->prophet->prophesize(FieldConfigInterface::class);
    $field->getType()->willReturn($config->type);
    $field->getSettings()->willReturn($config->settings);
    $field->getSetting(Argument::type('string'))
      ->willReturn($config->settings['handler_settings']);
    $field->getTargetEntityTypeId()->willReturn($config->host_type);
    $field->getName()->willReturn($config->name);
    $field->id()->willReturn($config->id);
    $field->bundle()->willReturn($config->host_bundle);
    $field->get(Argument::type('string'))->will(function ($args) {
      $field = $this->reveal();
      if(isset($field->{$args[0]})){
        return $field->{$args[0]};
      }
      return null;
    });
    $field->set(Argument::type('string'),Argument::any())->will(function ($args){
      $field = $this->reveal();
      $field->{$args[0]} = $args[1];
    });
    $field->set('cardinality', Argument::type('string'))->will(function ($args) use ($config) {
      $config->cardinality = $args[1];
    });
    $field->getFieldStorageDefinition()->will(function ($args) use ($that, $config){
      return $that->getFieldStorageMock($config);
    });
    $field->set('bundle',$config->host_bundle);
    return $field;
  }
  protected function getFieldStorageMock(FieldConfig $config){
    $storage = $this->prophet->prophesize(FieldStorageDefinitionInterface::class);
    $storage->getCardinality()->willReturn((string) $config->cardinality);
    return $storage->reveal();
  }

  /**
   * Creates entity field manager instance.
   *
   * @return EntityFieldManagerInterface
   *   A mocked entity field manager instance.
   */
  public function getEntityFieldManagerMock(){
    $manager = $this->prophet->prophesize(EntityFieldManagerInterface::class);
    $that = $this;
    $manager->getFieldDefinitions(Argument::type('string'),Argument::type('string'))
      ->will(function($args) use ($that){
        switch ($args[0]){
          case "paragraph":
            $fields = $that->getBundleFields($args[1]);
            return $fields;
          case "node":
            return array($that->fields[0]->reveal());
          default:
            return null;
        }
      });
    return $manager->reveal();
  }

  /**
   * Get all fields for a bundle.
   *
   * @var string $bundle
   *
   * @return \Prophecy\Prophecy\ObjectProphecy[]
   */
  public function getBundleFields($bundle){
    $result = array();
    foreach ($this->fields as $field) {
      if($field->reveal()->bundle() == $bundle){
        array_push($result, $field);
      }
    }
    return $result;
  }

  /**
   * @return EntityTypeBundleInfoInterface
   */
  public function getEntityTypeBundleInfoMock(){
    $bundleInfo =  $this->prophet->prophesize(EntityTypeBundleInfoInterface::class);
    $that = $this;
    $bundleInfo->getBundleInfo(Argument::type('string'))
      ->will(function($args) use ($that){
        if($args[0] === 'paragraph'){
          $bundles = [];
          foreach ($that->bundles as $bundle) {
            $bundles[$bundle]['name'] = $bundle;
            $bundles[$bundle]['label'] = $bundle;
          }
          return $bundles;
        }
        return null;
      });
    return $bundleInfo->reveal();
  }
}