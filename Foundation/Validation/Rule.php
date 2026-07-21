<?php

namespace kernel\Foundation\Validation;

/**
 * 校验规则门面类
 *
 * 提供流式 API 定义数据校验规则，支持静态调用和实例调用两种方式。
 *
 * 所有规则定义方法委托给 {@see RuleBuilder} 执行，
 * 通过 {@see __call()} 和 {@see __callStatic()} 实现链式委托。
 * 规则状态（Rule、ErrorMessages 等）通过 {@see __get()} / {@see __set()} 透明代理到构建器。
 *
 * 基本用法：
 * ```php
 * // 静态入口
 * $rules = Rule::required('必填')->type('string')->length(1, 100);
 *
 * // 实例入口
 * $rules = (new Rule())->required('必填')->in(['a', 'b']);
 * ```
 *
 * @property-read array|null  $rule                    校验规则定义
 * @property-read array       $errorMessages           各规则对应的错误信息
 *
 * @method Rule required(string $message = "")          校验值是否为空或为 null
 * @method Rule type(string|array $value, string $message = "") 校验数据类型，支持 int/string/bool/array 等，int 和 bool 会自动转为 integer 和 boolean
 * @method Rule equal(mixed $value, string $message = "")      校验值是否等于指定值（严格比较 ===）
 * @method Rule includes(string|array $value, string $message = "") 校验字符串是否包含子串，或数组是否包含指定元素
 * @method Rule hasKeys(string|array $value, string $message = "")  校验数组是否存在指定键名
 * @method Rule min(int $value, string $message = "")           校验数值是否 >= 指定值
 * @method Rule max(int $value, string $message = "")           校验数值是否 <= 指定值
 * @method Rule range(int $min, int $max, string $message = "") 校验数值是否在 [min, max] 范围内
 * @method Rule minLength(int $value, string $message = "")     校验字符串/数组长度是否 >= 指定值
 * @method Rule maxLength(int $value, string $message = "")     校验字符串/数组长度是否 <= 指定值
 * @method Rule length(int $min, int $max, string $message = "") 校验字符串/数组长度是否在 [min, max] 范围内
 * @method Rule pattern(string $pattern, string $message = "")  正则表达式校验
 * @method Rule custom(\Closure|callable $callback)             自定义校验，回调签名 function(mixed $value, array $rule, mixed $data): \kernel\Foundation\HTTP\Response
 * @method Rule useRule(RuleInterface $validateRule)            复用已有的校验规则实例
 * @method Rule email(string $message = "")                     校验值是否为有效的邮箱地址
 * @method Rule url(string $message = "")                       校验值是否为有效的 URL 地址
 * @method Rule ip(string $message = "")                        校验值是否为有效的 IP 地址（支持 IPv4 和 IPv6）
 * @method Rule date(string $message = "")                      校验值是否为有效的日期字符串
 * @method Rule dateFormat(string $format, string $message = "") 校验值是否符合指定的日期格式
 * @method Rule in(array|string ...$values)                     校验值是否在给定的值列表中，支持 in(['a','b'], 'msg') 或 in('a','b','c')
 * @method Rule notIn(array|string ...$values)                  校验值是否不在给定的值列表中
 * @method Rule confirmed(string $message = "")                 校验值是否与 字段名_confirmation 一致
 * @method Rule same(string $field, string $message = "")       校验值是否与另一个字段的值相同
 * @method Rule different(string $field, string $message = "")  校验值是否与另一个字段的值不同
 * @method Rule nullable(string $message = "")                    允许值为 null，值为 null 时跳过所有校验
 * @method Rule present(string $message = "")                   允许值为空字符串，值为空字符串时跳过所有校验
 * @method Rule prohibited(string $message = "")                校验字段不能存在于输入数据中
 * @method Rule requiredIf(string $anotherField, array|string $values, string $message = "") 当另一字段等于指定值时必填
 * @method Rule requiredUnless(string $anotherField, array|string $values, string $message = "") 除非另一字段等于指定值，否则必填
 *
 * @method static Rule required(string $message = "")          [静态] 校验值是否为空或为 null
 * @method static Rule type(string|array $value, string $message = "") [静态] 校验数据类型
 * @method static Rule equal(mixed $value, string $message = "")      [静态] 校验是否等于指定值
 * @method static Rule includes(string|array $value, string $message = "") [静态] 校验是否包含指定值
 * @method static Rule hasKeys(string|array $value, string $message = "")  [静态] 校验数组是否存在指定键
 * @method static Rule min(int $value, string $message = "")           [静态] 校验数值是否 >= 指定值
 * @method static Rule max(int $value, string $message = "")           [静态] 校验数值是否 <= 指定值
 * @method static Rule range(int $min, int $max, string $message = "") [静态] 校验数值是否在指定范围内
 * @method static Rule minLength(int $value, string $message = "")     [静态] 校验字符串/数组长度 >= 指定值
 * @method static Rule maxLength(int $value, string $message = "")     [静态] 校验字符串/数组长度 <= 指定值
 * @method static Rule length(int $min, int $max, string $message = "") [静态] 校验字符串/数组长度在 [min, max] 范围内
 * @method static Rule pattern(string $pattern, string $message = "")  [静态] 正则表达式校验
 * @method static Rule custom(\Closure|callable $callback)             [静态] 自定义校验
 * @method static Rule useRule(RuleInterface $validateRule)           [静态] 复用已有的校验规则实例
 * @method static Rule email(string $message = "")                    [静态] 校验值是否为有效的邮箱地址
 * @method static Rule url(string $message = "")                      [静态] 校验值是否为有效的 URL 地址
 * @method static Rule ip(string $message = "")                       [静态] 校验值是否为有效的 IP 地址
 * @method static Rule date(string $message = "")                     [静态] 校验值是否为有效的日期字符串
 * @method static Rule dateFormat(string $format, string $message = "") [静态] 校验值是否符合指定的日期格式
 * @method static Rule in(array|string ...$values)                    [静态] 校验值是否在给定的值列表中
 * @method static Rule notIn(array|string ...$values)                 [静态] 校验值是否不在给定的值列表中
 * @method static Rule confirmed(string $message = "")                [静态] 校验值是否与 字段名_confirmation 一致
 * @method static Rule same(string $field, string $message = "")      [静态] 校验值是否与另一个字段的值相同
 * @method static Rule different(string $field, string $message = "") [静态] 校验值是否与另一个字段的值不同
 * @method static Rule nullable(string $message = "")                   [静态] 允许值为 null，值为 null 时跳过所有校验
 * @method static Rule present(string $message = "")                    [静态] 允许值为空字符串，值为空字符串时跳过所有校验
 * @method static Rule prohibited(string $message = "")               [静态] 校验字段不能存在于输入数据中
 * @method static Rule requiredIf(string $anotherField, array|string $values, string $message = "") [静态] 当另一字段等于指定值时必填
 * @method static Rule requiredUnless(string $anotherField, array|string $values, string $message = "") [静态] 除非另一字段等于指定值，否则必填
 *
 * @see RuleBuilder
 * @see RuleInterface
 */
