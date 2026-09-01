<?php

namespace kernel\Foundation\Object;

use kernel\Foundation\Exception\Error;
use stdClass;

/**
 * 数据对象（Data Object）
 *
 * 一种数据容器，继承自 stdClass。实例化时通过构造参数一次性填充已声明
 * 属性，之后仍允许通过 {@see set()} 或 `->prop = $v` 继续写入数据
 * （含未声明的动态属性），适合需要在生命周期内多次更新字段的场景。
 *
 * 若希望「实例化后完全只读」，请继承 {@see ReadonlyDataObject}（而非本类），
 * 它仅在 __set 上叠加写入拦截，其余读写能力由本类提供。
 *
 * 适用场景：
 * - 把结构化数据（如配置、查询结果行、外部 API 响应）封装成对象
 * - 在多个组件间传递，且允许任意一方更新字段
 * - 需要 `toArray()` / `toJson()` / `get()` / `has()` / `keys()` 等便捷访问
 */
class DataObject extends stdClass
{
  public function __construct($data)
  {
    if (is_object($data)) {
      $data = method_exists($data, 'toArray') ? $data->toArray() : (array) $data;
    }
    foreach ($this->properties() as $key) {
      // 缺键时保留属性默认值，避免 Undefined array key 告警与默认值被覆盖
      if (array_key_exists($key, $data)) {
        $this->$key = $data[$key];
      }
    }
  }

  /**
   * 读取属性
   *
   * 访问不存在的属性时返回 null 而非触发 Undefined property 告警。
   *
   * @param string $name
   * @return mixed
   */
  public function __get($name)
  {
    if (property_exists($this, $name)) {
      return $this->$name;
    }
    return null;
  }

  /**
   * 允许在实例化后写入数据
   *
   * 支持写入「已声明的 protected 属性」以及「未声明的动态属性」。
   * 在类作用域内直接赋值，不会递归触发本方法。
   *
   * @param string $k
   * @param mixed  $v
   */
  public function __set($k, $v)
  {
    $this->$k = $v;
  }

  /**
   * 设置单个或多个属性
   *
   * - `set('name', $value)`：设置单个属性
   * - `set(['name' => $a, 'age' => $b])`：批量设置
   *
   * @param string|array $key   属性名或键值对数组
   * @param mixed        $value 单个属性的值（数组模式忽略）
   * @return $this 返回当前实例以支持链式调用
   */
  public function set($key, $value = null)
  {
    if (is_array($key)) {
      foreach ($key as $k => $v) {
        $this->$k = $v;
      }
    } else {
      $this->$key = $value;
    }

    return $this;
  }

  /**
   * 获取全部属性名（已声明属性 + 已写入的动态属性）
   *
   * @return string[]
   */
  protected function properties()
  {
    $reflect = new \ReflectionClass($this);
    $names = [];
    foreach ($reflect->getProperties() as $prop) {
      if ($prop->isStatic()) {
        continue;
      }
      $names[] = $prop->getName();
    }
    return array_values(array_unique(array_merge(
      $names,
      array_keys(get_object_vars($this))
    )));
  }

  /**
   * 将属性输出为数组格式
   *
   * @return array
   */
  public function toArray()
  {
    $data = [];
    foreach ($this->properties() as $key) {
      $data[$key] = $this->$key;
    }
    return $data;
  }

  /**
   * 是否包含指定属性（已声明且键存在）
   *
   * @param string $key
   * @return bool
   */
  public function has($key)
  {
    return array_key_exists($key, $this->toArray());
  }

  /**
   * 安全读取属性，不存在时返回默认值
   *
   * @param string $key
   * @param mixed  $default
   * @return mixed
   */
  public function get($key, $default = null)
  {
    $data = $this->toArray();
    return array_key_exists($key, $data) ? $data[$key] : $default;
  }

  /**
   * 返回全部属性名
   *
   * @return string[]
   */
  public function keys()
  {
    return $this->properties();
  }

  /**
   * 序列化为 JSON 字符串
   *
   * @param int $flags json_encode 标志位，默认转义 Unicode
   * @return string
   * @throws \RuntimeException 序列化失败时抛出
   */
  public function toJson($flags = JSON_UNESCAPED_UNICODE)
  {
    $json = json_encode($this->toArray(), $flags);
    if ($json === false) {
      throw new \RuntimeException("DataObject::toJson() 序列化失败：" . json_last_error_msg());
    }
    return $json;
  }

  /**
   * 序列化为 JSON 字符串（转字符串时调用）
   *
   * @return string
   */
  public function __toString()
  {
    try {
      return $this->toJson(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (\RuntimeException $e) {
      return '{}';
    }
  }
}
