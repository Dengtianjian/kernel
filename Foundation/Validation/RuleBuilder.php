<?php

namespace kernel\Foundation\Validation;

/**
 * 校验规则构建器
 *
 * 持有全部校验规则定义方法及规则状态数据，作为 {@see Rule} 的内部代理。
 * 架构上对应 Database/Model 中的 Builder/Query 角色：
 * Rule（门面）通过魔术方法委托到本类，本类负责实际的规则存储与链式构建。
 *
 * 实现 {@see RuleInterface} 契约，所有规则方法返回 $this 以支持链式调用。
 *
 * 规则存储结构：
 * - {@see $rule}: 规则名 → 规则值的映射，最终由 {@see Validator} 消费
 * - {@see $errorMessages}: 规则名 → 自定义错误信息
 *
 * @property array|null $rule                    规则定义，键为规则名（required/type/min 等），值为规则参数
 * @property array      $errorMessages           自定义错误信息，键为规则名，值为错误文案
 *
 * @see Rule
 * @see Validator
 * @see RuleInterface
 */
class RuleBuilder implements RuleInterface
{
  /**
   * 校验规则
   *
   * @var array|null
   */
  public $rule;

  /**
   * 规则校验失败的错误信息
   *
   * @var array
   */
  public $errorMessages = [];

  /**
   * 校验值是否为空或者为 null
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function required($message = "")
  {
    $this->rule["required"] = true;
    $this->errorMessages["required"] = $message;
    return $this;
  }

  /**
   * 校验目标值的数据类型是否等于指定数据类型或者目标值的数据类型是否存在于指定的数据类型数组中
   *
   * @param string|array $value 数据类型或者数据类型数组
   * @param string $message 校验失败报错信息
   * @return $this
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

    $this->rule["type"] = $value;
    $this->errorMessages["type"] = $message;
    return $this;
  }

  /**
   * 是否等于指定值
   *
   * @param mixed $value 指定值
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function equal($value, $message = "")
  {
    $this->rule["equal"] = $value;
    $this->errorMessages["equal"] = $message;
    return $this;
  }

  /**
   * 是否包含指定的值或者指定的数组所有元素
   *
   * @param string|array $value 任意基本类型值或者任意基本类型数组
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function includes($value, $message = "")
  {
    $this->rule["includes"] = $value;
    $this->errorMessages["includes"] = $message;
    return $this;
  }

  /**
   * 校验数组是否存在指定键或者指定键数组
   *
   * @param string|array $value 键或者键数组
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function hasKeys($value, $message = "")
  {
    $this->rule["hasKeys"] = $value;
    $this->errorMessages["hasKeys"] = $message;
    return $this;
  }

  /**
   * 校验数值是否大于指定数值
   *
   * @param int $value 大于的指定数值
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function min($value, $message = "")
  {
    $this->rule["min"] = $value;
    $this->errorMessages["min"] = $message;
    return $this;
  }

  /**
   * 校验数值是否小于指定数值
   *
   * @param int $value 小于的指定值
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function max($value, $message = "")
  {
    $this->rule["max"] = $value;
    $this->errorMessages["max"] = $message;
    return $this;
  }

  /**
   * 校验数值是否在指定数值范围内
   *
   * @param int $min 最小值
   * @param int $max 最大值
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function range($min, $max, $message = "")
  {
    $this->rule["range"] = [
      "min" => $min,
      "max" => $max,
    ];
    $this->errorMessages["range"] = $message;
    return $this;
  }

  /**
   * 校验字符串长度是否大于指定长度
   *
   * @param int $value 大于的指定长度数值
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function minLength($value, $message = "")
  {
    $this->rule["minLength"] = $value;
    $this->errorMessages["minLength"] = $message;
    return $this;
  }

  /**
   * 校验字符串长度是否小于指定长度
   *
   * @param int $value 小于的指定长度数值
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function maxLength($value, $message = "")
  {
    $this->rule["maxLength"] = $value;
    $this->errorMessages["maxLength"] = $message;
    return $this;
  }

  /**
   * 校验字符串长度是否在指定的长度范围内
   *
   * @param int $min 最小长度
   * @param int $max 最大长度
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function length($min, $max, $message = "")
  {
    $this->rule["length"] = [
      "min" => $min,
      "max" => $max,
    ];
    $this->errorMessages["length"] = $message;
    return $this;
  }

  /**
   * 正则表达式校验
   *
   * @param string $pattern 正则表达式
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function pattern($pattern, $message = "")
  {
    $this->rule['pattern'] = $pattern;
    $this->errorMessages["pattern"] = $message;
    return $this;
  }

  /**
   * 自定义校验
   *
   * @param \Closure|callable $callback 校验函数，签名 `function($value, $rules, $data): Result|void`
   *                                    失败时返回 `Result::failed()`；
   *                                    成功时返回 `Result::succeeded()`、或不返回/返回其他值皆视为通过
   * @return $this
   */
  public function custom($callback)
  {
    $this->rule['CustomValidate'] = $callback;
    return $this;
  }

  /**
   * 使用别的校验规则
   *
   * @param RuleInterface $validateRule 校验规则实例
   * @return $this
   */
  public function useRule(RuleInterface $validateRule)
  {
    if (!isset($this->rule['use'])) {
      $this->rule['use'] = [];
    }
    array_push($this->rule['use'], $validateRule);
    return $this;
  }

  // ──────────────────────────────────────
  //  格式校验类
  // ──────────────────────────────────────

