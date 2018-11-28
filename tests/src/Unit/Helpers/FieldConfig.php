<?php
namespace Drupal\Tests\feeds_para_mapper\Unit\Helpers;


class FieldConfig {
  /**
   * @var string
   */
  public $name;

  /**
   * @var string
   */
  public $type;

  /**
   * @var int
   */
  public $id;

  /**
   * @var int
   */
  public $cardinality;

  /**
   * @var array
   */
  public $settings;

  /**
   * @var string
   */
  public $host_type;

  /**
   * @var string
   */
  public $host_bundle;

  /**
   * FieldConfig constructor.
   * @param string $name
   * @param string $type
   * @param int $id
   * @param int $cardinality
   * @param array $settings
   * @param string $host_type
   * @param string $host_bundle
   */
  public function __construct($name, $type, $id, $cardinality, array $settings, $host_type, $host_bundle)
  {
    $this->name = $name;
    $this->type = $type;
    $this->id = $id;
    $this->cardinality = $cardinality;
    $this->settings = $settings;
    $this->host_type = $host_type;
    $this->host_bundle = $host_bundle;
  }


}