<?php


namespace Drupal\Tests\feeds_para_mapper\Unit\Helpers;


use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Prophecy\Argument;
use Prophecy\Prophet;

class EntityHelper
{
  /**
   * @var Node
   */
  public $node;
  protected $prophet;
  public function __construct(){
    $this->prophet = new Prophet();
    $this->node = $this->getEntity();
  }

  /**
   * Creates entity object
   * @param string $type
   *   The entity type.
   * @return EntityInterface
   *   A mocked entity object.
   */
  private function getEntity($type = "node"){
    $class = Node::class;
    if($type !== 'node'){
      $class = Paragraph::class;
    }
    $entity = $this->prophet->prophesize($class);
    $entity->isNew()->willReturn(true);
    $entity->hasField(Argument::any())->will(function($args){
      return $args[0] === 'paragraph_field';
    });
    $that = $this;
    $entity->get(Argument::any())->will(function($args) use ($that){
      return $args[0] === 'paragraph_field';
    });
    return $entity->reveal();
  }

  /** Creates entity manager instance.
   *
   * @return EntityTypeManagerInterface
   */
  public function getEntityTypeManagerMock(){
    $manager = $this->prophet->prophesize('Drupal\Core\Entity\EntityTypeManagerInterface');
    return $manager->reveal();
  }
}