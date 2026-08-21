<?php

namespace kernel\Foundation\Object;

use kernel\Foundation\Exception\Error;
use stdClass;

/**
 * 数据对象基类
 *
 * 一种「实例化时一次性赋值、之后只读」的数据容器，继承自 stdClass。
 * 子类通过声明 protected 属性来定义数据结构，构造时从传入数组/对象中
 * 取对应键填充；缺失的键保留属性默认值，不覆盖。
 *
 * 典型子类：{@see \kernel\Foundation\FileSystem\Storage\StorageFileInfoData}
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
   * 禁止在实例化后写入数据
   *
   * 仅拦截写入「未声明的动态属性」；构造期对已声明属性的赋值
   * 不会进入此方法，故实例化过程不受影响。
   *
   * @param string $k
   * @param mixed  $v
   * @throws \kernel\Foundation\Exception\Error
   */
  public function __set($k, $v)
  {
    throw new Error("数据对象只允许实例化时设置数据，不能写入未声明属性「{$k}」");
  }

  /**
   * 获取全部属性名（含父类继承的实例属性，排除静态属性）
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
    return array_values(array_unique($names));
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
