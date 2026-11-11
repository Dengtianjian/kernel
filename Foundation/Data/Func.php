<?php

namespace kernel\Foundation\Data;

/**
 * 可调用值工具类
 *
 * 处理「一个值能不能当函数/方法调用」这类判断。全部方法无需实例化即可调用。
 *
 * 方法分为以下几组：
 * - 判定：      isSafeCallable
 *
 * @package kernel\Foundation\Data
 */
class Func
{
  /**
   * 判断值是否为可安全调用的 callable
   *
   * 字符串只在白名单内才算可调用，避免把任意字符串当成函数名。
   *
   * 数组**不能直接交给 is_callable**：它把 `[字符串, 字符串]` 一律当作 `[类名, 方法名]`，
   * 首元素会被送去自动加载。Discuz 的 core::autoload → core::import() 在目标文件不存在时
   * 直接 break() 输出「Oops! System file lost」，于是一句普通的 `where("groupid", ["3", "4"])`
   * 会变成整站 500。这里只认两元结构 `[对象|类名, 方法名]`，且类名只在**已加载**时认可
   * （`class_exists($name, false)` 不触发自动加载）。
   *
   * @param mixed $value 待判断的值
   * @param array $whitelist 允许的字符串函数名白名单
   * @return bool
   */
  static function isSafeCallable($value, array $whitelist = []): bool
  {
    // 字符串类型：必须在白名单中才算安全
    if (is_string($value)) {
      return !empty($whitelist) && in_array($value, $whitelist, true) && is_callable($value);
    }

    // 数组：只有 [对象|类名, 方法名] 这种两元结构才可能是可调用数组
    if (is_array($value)) {
      if (count($value) !== 2) {
        return false;
      }
      list($target, $method) = array_values($value);
      if (!is_string($method)) {
        return false;
      }
      if (is_object($target)) {
        return is_callable([$target, $method]);
      }

      return is_string($target)
        && $target !== ""
        && class_exists($target, false)
        && is_callable([$target, $method]);
    }

    // 闭包、可调用对象
    return is_callable($value);
  }
}
