<?php
namespace Drupal\Tests\feeds_para_mapper\Unit\Helpers;


use Drupal\feeds\Feeds\Target\Text;
use Drupal\feeds_para_mapper\Utility\TargetInfo;
use Prophecy\Argument;

trait Common
{
  /**
   * @inheritdoc
   */
  public function getTargetInfo()
  {
    $targetInfo = new TargetInfo();
    $targetInfo->type = $this->getType();
    $targetInfo->in_common = array();
    $targetInfo->path = array();
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
    $text->setTarget(Argument::any(), Argument::any(), Argument::any(), Argument::any())->will(function($args){
      // @todo: maybe attach the value here
      $stop = null;
      return null;
    });
    return $text;
  }
}