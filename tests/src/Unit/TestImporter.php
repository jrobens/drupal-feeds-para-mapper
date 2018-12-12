<?php

namespace Drupal\Tests\feeds_para_mapper\Unit;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\feeds\Feeds\Target\Text;
use Drupal\feeds_para_mapper\Importer;
use Drupal\feeds_para_mapper\Utility\TargetInfo;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\feeds_para_mapper\Unit\Helpers\Common;
use Prophecy\Argument;

/**
 * @group Feeds Paragraphs
 * @coversDefaultClass \Drupal\feeds_para_mapper\Importer
 */
class TestImporter extends FpmTestBase
{
  use Common;
  /**
   * @var string
   */
  protected $class;
  /**
   * @var string
   */
  protected $type;
  /**
   * @var Importer
   */
  protected $importer;

  /**
   * @var FieldDefinitionInterface
   */
  protected $field;
  /**
   * @inheritdoc
   */
  protected function setUp()
  {
    $this->class = Text::class;
    $this->type  = "text";
    parent::setUp();
    $this->addServices($this->services);
    $entity_manager = $this->entityHelper->getEntityTypeManagerMock();
    $field_manager = $this->fieldHelper->getEntityFieldManagerMock();
    $mapper = $this->getMapperObject();
    $this->importer = new Importer($entity_manager, $field_manager, $mapper);
    $targets = $mapper->getTargets('node', 'products');
    $this->field = $targets[0];
    $this->initImporter();
  }
  protected function initImporter(){
    $propsValues = array(
      'feed'          => $this->getFeedMock(),
      'entity'        => $this->node->reveal(),
      'target'        => $this->field,
      'configuration' => array('max_values' => 1),
      'values'        => array( array('value' => "Test value")),
      'targetInfo'    => $this->field->get('target_info'),
      'instance'      => $this->wrapperTarget->createTargetInstance(),
    );
    foreach ($propsValues as $prop => $value) {
      $this->updateProperty($this->importer,$prop, $value);
    }
  }
  /**
   * @covers ::import
   */
  public function testImport(){
    $this->entityHelper->values = array();
    $feed = $this->getFeedMock();
    $entity = $this->entityHelper->node;
    $config = array(
      'max_values' => 1,
    );
    $values = array(
      array(
        'value' => "Test value",
      ),
    );
    $instance = $this->wrapperTarget->createTargetInstance();
    $this->importer->import($feed, $entity->reveal(), $this->field, $config, $values, $instance);
    $this->instanceMock
      ->setTarget(Argument::any(),Argument::any(),Argument::any(),Argument::any())
      ->shouldHaveBeenCalled();
  }

  /**
   * @covers ::initHostParagraphs
   */
  public function testInitHostParagraphs(){
    $this->entityHelper->values = array();
    $method = $this->getMethod(Importer::class,'initHostParagraphs');
    $result = $method->invoke($this->importer);
    foreach ($result as $item) {
      $paragraph = $item['paragraph'];
      $host_info = $paragraph->host_info;
      self::assertNotNull($host_info);
      self::assertTrue(count($host_info) === 4, "The info array should contain 4 items");
      $value = $item['value'];
      self::assertTrue(count($value) > 0, "The value key contains values");
    }
  }

