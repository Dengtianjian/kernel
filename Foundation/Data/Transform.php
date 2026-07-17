<?php

namespace kernel\Foundation\Data;

/**
 * 数据转换器 — 纯函数流转管道
 *
 * 将客户端传入的 _transform 参数解析为标准化的转换器调用链，
 * 通过白名单校验后，依次调用 handler 上的转换器方法（Data In, Data Out）。
 *
 * ## GET 语法
 *   _transform=withGroup:categoryId
 *   _transform=limitFields:id,title,withGroup:author_id
 *
 * ## POST 语法（JSON body）
 *   "_transform": ["withGroup", {"limit": [10]}]
 *
 * ## 转换器方法签名
 *   function name($data, ...$args): mixed
 */
class Transform
{
  /**
   * 解析原始 _transform 输入为标准化格式
   *
   * @param string|array $raw 原始 _transform 值
   * @return array 标准化数组：[{"name": "...", "args": [...]}, ...]
   */
  static function parse($raw): array
  {
    if (empty($raw)) return [];

    if (is_array($raw)) {
      return self::normalize($raw);
    }

    return self::parseString((string)$raw);
  }

  /**
   * 对数据依次执行转换器链（白名单校验 + 纯函数串联）
   *
   * @param array  $transforms 标准化后的转换器列表 [{name, args}]
   * @param array  $whitelist  允许调用的转换器名称白名单
   * @param object $handler    持有转换器方法的对象实例
   * @param mixed  $data       待处理的原始数据
   * @return mixed             处理后的数据
   */
  static function apply(array $transforms, array $whitelist, object $handler, $data)
  {
    // 白名单过滤
    $transforms = array_filter($transforms, function ($t) use ($whitelist) {
      return in_array($t['name'], $whitelist, true);
    });

    // 纯函数串联
    foreach ($transforms as $t) {
      if (method_exists($handler, $t['name'])) {
        $data = $handler->{$t['name']}($data, ...$t['args']);
      }
    }

    return $data;
  }

  // ---- 内部方法 ----

  /**
   * 标准化数组格式的 _transform（POST body）
   */
  private static function normalize(array $raw): array
  {
    $result = [];

    foreach ($raw as $item) {
      if (is_string($item)) {
        // 字符串格式："withGroup" 或 "withGroup:categoryId"
        $parsed = self::parseString($item);
        $result = array_merge($result, $parsed);
      } elseif (is_array($item)) {
        // 对象格式：{"methodName": ["arg1", "arg2"]}
        // key 为方法名，value 为参数数组
        foreach ($item as $methodName => $args) {
          $result[] = [
            'name' => $methodName,
            'args' => $args ?? [],
          ];
        }
      }
    }

    return $result;
  }

  /**
   * 解析 GET _transform 字符串
   *
   * 语法：
   *   methodName[:arg[,arg...]][,methodName[:arg...]]...
   *
   * 规则：
   *   - `:` 标记了一个新 transformer 的开始，后面是第一个参数
   *   - 后续不含 `:` 的 token 作为当前 transformer 的额外参数
   *   - 遇到下一个含 `:` 的 token，则开始一个新的 transformer
   *
   * 示例：
   *   "withGroup:categoryId"           => [{name: "withGroup", args: ["categoryId"]}]
   *   "limitFields:id,title"           => [{name: "limitFields", args: ["id", "title"]}]
   *   "limitFields:id,title,withGroup:author_id"
   *                                    => [{name: "limitFields", args: ["id", "title"]},
   *                                        {name: "withGroup", args: ["author_id"]}]
   */
  private static function parseString(string $input): array
  {
    $input = trim($input);
    if ($input === '') return [];

    $transforms = [];
    $parts = explode(',', $input);
    $current = null;

    foreach ($parts as $part) {
      $part = trim($part);
      if ($part === '') continue;

      $colonPos = strpos($part, ':');
      if ($colonPos !== false) {
        // 含 ':' => 新的 transformer 定义："name:firstArg"
        if ($current !== null) {
          $transforms[] = $current;
        }
        $name = substr($part, 0, $colonPos);
        $arg  = substr($part, $colonPos + 1);
        $current = ['name' => $name, 'args' => $arg !== '' ? [$arg] : []];
      } else {
        // 不含 ':' => 要么是当前 transformer 的参数，要么是新的无参 transformer
        if ($current !== null) {
          $current['args'][] = $part;
        } else {
          $transforms[] = ['name' => $part, 'args' => []];
        }
      }
    }

    if ($current !== null) {
      $transforms[] = $current;
    }

    return $transforms;
  }
}
