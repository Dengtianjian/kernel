<?php

namespace gstudio_kernel\Foundation\Database;

use gstudio_kernel\Foundation\Output;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class SQL
{
  /**
   * 优化字符串
   *
   * @param array $strings 字符串数组
   * @param string $quote 添加的引号
   * @param boolean $addQuote 是否跳过添加引号
   * @return string[] 优化后的字符串
   */
  static function addQuote($strings,  $quote = "`", $addQuote = true)
  {
    foreach ($strings as &$item) {
      if ($item === null) {
        continue;
      }
      if (\is_bool($item)) {
        $item = $item ? 1 : 0;
      }
      if ($addQuote) {
        $item = $quote . $item . $quote;
      }
    }
    return $strings;
  }
  static function condition($field, $value, $glue = "=", $operator = null)
  {
    $sql = self::field($field, $value, \strtolower($glue));
    if ($operator) {
      $sql .= " $operator ";
    }
    return $sql;
  }
  static function conditions($params)
  {
    $sql = "WHERE ";
    $lastIndex = count($params) - 1;
    // DONE 重写conditions方法或者去掉，只处理[field,value,glue,operator,statenment]格式，转为where语句
    foreach ($params as $itemIndex => $paramItem) {
      if ($paramItem['statement']) {
        $sql .= $paramItem['statement'];
      } else {
        $sql .= SQL::condition($paramItem['fieldName'], $paramItem['value'], $paramItem['glue'], $itemIndex === $lastIndex ? null : $paramItem['operator']);
      }
    }
    return $sql;
  }
  static function order($orders)
  {
    if (empty($orders)) {
      return "";
    }
    foreach ($orders as &$orderItem) {
      if (!$orderItem['field']) {
        continue;
      }
      $by = $orderItem['by'] ? $orderItem['by'] : 'ASC';
      $orderItem = "`" . $orderItem['field'] . "` " . $by;
    }
    $order = "ORDER BY " . \implode(", ", $orders);
    return $order;
  }
  static function field($fieldName, $value, $glue = "=")
  {
    $glue = strtolower($glue);
    $fieldName = self::addQuote([$fieldName])[0];
    $addQuote = true;

    if ($value === null) {
      $addQuote = false;
      switch ($glue) {
        case "!=":
          $value = "IS NOT NULL";
          break;
        case "=":
          $value = "IS NULL";
          break;
      }
      $glue = null;
    }
    if (is_numeric($value) && !is_string($value)) {
      $addQuote = false;
    }

    if ($addQuote && !is_array($value)) {
      $value = self::addQuote([$value], "'")[0];
    }

    if (is_array($value)) {
      $glue = $glue == 'notin' ? 'notin' : 'in';
    } elseif ($glue == 'in') {
      $glue = '=';
    }

    switch ($glue) {
      case '-':
      case '+':
        return $fieldName . '=' . $fieldName . $glue . $value;
        break;
      case '|':
      case '&':
      case '^':
      case '&~':
        return $fieldName . '=' . $fieldName . $glue . $value;
        break;
      case '>':
      case '<':
      case '<>':
      case '<=':
      case '>=':
        return $fieldName . $glue . $value;
        break;
      case 'like':
        return $fieldName . ' LIKE(' . $value . ')';
        break;
      case 'in':
      case 'notin':
        $value = self::addQuote(array_values($value), "'");
        $val = $value ? implode(',', $value) : '\'\'';
        return $fieldName . ($glue == 'notin' ? ' NOT' : '') . ' IN(' . $val . ')';
        break;
      case '=':
      default:
        return "$fieldName $glue $value";
        break;
    }
  }
  static function page($page)
  {
    if (!$page['limit'] || empty($page)) {
      return "";
    }
    if ($page['limit']) {
      $pageString = "LIMIT " . $page['limit'];
      if ($page['offset']) {
        $pageString .= " OFFSET " . $page['offset'];
      }
    }
    return $pageString;
  }
  static function limit($startOrNumbers, $numbers = null)
  {
    $sql = "LIMIT ";
    if ($numbers) {
      $sql .= "$startOrNumbers,$numbers";
    } else {
      $sql .= "$startOrNumbers";
    }
    return $sql;
  }
  static function insert($tableName, $data, $isReplaceInto = false)
  {
    $fields = \array_keys($data);
    $fields = self::addQuote($fields);
    $fields = \implode(",", $fields);
    $values = array_values($data);
    $values = self::addQuote($values, "'", true);
    $values = \implode(",", $values);

    $startSql = "INSERT INTO";
    if ($isReplaceInto) {
      $startSql = "REPLACE INTO";
    }
    return "$startSql `$tableName`($fields) VALUES($values);";
  }
  static function batchInsert($tableName, $fields, $datas, $isReplaceInto = false)
  {
    $fields = self::addQuote($fields);
    $fields = \implode(",", $fields);
    $valueSql = [];
    foreach ($datas as $dataItem) {
      $dataItem = self::addQuote($dataItem, "'", true);
      $valueSql[] = "(" . \implode(",", $dataItem) . ")";
    }
    $valueSql = \implode(",", $valueSql);
    $startSql = "INSERT INTO";
    if ($isReplaceInto) {
      $startSql = "REPLACE INTO";
    }
    return "$startSql `$tableName`($fields) VALUES$valueSql";
  }
  static function delete($tableName, $condition)
  {
    return "DELETE FROM `$tableName` $condition";
  }
  static function update($tableName, $data, $extraStatement = "")
  {
    $data = self::addQuote($data, "'", true);
    foreach ($data as $field => &$value) {
      if ($value === null) $value = "null";
      $value = "`$field` = $value";
    }
    $data = implode(",", $data);
    $sql = "UPDATE `$tableName` SET $data $extraStatement";
    return $sql;
  }
  // BUG 批量更新不应该走batchInsert的replace，应该是多条update
  static function batchUpdate($tableName,  $fields,  $datas,  $extraStatement = "")
  {
    $sql = self::batchInsert($tableName, $fields, $datas, true);
    $sql .= " $extraStatement";
    return $sql;
  }
  static function select($tableName, $fields = "*", $extraStatement = "")
  {
    if (is_array($fields)) {
      $fields = self::addQuote($fields, "`");
      $fields = implode(",", $fields);
    } else if ($fields === null) {
      $fields = "*";
    }
    return "SELECT $fields FROM `$tableName` $extraStatement";
  }
  static function count($tableName, $field = "*", $extraStatement = "")
  {
    return "SELECT COUNT('$field') FROM `$tableName` $extraStatement";
  }
  static function exist($tableName, $extraStatement = "")
  {
    return "SELECT 1 FROM `$tableName` $extraStatement";
  }
}