  /**
   *
   * @covers ::getTarget
   */
  public function testGetTarget(){
    $this->entityHelper->values = array();
    $method = $this->getMethod(Importer::class,'getTarget');
    $any = Argument::any();
    $str = Argument::type('string');
    $paragraph = $this->prophesize(Paragraph::class);
    $paragraph->hasField($any)->willReturn(true);
    $values = array(array('entity' => $paragraph->reveal()));
    $this->node->hasField($str)->willReturn(true);
    $fieldItem = $this->prophesize(EntityReferenceRevisionsFieldItemList::class);
    $fieldItem->getValue()->willReturn($values);
    $this->node->get($str)->willReturn($fieldItem->reveal());
    $paragraph->get($str)->willReturn($fieldItem->reveal());
    $args = array($this->node->reveal(), $this->field);
    // Call getTarget:
    $result = $method->invokeArgs($this->importer,$args);
    self::assertNotEmpty($result, "Result not empty");
    foreach ($result as $item) {
      self::assertInstanceOf(Paragraph::class, $item, "The result item is paragraph");
    }
    // Test with non-nested field:
    $info = $this->field->get('target_info');
    $info->path = array(
      array (
        'bundle' => 'bundle_one',
        'host_field' => 'paragraph_field',
        'host_entity' => 'node',
        'order' => 0,
      ),
    );
    $this->field->set('target_info', $info);
    $args = array($paragraph->reveal(), $this->field);
    // Call getTarget:
    $result = $method->invokeArgs($this->importer,$args);
    self::assertNotEmpty($result, "Result not empty");
    foreach ($result as $item) {
      self::assertInstanceOf(Paragraph::class, $item, "The result item is paragraph");
    }
  }

  /**
   * @covers ::loadTarget
   */
  public function testLoadTarget(){
    $method = $this->getMethod(Importer::class,'loadTarget')->getClosure();
    $storage = $this->entityHelper->getEntityTypeManagerMock()->getStorage('paragraph');
    $result = $method($this->node->reveal(), $this->field, $storage);
    self::assertNotEmpty($result,"nested entities loaded");
    // Test with non-nested field:
    $info = $this->field->get('target_info');
    $info->path = array(
      array (
        'bundle' => 'bundle_one',
        'host_field' => 'paragraph_field',
        'host_entity' => 'node',
        'order' => 0,
      ),
    );
    $this->field->set('target_info', $info);
    $result = $method($this->node->reveal(), $this->field, $storage);
    self::assertNotEmpty($result,"flat entity loaded");
  }

  /**
   * @covers ::createParagraphs
   */
  public function testCreateParagraphs(){
    $this->entityHelper->values = array();
    $method = $this->getMethod(Importer::class,'createParagraphs');
    $values = array(array('a'), array('b'), array('c'));
    $args = array($this->node->reveal(), $values);
    $result = $method->invokeArgs($this->importer, $args);
    self::assertCount(3, $result);
    for ($i = 0; $i < count($result); $i++) {
      self::assertArrayEquals($values[$i], $result[$i]['value']);
      self::assertInstanceOf(Paragraph::class, $result[$i]['paragraph']);
    }
  }

  /**
   * @covers ::updateParagraphs
   */
  public function testUpdateParagraphs(){
    $this->entityHelper->values = array();
    $method = $this->getMethod(Importer::class,'updateParagraphs');
    $values = array(
      array(array('value' => 'a')),
      array(array('value' => 'b')),
      array(array('value' => 'c')),
    );
    $paragraphs = $this->entityHelper->paragraphs;
    $lastPar = end($paragraphs);
    $args = array(array($lastPar->reveal()), $values);
    $result = $method->invokeArgs($this->importer, $args);
    self::assertCount(1, $result);
    for ($i = 0; $i < count($result); $i++) {
      self::assertArrayEquals($values[$i], $result[$i]['value']);
      self::assertInstanceOf(Paragraph::class, $result[$i]['paragraph']);
      self::assertArrayHasKey('state', $result[$i]);
    }
  }

  /**
   * @covers ::appendParagraphs
   */
  public function testAppendParagraphs(){
    $this->entityHelper->values = array(
      'bundle_two_text' => array(
        array(
          'value' => 'a'
        ),
      ),
    );
    $method = $this->getMethod(Importer::class,'appendParagraphs');
    $values = array(
      array(array('value' => 'a')),
      array(array('value' => 'b')),
      array(array('value' => 'c')),
      );
    $paragraphs = array_values($this->entityHelper->paragraphs);
    $paragraph = $paragraphs[1]->reveal();
    $paragraph->host_info = array(
      'field' => 'bundle_one_bundle_two',
      'bundle' => 'bundle_two',
      'entity' => $paragraphs[0]->reveal(),
      'type' => 'paragraph',
    );
    $args = array(array($paragraph), $values);
    $result = $method->invokeArgs($this->importer, $args);
    self::assertCount(3, $result);
    for ($i = 0; $i < count($result); $i++) {
      self::assertArrayEquals($values[$i], $result[$i]['value']);
      self::assertInstanceOf(Paragraph::class, $result[$i]['paragraph']);
      self::assertArrayHasKey('state', $result[$i]);
      $host_info = $result[$i]['paragraph']->host_info;
      self::assertArrayEquals($paragraph->host_info, $host_info);
    }
  }

