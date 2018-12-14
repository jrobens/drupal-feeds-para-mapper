<?php
namespace Drupal\Tests\feeds_para_mapper\Unit;

use Drupal\feeds\Feeds\Target\Text;
use Drupal\feeds_para_mapper\Importer;
use Drupal\feeds_para_mapper\RevisionHandler;
use Drupal\Tests\feeds_para_mapper\Unit\Helpers\Common;

/**
 * @group Feeds Paragraphs
 * @coversDefaultClass \Drupal\feeds_para_mapper\RevisionHandler
 */
class TestRevisionHandler extends FpmTestBase
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
   * @var RevisionHandler
   */
  protected $revHandler;

  /**
   * @var Importer
   */
  protected $importer;


  protected function setUp()
  {
    $this->class = Text::class;
    $this->type  = "text";
    parent::setUp();
    $this->addServices($this->services);
    $entity_manager = $this->entityHelper->getEntityTypeManagerMock()->reveal();
    $field_manager = $this->fieldHelper->getEntityFieldManagerMock();
    $mapper = $this->getMapperObject();
    try {
      $this->importer = new Importer($entity_manager, $field_manager, $mapper);
      $this->revHandler = new RevisionHandler($this->messenger->reveal(), $this->importer);
    } catch (\Exception $e) {
    }
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct(){
// Get mock, without the constructor being called
    $mock = $this->getMockBuilder(RevisionHandler::class)
      ->disableOriginalConstructor()
      ->getMock();
    $reflectedClass = new \ReflectionClass(RevisionHandler::class);
    $constructor = $reflectedClass->getConstructor();
    // Force the constructor to throw error:
    // now call the constructor
    $constructor->invoke($mock, $this->messenger->reveal(), $this->importer);
    $props = $reflectedClass->getProperties();
    $initialized = array(
      'messenger',
      'importer'
    );
    foreach ($props as $prop) {
      if(in_array($prop->getName(), $initialized)){
        $prop->setAccessible(true);
        $val = $prop->getValue($mock);
        self::assertNotEmpty($val);
      }
    }
  }

  /**
   * @covers ::handle
   */
  public function testHandle(){
    $field = end($this->fields)->reveal();
    $info = $this->getTargetInfo();
    $field->set('target_info', $info);
    $fpm_targets = array();
    $fpm_targets[$field->getName()] = $field;
    $node = $this->node->reveal();
    $node->fpm_targets = $fpm_targets;
    $revHandler = $this->getMockBuilder(RevisionHandler::class)
      ->disableOriginalConstructor()
      ->setMethods(['checkUpdates','cleanUp'])->getMock();
    $revHandler->expects($this->atLeastOnce())->method('checkUpdates');
    $revHandler->expects($this->atLeastOnce())->method('cleanUp');
    $revHandler->handle($node);
  }

  /**
   * @covers ::checkUpdates
   */
  public function testCheckUpdates(){
    $revHandler = $this->getMockBuilder(RevisionHandler::class)
      ->disableOriginalConstructor()
      ->setMethods(['createRevision'])->getMock();
    $revHandler->expects($this->atLeastOnce())->method('createRevision');
    $method = $this->getMethod($revHandler,'checkUpdates');
    $paragraph = end($this->entityHelper->paragraphs);
    $paragraph->isNew()->willReturn(false);
    $method->invokeArgs($revHandler, array(array($paragraph->reveal())));
  }
}