class Rule
{
  /**
   * 规则构建器实例，承载全部规则定义方法
   *
   * @var RuleBuilder
   */
  protected $builder;

  /**
   * 获取构建器实例（延迟初始化）
   *
   * @return RuleBuilder
   */
  protected function builder()
  {
    if (!isset($this->builder)) {
      $this->builder = new RuleBuilder();
    }
    return $this->builder;
  }

  /**
   * 属性读取代理
   *
   * 将属性读取透明转发到内部 {@see RuleBuilder} 实例，
   * 外部可通过 $rules->rule、$rules->errorMessages 等方式访问构建器状态。
   *
   * @param string $name 属性名
   * @return mixed 属性值，不存在时返回 null
   */
  public function __get($name)
  {
    $builder = $this->builder();
    if (property_exists($builder, $name)) {
      return $builder->$name;
    }
    return null;
  }

  /**
   * 属性写入代理
   *
   * 将属性写入透明转发到内部 {@see RuleBuilder} 实例。
   *
   * @param string $name  属性名
   * @param mixed  $value 属性值
   */
  public function __set($name, $value)
  {
    $this->builder()->$name = $value;
  }

  /**
   * 属性存在性检查代理
   *
   * 检查属性是否存在于内部构建器上。
   *
   * @param string $name 属性名
   * @return bool
   */
  public function __isset($name)
  {
    return property_exists($this->builder(), $name);
  }

  /**
   * 静态方法委托 — 支持 Rule::required() 等静态链式调用
   *
   * 创建当前类实例后委托给 {@see __call()} 执行，从而复用同一条委托链路。
   *
   * @param string $name      方法名
   * @param array  $arguments 方法参数
   * @return static
   */
  public static function __callStatic($name, $arguments)
  {
    $instance = new static();
    return $instance->$name(...$arguments);
  }

  /**
   * 实例方法委托 — 将任意方法调用转发给 {@see RuleBuilder} 执行
   *
   * 构建器的链式方法返回自身时，替换为当前 Rule 实例，
   * 确保调用者始终拿到 Rule 而非内部构建器。
   *
   * @param string $name      方法名
   * @param array  $arguments 方法参数
   * @return $this|mixed 链式调用返回 $this，终端方法返回实际结果
   */
  public function __call($name, $arguments)
  {
    $result = $this->builder()->$name(...$arguments);
    if ($result === $this->builder()) {
      return $this;
    }
    return $result;
  }
}
