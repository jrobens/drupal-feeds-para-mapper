<?php
namespace Drupal\Tests\feeds_para_mapper\Unit;

use Drupal\feeds\Feeds\Target\Text;
use Drupal\feeds_para_mapper\Importer;
use Drupal\feeds_para_mapper\RevisionHandler;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\feeds_para_mapper\Unit\Helpers\Common;
use Prophecy\Argument;

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

  protected function setUp()
  {
    $this->class = Text::class;
    $this->type  = "text";
    parent::setUp();
    $this->addServices($this->services);
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
    $importer = $this->prophesize(Importer::class);
    $constructor->invoke($mock, $this->messenger->reveal(), $importer->reveal());
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

  /**
   * @covers ::createRevision
   */
  public function testCreateRevision(){
    $revHandler = $this->getMockBuilder(RevisionHandler::class)
      ->disableOriginalConstructor()
      ->setMethods(array('updateParentRevision'))->getMock();
    $revHandler->expects($this->atLeastOnce())
      ->method('updateParentRevision')
      ->with($this->isInstanceOf(Paragraph::class));
    $method = $this->getMethod($revHandler,'createRevision');
    $paragraph = end($this->entityHelper->paragraphs);
    $bool = Argument::type('bool');
    $paragraph->setNewRevision($bool)->willReturn(null);
    $paragraph->isDefaultRevision($bool)->willReturn(null);
    $method->invoke($revHandler, $paragraph->reveal());
    $paragraph->setNewRevision($bool)->shouldHaveBeenCalled();
    $paragraph->isDefaultRevision($bool)->shouldHaveBeenCalled();
    $paragraph->save()->shouldHaveBeenCalled();
  }
}