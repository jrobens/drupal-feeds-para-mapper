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
  protected $prophet;
  protected $targetInfo;
  public function __construct(TargetInfo $targetInfo, $bundles)
  {
    $this->prophet = new Prophet();
    $this->targetInfo = $targetInfo;
    $this->bundles = $bundles;
    $this->fields = $this->getFields();
  }
  protected function getFields(){
    $fields = array();
    $fieldsConfig = array(
      array(
        'name' => 'paragraph_field',
        'type' => 'entity_reference_revisions',
        'id' => 1,
        'settings' => array(
          'handler_settings' => array(
            'target_bundles' => $this->bundles,
          ),
        ),
        'host_type' => 'node',
      ),
      array(
        'name' => 'text_field',
        'type' => 'text',
        'id' => 2,
        'settings' => array(
          'handler_settings' => array(),
        ),
        'host_type' => 'paragraph'
      ),
    );
    foreach ($fieldsConfig as $fieldConfig) {
      $field = $this->getField($fieldConfig);
      $fields[] = $field;
    }
    return $fields;
  }
  public function getField($config = array()){
    $field = $this->prophet->prophesize(FieldConfigInterface::class);

    $field->getType()->willReturn($config['type']);
    $field->getSettings()->willReturn($config['settings']);
    $field->getSetting(Argument::type('string'))
      ->willReturn($config['settings']['handler_settings']);
    $field->getTargetEntityTypeId()->willReturn($config['host_type']);
    $field->getName()->willReturn($config['name']);
    $field->id()->willReturn($config['id']);
    $field->get('target_info')->willReturn($this->targetInfo);
    $field->get(Argument::type('string'))->will(function ($args) {
      if(isset($this->{$args[0]})){
        return $this->{$args[0]};
      }
      return null;
    });
    $field->set(Argument::type('string'),Argument::any())->will(function ($args){
      $this->{$args[0]} = $args[1];
    });
    $field->getFieldStorageDefinition()->willReturn($this->getFieldStorageMock());
    return $field;
  }
  protected function getFieldStorageMock(){
    $storage = $this->prophet->prophesize(FieldStorageDefinitionInterface::class);
    $storage->getCardinality()->willReturn('1');
    return $storage->reveal();
  }
  protected function getFieldStorage(){
    $storage = $this->prophet->prophesize(FieldStorageDefinitionInterface::class);
    $storage->getCardinality()->willreturn(1);
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
        if($args[0] === 'paragraph'){
          return array($that->fields[1]->reveal());
        }
        return array($that->fields[0]->reveal());
      });
    return $manager->reveal();
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