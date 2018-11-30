<?php


namespace Drupal\Tests\feeds_para_mapper\Unit\Helpers;


use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophet;

class EntityHelper
{
  /**
   * @var ObjectProphecy
   */
  public $node;
  protected $prophet;

  /**
   * @var FieldHelper
   */
  protected $fieldHelper;

  /**
   * @var array
   */
  protected $values;

  public function __construct(FieldHelper $fieldHelper){
    $this->prophet = new Prophet();
    $this->node = $this->getEntity('node', $fieldHelper->node_bundle);
    $this->fieldHelper = $fieldHelper;
  }

  /**
   * Creates entity object
   * @param string $type
   *   The entity type.
   * @param string $bundle
   *   The entity bundle.
   *
   * @return ObjectProphecy
   *   A mocked entity object.
   */
  private function getEntity($type, $bundle){
    $class = Node::class;
    if($type === 'paragraph'){
      $class = Paragraph::class;
    }
    $entity = $this->prophet->prophesize($class);
    $entity->isNew()->willReturn(true);
    $that = $this;
    $entity->hasField(Argument::type('string'))->will(function($args) use ($that, $type, $bundle){
      if($type === 'node'){
        $field = $that->fieldHelper->fields[0]->reveal()->getName();
        if($field === $args[0]){
          return true;
        }
        return false;
      }
      else {
        $fields = $that->fieldHelper->getBundleFields($bundle);
        foreach ($fields as $field) {
          if($field->reveal()->getName() === $args[0]){
            return true;
          }
        }
        return false;
      }
    });
    $entity->getEntityTypeId()->willReturn($type);
    $entity->bundle()->willReturn($bundle);
    $that = $this;
    $entity->get(Argument::type('string'))->will(function($args) use ($entity, $that){
      return $that->getFieldItemListMock($args[0]);
    });

    $entity->getFieldDefinitions()->will(function ($args) use ($that, $type, $bundle){
      return $that->fieldHelper->getFieldDefinitions($type, $bundle);
    });
    return $entity;
  }

  /**
   *
   * @param string $field
   *
   * @return EntityReferenceRevisionsFieldItemList
   */
  private function getFieldItemListMock($field){
    $that = $this;
    $fieldItem = $this->prophet->prophesize(EntityReferenceRevisionsFieldItemList::class);
    $fieldItem->getValue()->will(function($args) use ($that, $field){
      if(isset($that->values[$field])) {
        return $that->values[$field];
      }
      return array();
    });
    $fieldItem->appendItem(Argument::any())->will(function($args) use ($that,$field){
      $values = array();
      if(isset($that->values[$field])){
        $values = $that->values[$field];
      }
      $values[] = array('entity' => $args[0]);
      $that->values[$field] = $values;
      return $this->reveal();
    });
    return $fieldItem->reveal();
  }
  /** Creates entity manager instance.
   *
   * @return EntityTypeManagerInterface
   */
  public function getEntityTypeManagerMock(){
    $manager = $this->prophet->prophesize('Drupal\Core\Entity\EntityTypeManagerInterface');
    $storage = $this->prophet->prophesize(EntityStorageInterface::class);
    $that = $this;
    $storage->create(Argument::type('array'))->will(function($args) use ($that){
      $bundle = $args[0]['type'];
      return $that->getEntity('paragraph', $bundle)->reveal();
    });
    try {
      $manager->getStorage(Argument::type('string'))->willReturn($storage->reveal());
    } catch (InvalidPluginDefinitionException $e) {
    } catch (PluginNotFoundException $e) {
    }
    return $manager->reveal();
  }
}