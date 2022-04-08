<?php

namespace kernel\Foundation;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Str;

/**
 * 伪静态类
 * 替换参数、加入页面链接到全局global等
 */
class PseudoStatic
{
  static $rules = [];
  static function add($rules)
  {
    self::$rules = \array_merge(self::$rules, $rules);
  }
  static function replace($alias, $params = [])
  {
    $alias = explode("/", $alias);
    $value = self::$rules;
    foreach ($alias as $aliasItem) {
      $value = $value[$aliasItem];
    }
    return Str::replaceParams($value, $params);
  }
  static function match($alias, $params = [])
  {
    $result = self::replace($alias, $params);
    $alias = explode("/", $alias);
    $mergeData = [];
    $previous = "";
    $aliasCount = count($alias);
    foreach ($alias as $index => $item) {
      if ($previous) {
        $mergeData[$previous][$item] = [];
        if ($index == $aliasCount - 1) {
          $mergeData[$previous][$item] = $result;
        }
      } else {
        $mergeData[$item] = [];
        if ($index == $aliasCount - 1) {
          $mergeData[$item] = $result;
        }
      }
      $previous = $item;
    }


    $urls = Arr::merge(Store::getApp("rewriteURL"), $mergeData);
    Store::setApp([
      "rewriteURL" => $urls
    ]);
  }
}
