<?php

namespace kernel\Foundation\Object;

use kernel\Foundation\Exception\Error;

/**
 * 只读数据对象（Readonly Data Object）
 *
 * {@see DataObject} 的只读变体：继承 {@see DataObject} 的全部读写与序列化
 * 能力，但重写 `__set` 以禁止实例化后的任何写入（含未声明的动态属性），
 * 从而保证数据在传递过程中的不可变性。
 *
 * 适用场景：
 * - 把结构化数据（如配置、查询结果行、外部 API 响应）封装成对象
 * - 在多个组件间传递，但任意一方都不应意外修改内容
 *
 * 如果需要「实例化后仍可写入」的变体，请直接继承 {@see DataObject}。
 */
class ReadonlyDataObject extends DataObject
{
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
}
