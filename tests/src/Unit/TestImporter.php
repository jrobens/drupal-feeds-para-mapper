<?php

namespace Drupal\Tests\feeds_para_mapper\Unit;


use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\Feeds\Target\Text;
use Drupal\feeds_para_mapper\Importer;
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
  }

  /**
   * @covers ::import
   */
  public function testImport(){
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
    $this->importer->import($feed, $entity, $this->field, $config, $values, $instance);
    $this->instanceMock
      ->setTarget(Argument::any(),Argument::any(),Argument::any(),Argument::any())
      ->shouldHaveBeenCalled();
  }


}