  /**
   * 校验值是否为有效的邮箱地址
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function email($message = "")
  {
    $this->rule['email'] = true;
    $this->errorMessages['email'] = $message;
    return $this;
  }

  /**
   * 校验值是否为有效的 URL 地址
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function url($message = "")
  {
    $this->rule['url'] = true;
    $this->errorMessages['url'] = $message;
    return $this;
  }

  /**
   * 校验值是否为有效的 IP 地址（支持 IPv4 和 IPv6）
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function ip($message = "")
  {
    $this->rule['ip'] = true;
    $this->errorMessages['ip'] = $message;
    return $this;
  }

  /**
   * 校验值是否为有效的日期字符串
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function date($message = "")
  {
    $this->rule['date'] = true;
    $this->errorMessages['date'] = $message;
    return $this;
  }

  /**
   * 校验值是否符合指定的日期格式
   *
   * @param string $format  日期格式，如 Y-m-d、Y-m-d H:i:s
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function dateFormat($format, $message = "")
  {
    $this->rule['dateFormat'] = $format;
    $this->errorMessages['dateFormat'] = $message;
    return $this;
  }

  // ──────────────────────────────────────
  //  值比较与关联类
  // ──────────────────────────────────────

  /**
   * 校验值是否在给定的值列表中
   *
   * 可变参数：支持 in('a', 'b', 'c') 或 in(['a', 'b', 'c'], 'message')。
   *
   * @param array|string ...$values 值列表或数组+消息
   * @return $this
   */
  public function in($values, $message = "")
  {
    if (!is_array($values)) {
      // 可变参数模式：所有参数作为值列表，无自定义消息
      $values = func_get_args();
      $message = "";
    }
    $this->rule['in'] = $values;
    $this->errorMessages['in'] = $message;
    return $this;
  }

  /**
   * 校验值是否不在给定的值列表中
   *
   * 可变参数：支持 notIn('a', 'b', 'c') 或 notIn(['a', 'b', 'c'], 'message')。
   *
   * @param array|string ...$values 值列表或数组+消息
   * @return $this
   */
  public function notIn($values, $message = "")
  {
    if (!is_array($values)) {
      // 可变参数模式：所有参数作为值列表，无自定义消息
      $values = func_get_args();
      $message = "";
    }
    $this->rule['notIn'] = $values;
    $this->errorMessages['notIn'] = $message;
    return $this;
  }

  /**
   * 校验字段的值是否与 字段名_confirmation 的值一致（常用于密码确认）
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function confirmed($message = "")
  {
    $this->rule['confirmed'] = true;
    $this->errorMessages['confirmed'] = $message;
    return $this;
  }

  /**
   * 校验字段的值是否与另一个指定字段的值相同
   *
   * @param string $field   另一个字段名
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function same($field, $message = "")
  {
    $this->rule['same'] = $field;
    $this->errorMessages['same'] = $message;
    return $this;
  }

  /**
   * 校验字段的值是否与另一个指定字段的值不同
   *
   * @param string $field   另一个字段名
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function different($field, $message = "")
  {
    $this->rule['different'] = $field;
    $this->errorMessages['different'] = $message;
    return $this;
  }

  // ──────────────────────────────────────
  //  条件与特殊类
  // ──────────────────────────────────────

  /**
   * 允许字段值为 null。值为 null 时跳过后续所有校验规则，直接通过
   *
   * @param string $message 校验失败报错信息（本规则不产生错误，仅用于兼容接口）
   * @return $this
   */
  public function nullable($message = "")
  {
    $this->rule['nullable'] = true;
    $this->errorMessages['nullable'] = $message;
    return $this;
  }

  /**
   * 允许字段值为空字符串。值为空字符串时跳过后续所有校验规则，直接通过
   *
   * @param string $message 校验失败报错信息（本规则不产生错误，仅用于兼容接口）
   * @return $this
   */
  public function present($message = "")
  {
    $this->rule['present'] = true;
    $this->errorMessages['present'] = $message;
    return $this;
  }

  /**
   * 校验字段不能存在于输入数据中
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function prohibited($message = "")
  {
    $this->rule['prohibited'] = true;
    $this->errorMessages['prohibited'] = $message;
    return $this;
  }

  /**
   * 当另一个字段的值等于指定值时，此字段为必填
   *
   * 可变参数：支持 requiredIf('status', ['active', 'pending'], 'message') 或 requiredIf('status', 'active', 'pending')。
   *
   * @param string       $anotherField 另一个字段名
   * @param array|string $values       指定值或值列表
   * @param string       $message      校验失败报错信息
   * @return $this
   */
  public function requiredIf($anotherField, $values, $message = "")
  {
    if (!is_array($values)) {
      // 可变参数模式：跳过 $anotherField，剩余参数作为值列表
      $values = array_slice(func_get_args(), 1);
      $message = "";
    }
    $this->rule['requiredIf'] = [
      'field'  => $anotherField,
      'values' => (array)$values,
    ];
    $this->errorMessages['requiredIf'] = $message;
    return $this;
  }

  /**
   * 除非另一个字段的值等于指定值，否则此字段为必填
   *
   * 可变参数：支持 requiredUnless('status', ['draft'], 'message') 或 requiredUnless('status', 'draft')。
   *
   * @param string       $anotherField 另一个字段名
   * @param array|string $values       指定值或值列表
   * @param string       $message      校验失败报错信息
   * @return $this
   */
  public function requiredUnless($anotherField, $values, $message = "")
  {
    if (!is_array($values)) {
      // 可变参数模式：跳过 $anotherField，剩余参数作为值列表
      $values = array_slice(func_get_args(), 1);
      $message = "";
    }
    $this->rule['requiredUnless'] = [
      'field'  => $anotherField,
      'values' => (array)$values,
    ];
    $this->errorMessages['requiredUnless'] = $message;
    return $this;
  }
}
