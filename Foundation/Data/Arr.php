<?php

namespace kernel\Foundation\Data;

use control;
use kernel\Foundation\Output;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Arr
{
  /**
   * 判断不是关联数组
   *
   * @param array $array 原数组
   * @return boolean
   */
  static function isAssoc($array)
  {
    if (is_array($array)) {
      return array_keys($array) !== range(0, count($array) - 1);
    }
    return false;
  }
  /**
   * 索引数组转关联数组
   * @param array $array 原数组 索引数组
   * @param string $key 键名
   * @return array
   */
  static function indexToAssoc($array, $key)
  {
    $result = [];
    foreach ($array as $item) {
      $result[$item[$key]] = $item;
    }
    return $result;
  }
  /**
   * 将一维数组转换为树形多维数组
   *
   * @param array $arr 一维数组，每个元素包含 $dataPrimaryKey（唯一标识）和 $relatedParentKey（父级标识）
   * @param string $dataPrimaryKey 主键，节点的唯一标识字段名
   * @param string $relatedParentKey 关联父级键名，值为 0/null 表示顶级节点
   * @param string $childArrayKeys 子级数组的键名，默认为 childs
   * @return array 树形结构数组
   */
  static function tree($arr, $dataPrimaryKey, $relatedParentKey, $childArrayKeys = "childs")
  {
    if (!$arr) {
      return [];
    }

    // 第一阶段：建立索引映射表，通过主键直接定位节点
    $nodes = [];
    foreach ($arr as $item) {
      $item[$childArrayKeys] = [];
      $nodes[$item[$dataPrimaryKey]] = $item;
    }

    // 第二阶段：组装父子关系
    $roots = [];
    foreach ($nodes as $id => &$node) {
      $parentId = $node[$relatedParentKey] ?? 0;
      // 值为 0/null/空字符串 表示顶级节点
      if (!$parentId || !isset($nodes[$parentId])) {
        $roots[] = &$node;
      } else {
        $nodes[$parentId][$childArrayKeys][] = &$node;
      }
    }

    return $roots;
  }
  /**
   * 合并数组。支持多维数组合并
   *
   * @param array ...$arrs 要合并的数组
   * @return array 合并完后的数组
   */
  static function merge(...$arrs)
  {
    $merged = [];
    while ($arrs) {
      $array = array_shift($arrs);
      if (!$array) {
        continue;
      }
      if (!is_array($array))
        continue;
      foreach ($array as $key => $value) {
        if (is_string($key)) {
          if (
            is_array($value) && array_key_exists($key, $merged)
            && is_array($merged[$key])
          ) {
            $merged[$key] = self::merge(...[$merged[$key], $value]);
          } else {
            $merged[$key] = $value;
          }
        } else {
          $merged[] = $value;
        }
      }
    }

    return $merged;
  }
  /**
   * 分隔字符串转换成多级数组
   *
   * @param string $string 字符串
   * @param string $separator 用于分割字符串的字符。默认是 /
   * @return void
   */
  static function stringToMultiLevelArray($string, $separator = "/")
  {
    $strings = explode($separator, $string);
    $result = [];
    $previous = NULL;
    foreach ($strings as $stringItem) {
      if (\is_array($previous)) {
        $previous[$stringItem] = [];
        $previous = &$previous[$stringItem];
      } else {
        $result[$stringItem] = [];
        $previous = &$result[$stringItem];
      }
    }
    unset($previous);
    return $result;
  }
  /**
   * 从数组中抽取指定字段的值
   *
   * @param array $target 目标数组
   * @param array $keys 要抽取的key值
   * @return array
   */
  static function partial($target, $keys)
  {
    $result = [];
    foreach ($keys as $key) {
      if (isset($target[$key])) {
        $result[$key] = $target[$key];
      }
    }
    return $result;
  }
  /**
   * 根据指定的key分组
   *
   * @param array $target 目标数组。需要时二维数组，每个二维数组里面都有一个共同的key
   * @param string $byKey 每个数组共同的key，就是根据这个key来分组
   * @return array
   */
  static function group($target, $byKey)
  {
    $result = [];
    foreach ($target as $item) {
      if (!isset($item[$byKey])) {
        continue;
      }
      if (!isset($result[$item[$byKey]])) {
        $result[$item[$byKey]] = [];
      }
      array_push($result[$item[$byKey]], $item);
    }
    return $result;
  }
  /**
   * 数组转换为XML字符串
   *
   * @param array $target 目标数组
   * @param boolean $root 是否需要根标签
   * @return string
   */
  static function toXML($target, $root = true, $rootName = "xml")
  {
    $res = "";
    if ($root) {
      $res .= "<$rootName>";
    }

    if (is_array($target)) {
      foreach ($target as $key => $value) {
        if (is_string($value)) {
          $res .= "<$key><![CDATA[$value]]></$key>";
        } else if (is_array($value)) {
          if (self::isAssoc($value)) {
            $res .= "<$key>" . self::toXML($value, false) . "</$key>";
          } else {
            $itemStr = "";
            foreach ($value as $item) {
              $itemStr .= "<$key>";
              $itemStr .= self::toXML($item, false);
              $itemStr .= "</$key>";
            }
            $res .= $itemStr;
          }
        } else {
          $res .= "<$key>$value</$key>";
        }
      }
    } else {
      $res .= $target;
    }

    if ($root) {
      $res .= "</$rootName>";
    }

    return $res;
  }
  /**
   * 提取数组指定列并去重、过滤空值
   *
   * 从二维数组中提取指定键名的列，去除重复值，并过滤掉空元素（如 null, '', false 等）。
   *
   * @param array $data 需要处理的二维数组
   * @param string $key 需要提取的列名（键名）
   * @return array 返回处理后的去重且不含空值的一维数组
   */
  static function extractUniqueValues($data, $key)
  {
    return array_filter(array_unique(array_column($data, $key)), function ($item) {
      return $item;
    });
  }
}
