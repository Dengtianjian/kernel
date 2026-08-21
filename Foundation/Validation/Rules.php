<?php

namespace kernel\Foundation\Validation;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Error;

/**
 * 关联数组校验规则
 *
 * 用于定义关联数组中每个字段的校验规则，每个字段对应一个 {@see Rule} 实例。
 * 继承自 {@see Rule}，因此同样支持流式链式调用定义自身级别的规则。
 *
 * 基本用法：
 * ```php
 * $rules = new Rules([
 *     'name' => Rule::required('姓名必填')->type('string')->length(1, 50),
 *     'age'  => Rule::required('年龄必填')->type('integer')->range(0, 150),
 * ]);
 * ```
 *
 * @method Rules required(string $message = "")          校验值是否为空或为 null
 * @method Rules type(string|array $value, string $message = "") 校验数据类型
 * @method Rules equal(mixed $value, string $message = "")      校验是否等于指定值
 * @method Rules includes(string|array $value, string $message = "") 校验是否包含指定值
 * @method Rules hasKeys(string|array $value, string $message = "")  校验数组是否存在指定键
 * @method Rules min(int $value, string $message = "")           校验数值是否 >= 指定值
 * @method Rules max(int $value, string $message = "")           校验数值是否 <= 指定值
 * @method Rules range(int $min, int $max, string $message = "") 校验数值是否在指定范围内
 * @method Rules minLength(int $value, string $message = "")     校验字符串/数组长度 >= 指定值
 * @method Rules maxLength(int $value, string $message = "")     校验字符串/数组长度 <= 指定值
 * @method Rules length(int $min, int $max, string $message = "") 校验字符串/数组长度在 [min, max] 范围内
 * @method Rules pattern(string $pattern, string $message = "")  正则表达式校验
 * @method Rules email(string $message = "")                     校验值是否为有效的邮箱地址
 * @method Rules url(string $message = "")                       校验值是否为有效的 URL 地址
 * @method Rules ip(string $message = "")                        校验值是否为有效的 IP 地址
 * @method Rules date(string $message = "")                      校验值是否为有效的日期字符串
 * @method Rules dateFormat(string $format, string $message = "") 校验值是否符合指定的日期格式
 * @method Rules in(array|string ...$values)                     校验值是否在给定的值列表中
 * @method Rules notIn(array|string ...$values)                  校验值是否不在给定的值列表中
 * @method Rules confirmed(string $message = "")                 校验值是否与 字段名_confirmation 一致
 * @method Rules same(string $field, string $message = "")       校验值是否与另一个字段的值相同
 * @method Rules different(string $field, string $message = "")  校验值是否与另一个字段的值不同
 * @method Rules nullable(string $message = "")                  允许值为 null
 * @method Rules present(string $message = "")                   允许值为空字符串
 * @method Rules prohibited(string $message = "")                校验字段不能存在于输入数据中
 * @method Rules requiredIf(string $anotherField, array|string $values, string $message = "") 当另一字段等于指定值时必填
 * @method Rules requiredUnless(string $anotherField, array|string $values, string $message = "") 除非另一字段等于指定值否则必填
 * @method Rules custom(\Closure|callable $callback)             自定义校验
 * @method Rules useRule(RuleInterface $validateRule)           复用已有的校验规则实例
 *
 * @see Rule
 * @see Validator
 */
class Rules extends Rule
{
  /**
   * 字段规则映射，结构为 [字段名 => Rule 实例]
   *
   * @var array<string, Rule>
   */
  private $fieldRules = [];

  /**
   * 条件规则（Laravel 风格 sometimes），仅当回调返回 true 时才参与校验
   *
   * 结构：[[ 'attribute' => string, 'rule' => Rule, 'callback' => callable ], ...]
   *
   * @var array
   */
  private $conditionalRules = [];

  /**
   * 构建关联数组校验规则实例
   *
   * @param array<string, Rule>|null $fieldRules 字段名到校验规则的映射，必须是关联数组
   * @throws Exception 当传入非关联数组或规则值不是 Rule 实例时抛出
   */
  public function __construct($fieldRules = null)
  {
    if (!is_null($fieldRules)) {
      if (!is_array($fieldRules)) {
        throw new Error("校验数组规则实例化传入的第一个参数必须是数组，且需要时关联数组");
      }
      if (!Arr::isAssoc($fieldRules)) {
        throw new Error("校验数组规则实例化传入的第一个参数仅允许传入关联数组");
      }
      foreach ($fieldRules as $rule) {
        if (!$rule instanceof Rule) {
          throw new Error("校验数组规则的传入的规则必须是校验规则类实例");
        }
      }
      $this->FieldRules = $fieldRules;
    }
  }

  /**
   * 检查是否存在指定字段的校验规则
   *
   * @param string $key 字段名称
   * @return bool
   */
  public function has($key)
  {
    return isset($this->FieldRules[$key]);
  }

  /**
   * 获取指定字段的校验规则
   *
   * @param string $key 字段名称
   * @return Rule|null 不存在时返回 null
   */
  public function get($key)
  {
    return $this->FieldRules[$key] ?? null;
  }

  /**
   * 获取全部字段校验规则
   *
   * @return array<string, Rule>
   */
  public function all()
  {
    return $this->FieldRules;
  }

  /**
   * 条件规则（Laravel 风格 sometimes）
   *
   * 仅在回调返回 true 时才将该字段的规则加入校验。
   * 回调签名为 function(array $data): bool，接收完整数据。
   *
   * @param string   $attribute 字段名（支持点号语法）
   * @param Rule     $rule      校验规则实例
   * @param callable $callback  条件判断回调，返回 bool
   * @return $this
   */
  public function sometimes(string $attribute, Rule $rule, callable $callback)
  {
    $this->conditionalRules[] = [
      'attribute' => $attribute,
      'rule'      => $rule,
      'callback'  => $callback,
    ];
    return $this;
  }

  /**
   * 获取所有条件规则
   *
   * @return array
   */
  public function getConditionalRules(): array
  {
    return $this->conditionalRules;
  }

  /**
   * 动态添加字段规则（供 Validator 在合并条件规则时使用）
   *
   * @param string $attribute 字段名
   * @param Rule   $rule      校验规则实例
   * @return $this
   */
  public function addRule(string $attribute, Rule $rule)
  {
    $this->FieldRules[$attribute] = $rule;
    return $this;
  }

  /**
   * 判断是否有字段名包含通配符 *
   *
   * @return bool
   */
  public function hasWildcard(): bool
  {
    foreach ($this->FieldRules as $fieldName => $_) {
      if (str_contains($fieldName, '*')) {
        return true;
      }
    }
    return false;
  }

  /**
   * 获取包含通配符的字段规则集合
   *
   * @return array<string, Rule>
   */
  public function wildcardRules(): array
  {
    $result = [];
    foreach ($this->FieldRules as $fieldName => $rule) {
      if (str_contains($fieldName, '*')) {
        $result[$fieldName] = $rule;
      }
    }
    return $result;
  }
}
