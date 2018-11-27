<?php

namespace Drupal\Tests\feeds_para_mapper\Unit;


use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\feeds\FeedInterface;
use Drupal\feeds\Feeds\Processor\EntityProcessorBase;
use Drupal\feeds\FeedTypeInterface;
use Drupal\feeds\Plugin\Type\FeedsPluginManager;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
use Drupal\feeds_para_mapper\Feeds\Target\WrapperTarget;
use Drupal\feeds_para_mapper\Mapper;
use Drupal\feeds_para_mapper\Utility\TargetInfo;
use Drupal\node\Entity\Node;
use Drupal\Tests\feeds_para_mapper\Unit\Helpers\EntityHelper;
use Drupal\Tests\feeds_para_mapper\Unit\Helpers\FieldHelper;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionClass;
use Drupal\Core\DependencyInjection\ContainerBuilder;
abstract class FpmTestBase extends UnitTestCase
{
  /**
   * The field helper instance.
   *
   * @var FieldHelper
   */
  private $fieldHelper;

  /**
   * The entity helper instance.
   *
   * @var EntityHelper
   */
  private $entityHelper;

  /**
   * The wrapper instance.
   *
   * @var WrapperTarget
   */
  protected $wrapperTarget;

  /**
   * The mocked feed type instance.
   *
   * @var FeedTypeInterface
   */
  protected $feedType;

  /**
   * The mocked node.
   *
   * @var Node
   */
  protected $node;

  /**
   * The mocked paragraphs and node fields.
   *
   * @var ObjectProphecy[]
   */
  protected $fields;

  /**
   * The paragraphs bundles names.
   *
   * @var array
   */
  protected $bundles;

  /**
   * The services to mock.
   *
   * @var string[]
   */
  protected $services;

  /**
   * @inheritdoc
   */
  protected function setUp()
  {
    $this->bundles = array(
      'bundle_one' => 'bundle_one',
    );
    $this->fieldHelper = new FieldHelper($this->getTargetInfo(), $this->bundles);
    $this->fields = $this->fieldHelper->fields;
    $this->entityHelper = new EntityHelper();
    $this->node = $this->entityHelper->node;
    $services = array(
      'feeds_para_mapper.mapper' => $this->getMapperObject(),
      'entity_type.manager' => $this->entityHelper->getEntityTypeManagerMock(),
      'entity_field.manager' => $this->fieldHelper->getEntityFieldManagerMock(),
      'string_translation' => $this->getStringTranslationStub(),
    );
    $args = func_get_args();
    if (isset($args[0])) {
      $services = array_merge($args[0],$services);
    }
    $this->services = $services;
    $this->addServices($this->services);
    $this->initWrapper();
    parent::setUp();
  }

  /**
   * Returns the target info object.
   *
   * @return TargetInfo
   */
  abstract function getTargetInfo();

  /**
   * Returns a mocked field target object.
   * @return FieldTargetBase
   *   The field target instance.
   */
  abstract function getInstanceMock();

  /**
   * Adds services to the container.
   *
   * @param array $services
   */
  protected function addServices(array $services){
    $container = new ContainerBuilder();
    foreach ($services as $id => $service) {
      $container->set($id, $service);
    }
    \Drupal::setContainer($container);
  }

  /**
   * Instantiates and returns a WrapperTarget object.
   *
   */
  private function initWrapper(){
    $method = $this->getMethod('Drupal\feeds_para_mapper\Feeds\Target\WrapperTarget', 'prepareTarget')->getClosure();
    $this->feedType = $this->getFeedTypeMock();
    $field = $this->fields[0]->reveal();
    $configuration = [
      'feed_type' => $this->feedType,
      'target_definition' => $method($field),
    ];
    $id = "wrapper_target";
    $plugin_definition = array();
    $plugin_manager = $this->getPluginManagerMock();
    $mapper = $this->getMapperObject();
    $messenger = $this->getMessengerMock();
    $wrapperTarget = new WrapperTarget($configuration,$id, $plugin_definition,$messenger, $plugin_manager, $mapper);
    $this->wrapperTarget = $wrapperTarget;
  }

