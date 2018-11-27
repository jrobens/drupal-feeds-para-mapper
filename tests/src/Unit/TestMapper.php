<?php

namespace Drupal\Tests\feeds_para_mapper\Unit;


use Drupal\feeds_para_mapper\Mapper;


/**
 * Class TestMapper
 * @coversDefaultClass \Drupal\feeds_para_mapper\Mapper
 */
class TestMapper extends TestWrapperTarget
{
  /**
   * @var Mapper
   */
  protected $mapper;

  /**
   * @inheritdoc
   */
  protected function setUp()
  {
    parent::setUp();
    $this->addServices($this->services);
    $this->mapper = $this->getMapperObject();
  }

  /**
   * @covers ::getTargets
   */
  public function testGetTargets(){
    $targets = $this->mapper->getTargets('node','product', true);
    $stop = null;
  }

}