<?php

namespace kernel\Foundation\Validate;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Exception;

class ValidateArray extends ValidateRules
{
  /**
   * 关联数组的字段规则
   *
   * @var array
   */
  private $FieldRules = [];
  /**
   * 构建验证数组规则类实例
   *
   * @param array $FieldRules 校验关联数组规则，传入值必须是关联数组
   */
  public function __construct($FieldRules = null)
  {
    if (!is_null($FieldRules)) {
      if (!is_array($FieldRules)) {
        throw new Exception("校验数组规则实例化传入的第一个参数必须是数组，且需要时关联数组");
      }
      if (!Arr::isAssoc($FieldRules)) {
        throw new Exception("校验数组规则实例化传入的第一个参数仅允许传入关联数组");
      }
      foreach ($FieldRules as $rule) {
        if (!$rule instanceof ValidateRules) {
          throw new Exception("校验数组规则的传入的规则必须是校验规则类实例");
        }
      }
      $this->FieldRules = $FieldRules;
    }
  }
  /**
   * 是否存在某个字段的规则
   *
   * @param string $key 字段名称
   * @return boolean
   */
  public function has($key)
  {
    return isset($this->FieldRules[$key]);
  }
  /**
   * 获取某个字段的规则
   *
   * @param string $key
   * @return ValidateRules
   */
  public function get($key)
  {
    return $this->FieldRules[$key];
  }
  /**
   * 获取全部字段校验规则
   *
   * @return array
   */
  public function all()
  {
    return $this->FieldRules;
  }
}
