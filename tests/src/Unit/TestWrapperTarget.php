<?php
namespace Drupal\Tests\feeds_para_mapper\Unit;

use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;
use Drupal\feeds_para_mapper\Utility\TargetInfo;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds\Feeds\Target\Text;
use Drupal\feeds_para_mapper\Feeds\Target\WrapperTarget;
use Prophecy\Argument;

/**
 * @group Feeds Paragraphs
 * @coversDefaultClass \Drupal\feeds_para_mapper\Feeds\Target\WrapperTarget
 */
class TestWrapperTarget extends FpmTestBase {
  /**
   * @var string
   */
  protected $class;
  /**
   * @var string
   */
  protected $type;
  /**
   * @var FieldTargetBase
   */
  protected $instanceMock;

  /**
   * The FeedsTarget plugin being tested.
   *
   * @var Text
   */
  protected $target;

  protected function setUp()
  {
    $this->class        = Text::class;
    $this->type         = "text";
    $this->instanceMock = $this->getInstanceMock();
    parent::setUp();
    $this->addServices($this->services);
  }

  /**
   * Returns the target class.
   *
   */
  function getClass()
  {
    return $this->class;
  }

  /**
   * Returns the target type.
   *
   */
  function getType()
  {
    return $this->type;
  }

  /**
   * @inheritdoc
   */
  public function getTargetInfo()
  {
    $targetInfo = new TargetInfo();
    $targetInfo->type = $this->getType();
    $targetInfo->in_common = array();
    $targetInfo->path = array(
      array(
        'bundle' => $this->bundles[array_keys($this->bundles)[0]],
        'host_field' => 'paragraph_field',
        'host_entity' => 'node',
        'order' => 0,
      ),
    );
    $targetInfo->paragraphs = array();
    $targetInfo->max_values = 1;
    $targetInfo->has_settings = true;
    $targetInfo->plugin = array(
      "class" => $this->getClass(),
      "id" => $this->getType(),
    );
    return $targetInfo;
  }

  /**
   * Returns a mocked field target object.
   *
   * @return FieldTargetBase
   *   The field target instance.
   */
  public function getInstanceMock()
  {
    $defaultConfig = array('format' => 'default format');
    $text = $this->prophesize(Text::class);
    $form = array();
    $form['format'] = [
      '#type' => 'select',
      '#title' => 'Filter format',
      '#options' => array('a','b','c'),
    ];
    $translation = $this->getStringTranslationStub();
    $text->defaultConfiguration()->willReturn($defaultConfig);
    $text->buildConfigurationForm(Argument::type('array'),Argument::any())
      ->willReturn($form);
    $text->submitConfigurationForm(Argument::any(),Argument::any())
      ->willReturn(null);
    $text->getSummary()->willReturn($translation->translate("test summary"));
    $this->instanceMock = $text;
    $this->target = $text->reveal();
    return $this->target;
  }

  /**
   * Mocks a form state object.
   * @return FormStateInterface
   *   The form state.
   */
  public function getFormStateMock(){
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects($this->any())
      ->method('getTriggeringElement')
      ->willReturn(array('#delta' => 0));

    $formState->expects($this->any())
      ->method('getValue')
      ->willReturn(array('format' => 'test format'));
    return $formState;
  }

  /**
   *
   * @covers ::createTargetInstance
   */
  public function testCreateTargetInstance()
  {
    $instance = $this->wrapperTarget->createTargetInstance();
    $this->assertTrue($instance instanceof Text);
  }

  /**
   *
   * @covers ::prepareTarget
   */
  public function testPrepareTarget(){
    $method = $this->getMethod(Text::class,'prepareTarget')->getClosure();
    $textDef = $method($this->fields[1]->reveal());
    $textPCount = count($textDef->getProperties());
    $method = $this->getMethod(WrapperTarget::class,'prepareTarget')->getClosure();
    $wrapperDef = $method($this->fields[1]->reveal());
    $wrapperPCount = count($wrapperDef->getProperties());
    $this->assertSame($textPCount,$wrapperPCount,'The wrapper has the target properties');
  }
  /**
   *
   * @covers ::defaultConfiguration
   */
  public function testDefaultConfiguration(){
    $textDefaultConfig = $this->target->defaultConfiguration();
    $wrapperDefaultConfig = $this->wrapperTarget->defaultConfiguration();
    $message = "Wrapper has the target's default configuration: ";
    foreach ($textDefaultConfig as $key => $configItem) {
      $this->assertArrayHasKey($key,$wrapperDefaultConfig, $message . $key);
    }
    $this->assertArrayHasKey('max_values', $wrapperDefaultConfig, $message . 'max_values');
  }

  /**
   *
   * @covers ::buildConfigurationForm
   */
  public function testBuildConfigurationForm(){
    $formState = $this->getFormStateMock();
    $textForm = $this->target->buildConfigurationForm(array(), $formState);
    $wrapperForm = $this->wrapperTarget->buildConfigurationForm(array(), $formState);
    $message = "Wrapper has the target's form element: ";
    foreach ($textForm as $field => $formMarkup) {
      $this->assertArrayHasKey($field,$wrapperForm, $message . $field);
    }
    $this->assertArrayHasKey('max_values', $wrapperForm, $message . 'max_values');
  }

  /**
   * @covers ::getSummary
   */
  public function testGetSummary(){
    $res = $this->wrapperTarget->getSummary();
    $res = $res->getUntranslatedString();
    $expected = "test summary<br>Maximum values: 1";
    $this->assertSame($res, $expected, "The target summary exists");
  }
  /**
   * @covers \Drupal\feeds_para_mapper\Feeds\Target\WrapperTarget::targets
   */
  public function testTargets(){
    $targets = array();
    $this->wrapperTarget->targets($targets,$this->feedType,array());
    $this->assertTrue(isset($targets['text_field']),'Target added');
    $field_type = $targets['text_field']->getFieldDefinition()->field_type;
    $this->assertSame('entity_reference_revisions', $field_type,'target field type is changed');
  }
}