<?php

namespace gstudio_kernel\Foundation\Data;

use gstudio_kernel\Foundation\Output;

if (!defined("IN_DISCUZ")) {
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
   * 分级
   *
   * @param array $arr 原数组
   * @param string $dataPrimaryKey 主键，也是父子都有的一个唯一值
   * @param string $relatedParentKey 关联键名，用于关联父子
   * @param string $childArrayKeys = childs 子级保存在指定的键值下的数组名称
   * @return array 分级后的数组
   */
  static function tree($arr, $dataPrimaryKey, $relatedParentKey, $childArrayKeys = "childs")
  {
    $arr = self::indexToAssoc($arr, $dataPrimaryKey);
    $result = [];
    foreach ($arr as &$arrItem) {
      if (!$arrItem[$relatedParentKey]) { //* 最高级
        if (!isset($result[$arrItem[$dataPrimaryKey]])) { //* 判断结果数组里是否存在，没有就加进去
          $result[$arrItem[$dataPrimaryKey]] = $arrItem;
          $arrItem['reference'] = &$result[$arrItem[$dataPrimaryKey]];
          $arrItem['reference'][$childArrayKeys] = [];
        }
      } else { //* 下级
        if ($arr[$arrItem[$relatedParentKey]]['reference']) {
          $arr[$arrItem[$relatedParentKey]]['reference'][$childArrayKeys][$arrItem[$dataPrimaryKey]] = $arrItem;
          $arrItem['reference'] = &$arr[$arrItem[$relatedParentKey]]['reference'][$childArrayKeys][$arrItem[$dataPrimaryKey]];
        }
        $arr[$arrItem[$relatedParentKey]]['reference'][$childArrayKeys] = array_values($arr[$arrItem[$relatedParentKey]]['reference'][$childArrayKeys]);
      }
    }
    return array_values($result);
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
  static function partial($target,  $keys)
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
  static function group($target,  $byKey)
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
}
