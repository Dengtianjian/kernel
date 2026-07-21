<?php

namespace kernel\Foundation\Validation;

/**
 * 校验规则契约接口
 *
 * 定义所有校验规则方法的完整契约，{@see RuleBuilder} 为默认实现。
 * 上层通过 {@see Rule} 门面类调用，实现与具体构建器的解耦。
 *
 * @see RuleBuilder
 * @see Rule
 * @see Rules
 */
interface RuleInterface
{
  /**
   * 校验值是否为空或者为 null
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function required($message = "");

  /**
   * 校验目标值的数据类型是否等于指定数据类型，或是否存在于指定的数据类型数组中
   * int 自动转为 integer，bool 自动转为 boolean
   *
   * @param string|string[] $value 数据类型或数据类型数组
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function type($value, $message = "");

  /**
   * 校验是否等于指定值（严格比较 ===）
   *
   * @param mixed $value 指定值
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function equal($value, $message = "");

  /**
   * 校验字符串是否包含子串，或数组是否包含指定元素
   *
   * @param string|array $value 任意基本类型值或任意基本类型数组
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function includes($value, $message = "");

  /**
   * 校验数组是否存在指定键或指定键数组
   *
   * @param string|string[] $value 键或键数组
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function hasKeys($value, $message = "");

  /**
   * 校验数值是否 >= 指定值
   *
   * @param int $value 指定的最小值
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function min($value, $message = "");

  /**
   * 校验数值是否 <= 指定值
   *
   * @param int $value 指定的最大值
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function max($value, $message = "");

  /**
   * 校验数值是否在 [min, max] 范围内
   *
   * @param int $min 最小值
   * @param int $max 最大值
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function range($min, $max, $message = "");

  /**
   * 校验字符串/数组长度是否 >= 指定值
   *
   * @param int $value 指定的最小长度
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function minLength($value, $message = "");

  /**
   * 校验字符串/数组长度是否 <= 指定值
   *
   * @param int $value 指定的最大长度
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function maxLength($value, $message = "");

  /**
   * 校验字符串/数组长度是否在 [min, max] 范围内
   *
   * @param int $min 最小长度
   * @param int $max 最大长度
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function length($min, $max, $message = "");

  /**
   * 正则表达式校验
   *
   * @param string $pattern 正则表达式
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function pattern($pattern, $message = "");

  /**
   * 自定义校验
   *
   * @param \Closure|callable $callback 校验函数，返回值必须是继承自 Response 的实例
   * @return $this
   */
  public function custom($callback);

  /**
   * 复用已有的校验规则实例
   *
   * @param RuleInterface $validateRule 校验规则实例
   * @return $this
   */
  public function useRule(RuleInterface $validateRule);

  // ──────────────────────────────────────
  //  格式校验类
  // ──────────────────────────────────────

  /**
   * 校验值是否为有效的邮箱地址
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function email($message = "");

  /**
   * 校验值是否为有效的 URL 地址
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function url($message = "");

  /**
   * 校验值是否为有效的 IP 地址（支持 IPv4 和 IPv6）
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function ip($message = "");

  /**
   * 校验值是否为有效的日期字符串
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function date($message = "");

  /**
   * 校验值是否符合指定的日期格式
   *
   * @param string $format  日期格式，如 Y-m-d、Y-m-d H:i:s
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function dateFormat($format, $message = "");

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
  public function in($values, $message = "");

  /**
   * 校验值是否不在给定的值列表中
   *
   * 可变参数：支持 notIn('a', 'b', 'c') 或 notIn(['a', 'b', 'c'], 'message')。
   *
   * @param array|string ...$values 值列表或数组+消息
   * @return $this
   */
  public function notIn($values, $message = "");

  /**
   * 校验字段的值是否与 字段名_confirmation 的值一致（常用于密码确认）
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function confirmed($message = "");

  /**
   * 校验字段的值是否与另一个指定字段的值相同
   *
   * @param string $field   另一个字段名
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function same($field, $message = "");

  /**
   * 校验字段的值是否与另一个指定字段的值不同
   *
   * @param string $field   另一个字段名
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function different($field, $message = "");

  // ──────────────────────────────────────
  //  条件与特殊类
  // ──────────────────────────────────────

  /**
   * 允许字段值为 null。值为 null 时跳过后续所有校验规则，直接通过
   *
   * @param string $message 校验失败报错信息（本规则不产生错误，仅用于兼容接口）
   * @return $this
   */
  public function nullable($message = "");

  /**
   * 允许字段值为空字符串。值为空字符串时跳过后续所有校验规则，直接通过
   *
   * @param string $message 校验失败报错信息（本规则不产生错误，仅用于兼容接口）
   * @return $this
   */
  public function present($message = "");

  /**
   * 校验字段不能存在于输入数据中
   *
   * @param string $message 校验失败报错信息
   * @return $this
   */
  public function prohibited($message = "");

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
  public function requiredIf($anotherField, $values, $message = "");

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
  public function requiredUnless($anotherField, $values, $message = "");
}
