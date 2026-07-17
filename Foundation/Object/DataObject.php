<?php

namespace kernel\Foundation\Object;

use kernel\Foundation\Exception\Exception;
use stdClass;

/**
 * 文件信息
 * @property string $key 文件键
 * @property string $name 文件名称
 */
class DataObject extends stdClass
{
  public function __construct($data)
  {
    if (is_object($data)) {
      $data = $data->toArray();
    }
    $Vars = get_class_vars(get_class($this));
    foreach (array_keys($Vars) as $key) {
      $this->$key = $data[$key];
    }
  }
  function __get($name)
  {
    return $this->$name;
  }
  function __set($k, $v)
  {
    throw new Exception("数据对象只允许实例化时设置数据");
  }
  /**
   * 将属性输出为数组格式
   *
   * @return array
   */
  final function toArray()
  {
    $Vars = get_class_vars(get_class($this));
    $Data = [];
    foreach (array_keys($Vars) as $key) {
      $Data[$key] = $this->$key;
    }
    return $Data;
  }
  final function __toString()
  {
    $data = $this->toArray();

    return json_encode($data);
  }
}