  /**
   * Mocks the messenger service.
   * @return MessengerInterface
   */
  private function getMessengerMock(){
    $messenger = $this->prophesize(MessengerInterface::class);
    $messenger->addWarning(Argument::any());
    return $messenger->reveal();
  }

  /**
   * Returns a mocked feed entity.
   *
   * @return FeedInterface
   *   A mocked feed entity.
   */
  protected function getFeedMock() {
    $feed = $this->prophesize(FeedInterface::class);
    $feed->getType()
      ->willReturn($this->getFeedTypeMock());
    return $feed->reveal();
  }

  /**
   * Creates feed type entity.
   *
   * @return \Drupal\feeds\FeedTypeInterface
   *   A mocked feed type entity.
   */
  protected function getFeedTypeMock() {
    $feed_type = $this->createMock(FeedTypeInterface::class);
    $processor = $this->getProcessorMock();
    $feed_type->id = 'test_feed_type';
    $feed_type->description = 'This is a test feed type';
    $feed_type->label = 'Test feed type';
    $feed_type->expects($this->any())
      ->method('label')
      ->will($this->returnValue($feed_type->label));
    $feed_type->expects($this->any())
      ->method('getProcessor')
      ->will($this->returnValue($processor));

    return $feed_type;
  }

  /**
   * Mocks an entity processor instance.
   *
   * @return EntityProcessorBase|\PHPUnit\Framework\MockObject\MockObject
   */
  private function getProcessorMock(){
    $processor = $this->getMockBuilder(EntityProcessorBase::class)
      ->disableOriginalConstructor()
      ->setMethods(array('entityType','bundle'))
      ->getMock();
    $processor->expects($this->any())
      ->method('entityType')
      ->will($this->returnValue("node"));
    $processor->expects($this->any())
      ->method('bundle')
      ->will($this->returnValue("product"));
    return $processor;
  }

  /**
   * Creates FeedsPluginManager instance.
   *
   * @return FeedsPluginManager
   *   A mocked feeds plugin manager instance.
   */
  private function getPluginManagerMock(){
    $manager = $this->prophesize(FeedsPluginManager::class);
    try {
      $manager->createInstance(Argument::type('string'), Argument::type('array'))
        ->willReturn($this->getInstanceMock());
    } catch (PluginException $e) {

    }
    $manager->getDefinitions()
      ->willReturn($this->generatePluginDefinitions());
    return $manager->reveal();
  }

  /**
   * Generates plugin definitions array (text plugin for now).
   *
   * @return array
   */
  protected function generatePluginDefinitions(){
    $definitions = array(
      'text' => array(
        'id' => 'text',
        'field_types' => array(
          'text',
          'text_long',
          'text_with_summary',
        ),
        'arguments' => array(
          '@current_user'
        ),
        'class' => 'Drupal\feeds\Feeds\Target\Text',
        'provider'=> 'feeds',
        'plugin_type' => 'target',
        'form' => array(
          'configuration' => 'Drupal\feeds\Feeds\Target\Text'
        ),
      ),
    );
    return $definitions;
  }

  /**
   * Calls a protected method on an object.
   *
   * @param string $class
   * @param string $name
   * @return \ReflectionMethod
   */
  protected function getMethod($class, $name) {
    try {
      $class = new ReflectionClass($class);
    } catch (\ReflectionException $e) {
    }
    $method = $class->getMethod($name);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   *  Generates a mapper object.
   * @return Mapper
   */
  protected function getMapperObject(){
    $plugin_manager = $this->getPluginManagerMock();
    $field_manager = $this->fieldHelper->getEntityFieldManagerMock();
    $bundleInfo = $this->fieldHelper->getEntityTypeBundleInfoMock();
    return new Mapper($plugin_manager,$field_manager,$bundleInfo);
  }
}