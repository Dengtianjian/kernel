<?php

namespace kernel\Foundation\Data;


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
    usort($arr, function ($a, $b) {
      if ($a['parentId'] && $b['parentId'])
        return 0;
      if ($a['parentId'])
        return 1;
      if ($b['parentId'])
        return -1;

      return 0;
    });

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
   * 过滤空值 并且 移除数组中重复的值
   * @param array $Target 操作的数组
   * @return array 过滤后的数组
   */
  static function filterNullUnique($Target)
  {
    return array_unique(array_filter($Target, function ($item) {
      return !is_null($item) && !empty($item);
    }));
  }
  /**
   * 通过点号语法判断多维数组中是否存在指定的键
   *
   * 支持点号分隔的路径（如 user.profile.name）。
   * 与 {@see get()} 不同，本方法严格区分「键存在值为 null」和「键不存在」。
   *
   * @param array|\ArrayAccess $array 目标数组
   * @param string|null        $key   键名，支持点号语法
   * @return bool 键是否存在
   */
  static function has($array, $key)
  {
    if (!is_array($array) && !$array instanceof \ArrayAccess) {
      return false;
    }

    if (is_null($key)) {
      return false;
    }

    // 不含点号 → 直接检查
    if (!str_contains($key, '.')) {
      if (is_array($array)) {
        return array_key_exists($key, $array);
      }
      if ($array instanceof \ArrayAccess) {
        return $array->offsetExists($key);
      }
      return false;
    }

    // 含点号 → 逐段下探
    $segments = explode('.', $key);
    foreach ($segments as $segment) {
      if (is_array($array) && array_key_exists($segment, $array)) {
        $array = $array[$segment];
      } elseif ($array instanceof \ArrayAccess && $array->offsetExists($segment)) {
        $array = $array[$segment];
      } else {
        return false;
      }
    }

    return true;
  }
  /**
   * 通过点号语法获取多维数组的值
   *
   * 支持点号分隔的路径（如 user.profile.name）和通配符 *（如 photos.*.url）。
   * 通配符匹配时返回平铺的结果数组。
   *
   * @param array|\ArrayAccess $array 目标数组
   * @param string|null        $key    键名，支持点号语法和 * 通配符
   * @param mixed              $default 键不存在时的默认值
   * @return mixed 获取到的值，或通配符匹配的结果数组
   */
  static function get($array, $key, $default = null)
  {
    if (!is_array($array) && !$array instanceof \ArrayAccess) {
      return $default;
    }

    if (is_null($key)) {
      return $array;
    }

    // 直接键访问
    if (array_key_exists($key, $array)) {
      return $array[$key];
    }

    // 不含点号和通配符，尝试直接访问
    if (!str_contains($key, '.') && !str_contains($key, '*')) {
      return $array[$key] ?? $default;
    }

    // 含通配符 → 展开匹配
    if (str_contains($key, '*')) {
      return self::wildcardGet($array, $key, $default);
    }

    // 仅含点号 → 按路径逐层深入
    $segments = explode('.', $key);
    foreach ($segments as $segment) {
      if (is_array($array) && array_key_exists($segment, $array)) {
        $array = $array[$segment];
      } elseif ($array instanceof \ArrayAccess && $array->offsetExists($segment)) {
        $array = $array[$segment];
      } else {
        return $default;
      }
    }

    return $array;
  }
  /**
   * 通配符展开取值：将 photos.*.url 展开为数组中每个元素对应键的值
   *
   * @param array  $array   目标数组
   * @param string $key     含 * 的键路径
   * @param mixed  $default 默认值
   * @return array 展开后的值数组
   */
  private static function wildcardGet($array, $key, $default = null)
  {
    $segments = explode('.', $key);
    $results = [];
    self::wildcardWalk($array, $segments, '', $results);
    return !empty($results) ? $results : $default;
  }
  /**
   * 递归遍历通配符路径
   *
   * @param mixed  $data        当前层级数据
   * @param array  $segments    剩余路径段
   * @param string $currentPath 已走过的路径（用于调试/错误定位）
   * @param array  &$results    结果集
   */
  private static function wildcardWalk($data, $segments, $currentPath, &$results)
  {
    if (empty($segments)) {
      $results[] = $data;
      return;
    }

    $segment = array_shift($segments);

    if ($segment === '*') {
      if (!is_array($data)) {
        return;
      }
      foreach ($data as $i => $item) {
        self::wildcardWalk($item, $segments, $currentPath . '.' . $i, $results);
      }
    } else {
      if (is_array($data) && array_key_exists($segment, $data)) {
        self::wildcardWalk($data[$segment], $segments, $currentPath . '.' . $segment, $results);
      } elseif ($data instanceof \ArrayAccess && $data->offsetExists($segment)) {
        self::wildcardWalk($data[$segment], $segments, $currentPath . '.' . $segment, $results);
      }
    }
  }
  /**
   * 通过点号语法移除多维数组中的键
   *
   * 支持点号分隔的路径（如 user.profile.name）。键不存在时静默忽略。
   * 若 `$array` 为引用传递的对象（\ArrayAccess），直接对对象删除。
   *
   * @param array|\ArrayAccess $array 目标数组（对象传引用，数组需手动接收返回值）
   * @param string|null        $key   键名，支持点号语法
   * @return array 移除后的数组（数组入参时可作为返回值使用）
   */
  static function forget(&$array, $key)
  {
    if (is_null($key) || $key === "") {
      return $array;
    }

    // 不含点号 → 直接删除
    if (!str_contains($key, '.')) {
      if (is_array($array)) {
        unset($array[$key]);
      } elseif ($array instanceof \ArrayAccess) {
        $array->offsetUnset($key);
      }
      return $array;
    }

    $segments = explode('.', $key);
    $last = array_pop($segments);
    $node = &$array;

    foreach ($segments as $segment) {
      if (is_array($node) && array_key_exists($segment, $node)) {
        $node = &$node[$segment];
      } elseif ($node instanceof \ArrayAccess && $node->offsetExists($segment)) {
        $item = $node[$segment];
        $node = &$item;
      } else {
        return $array; // 路径不存在，直接返回
      }
    }

    if (is_array($node)) {
      unset($node[$last]);
    } elseif ($node instanceof \ArrayAccess) {
      $node->offsetUnset($last);
    }

    return $array;
  }
}