  /**
   * @covers ::createParents
   */
  public function testCreateParents(){
    $this->entityHelper->values = array();
    $method = $this->getMethod(Importer::class,'createParents');
    $node = $this->node->reveal();
    $expected = array(
      'type' => 'node',
      'entity' => $node,
      'bundle' => 'bundle_one',
      'field' => 'paragraph_field',
    );
    $result = $method->invokeArgs($this->importer, array($node));
    self::assertSame($expected, $result->host_info);
    // Test with already created parents:
    $result = $method->invokeArgs($this->importer, array($node));
    self::assertNull($result);
  }

  /**
   * @covers ::duplicateExisting
   */
  public function testDuplicateExisting(){
    $this->entityHelper->values = array();
    $method = $this->getMethod(Importer::class,'duplicateExisting');
    $paragraph = $this->entityHelper->paragraphs[2];
    $paragraph->isNew()->willReturn(false);
    $parObject = $paragraph->reveal();
    $result = $method->invokeArgs($this->importer, array($parObject));
    $paragraph->isNew()->shouldHaveBeenCalled();
    $paragraph->getParentEntity()->shouldHaveBeenCalled();
    $paragraph->getType()->shouldHaveBeenCalled();
    $paragraph->getParentEntity()->shouldHaveBeenCalled();
    $paragraph->get('parent_field_name')->shouldHaveBeenCalled();
    self::assertInstanceOf(Paragraph::class, $result);
  }

  /**
   * @covers ::removeExistingParents
   */
  public function testRemoveExistingParents(){
    $this->entityHelper->values = array();
    $method = $this->getMethod(Importer::class,'removeExistingParents');
    $path = $this->field->get('target_info')->path;
    $result = $method->invokeArgs($this->importer, array($path));
    self::assertArrayHasKey('parents', $result, 'parents key exists');
    self::assertArrayHasKey('removed', $result, 'removed key exists');
    self::assertCount(count($path), $result['parents'], 'parents count is correct');
    self::assertCount(0, $result['removed'], 'removed array is empty');
    // check that the order of each parent is correct
    for($i=1; $i < count($result['parents']); $i++){
      self::assertTrue($result['parents'][$i]['order'] > $result['parents'][$i -1]['order'], 'Parents order is correct');
    }
    $this->entityHelper->values['paragraph_field'] = array(
      array(
        'entity' => $this->entityHelper->paragraphs[1]
      )
    );
    $result = $method->invokeArgs($this->importer, array($path));
    self::assertCount(count($path) -1, $result['parents'], 'parents count is correct');
    self::assertCount(1, $result['removed'], 'removed is not empty');
  }

  /**
   * @covers ::createParagraph
   */
  public function testCreateParagraph(){
    $this->entityHelper->values = array();
    $method = $this->getMethod(Importer::class,'createParagraph');
    $node = $this->node->reveal();
    $args = array(
      $field = "paragraph_field",
      $bundle = "bundle_one",
      $node,
    );
    $result = $method->invokeArgs($this->importer, $args);
    $value = $this->entityHelper->values['paragraph_field'];
    self::assertTrue(isset($value[0]['entity']), 'the host entity has the created paragraph');
    self::assertInstanceOf(Paragraph::class, $result);
    $host_info = $result->host_info;
    $keys = array(
      'type',
      'entity',
      'bundle',
      'field'
    );
    foreach ($keys as $key) {
      self::assertArrayHasKey($key, $host_info);
    }
  }
}