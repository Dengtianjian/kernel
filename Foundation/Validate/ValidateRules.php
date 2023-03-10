<?php

namespace kernel\Foundation\Validate;

use kernel\Foundation\Exception\Exception;

class ValidateRules
{
  /**
   * 校验规则
   *
   * @var array
   */
  public $Rule = null;
  /**
   * 规则校验失败的错误信息
   *
   * @var array
   */
  public $ErrorMessages = [];
  public $typeCheckNullAllowed = false;
  /**
   * 快速实例化
   *
   */
  public static function quick()
  {
    return new ValidateRules();
  }
  /**
   * 是否等于指定值
   *
   * @param mixed $value 指定值
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function equal($value, $message = "")
  {
    $this->Rule["equal"] = $value;
    $this->ErrorMessages["equal"] = $message;
    return $this;
  }
  /**
   * 是否包含指定的值或者指定的数组所有元素
   *
   * @param string|array $value 任意基本类型值或者任意基本类型数组
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function includes($value, $message = "")
  {
    $this->Rule["includes"] = $value;
    $this->ErrorMessages["includes"] = $message;
    return $this;
  }
  /**
   * 校验数组是否存在指定键或者指定键数组
   *
   * @param string|array $value 键或者键数组
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function hasKeys($value, $message = "")
  {
    $this->Rule["hasKeys"] = $value;
    $this->ErrorMessages["hasKeys"] = $message;
    return $this;
  }
  /**
   * 校验目标值的数据类型是否等于指定数据类型或者目标值的数据类型是否存在于指定的数据类型数组中
   *
   * @param string|array $value 数据类型或者数据类型数组
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function type($value, $message = "")
  {
    if (is_array($value)) {
      $value = array_map(function ($item) {
        if ($item === "int") {
          $item = "integer";
        }
        if ($item === "bool") {
          $item = "boolean";
        }
        return $item;
      }, $value);
    } else {
      if ($value === "int") {
        $value = "integer";
      }
      if ($value === "bool") {
        $value = "boolean";
      }
    }

    $this->Rule["type"] = $value;
    $this->ErrorMessages["type"] = $message;
    return $this;
  }
  public function nullAllow($allow = true)
  {
    $this->typeCheckNullAllowed = $allow;
    return $this;
  }
  /**
   * 校验数值是否大于指定数值
   *
   * @param int $value 大于的指定数值
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function min($value, $message = "")
  {
    $this->Rule["min"] = $value;
    $this->ErrorMessages["min"] = $message;
    return $this;
  }
  /**
   * 校验数值是否小于指定数值
   *
   * @param int $value 小于的指定值
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function max($value, $message = "")
  {
    $this->Rule["max"] = $value;
    $this->ErrorMessages["max"] = $message;
    return $this;
  }
  /**
   * 校验数值是否在指定数值范围内
   *
   * @param int $min 最小值
   * @param int $max 最大值
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function range($min, $max, $message = "")
  {
    $this->Rule["range"] = [
      "min" => $min,
      "max" => $max,
    ];
    $this->ErrorMessages["range"] = $message;
    return $this;
  }
  /**
   * 校验字符串长度是否大于指定长度
   *
   * @param int $value 大于的指定长度数值
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function minLength($value, $message = "")
  {
    $this->Rule["minLength"] = $value;
    $this->ErrorMessages["minLength"] = $message;
    return $this;
  }
  /**
   * 校验字符串长度是否小于指定长度
   *
   * @param int $value 小于的指定长度数值
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function maxLength($value, $message = "")
  {
    $this->Rule["maxLength"] = $value;
    $this->ErrorMessages["maxLength"] = $message;
    return $this;
  }
  /**
   * 校验字符串长度是否在指定的长度范围内
   *
   * @param int $min 最小长度
   * @param int $max 最大长度
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function length($min, $max, $message = "")
  {
    $this->Rule["length"] = [
      "min" => $min,
      "max" => $max,
    ];
    $this->ErrorMessages["length"] = $message;
  }
  /**
   * 校验值是否为空或者为null
   *
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function required($message = "")
  {
    $this->Rule["required"] = true;
    $this->ErrorMessages["required"] = $message;
    return $this;
  }
  /**
   * 校验值是否存在枚举数组内
   *
   * @param array $enumList 枚举数组
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function enum($enumList, $message = "")
  {
    if (!is_array($enumList)) {
      throw new Exception("验证器枚举校验传入的枚举列表必须是个数组");
    }
    $this->Rule['enum'] = $enumList;
    $this->ErrorMessages["enum"] = $message;
    return $this;
  }
  /**
   * 正则表达式校验
   *
   * @param Regex $pattern 正则表达式
   * @param string $message 校验失败报错信息
   * @return ValidateRules
   */
  public function pattern($pattern, $message = "")
  {
    $this->Rule['pattern'] = $pattern;
    $this->ErrorMessages["pattern"] = $message;
    return $this;
  }
  /**
   * 自定义校验
   *
   * @param Closure|callable  $callback 校验函数，函数返回值必须是继承自Response响应类的，例如ResponseError、ReturnResutl
   * @return ValidateRules
   */
  public function custom($callback)
  {
    $this->Rule['CustomValidate'] = $callback;
    return $this;
  }
  /**
   * 使用别的校验规则
   *
   * @param ValidateRules $validateRule 校验规则实例数组或者校验规则实例
   * @return ValidateRules
   */
  public function useRule(ValidateRules $validateRule)
  {
    if (!isset($this->Rule['use'])) {
      $this->Rule['use'] = [];
    }
    array_push($this->Rule['use'], $validateRule);
    return $this;
  }
}
