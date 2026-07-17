<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Output;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

/**
 * SQL 语句生成器
 */
class Statement
{
  /**
   * 比较运算符
   * @var array
   */
  public $comparisonOperators = [
    "=",
    "<>",
    "!=",
    ">",
    "<",
    "<=",
    ">=",
    "<=>", //* 安全等于（即使与NULL比较也会返回TRUE或FALSE，不会返回UNKNOWN）
  ];
  /**
   * 运算符
   * @var array
   */
  public $operators = [
    "IS NULL",
    "NOT IS NULL",
    "BETWEEN",
    "NOT BETWEEN",
    "IN",
    "NOT IN",
    "LIKE",
    "NOT LIKE"
  ];
  /**
   * 基础 SQL
   * @var string
   */
  protected $baseSQL = null;
  /**
   * 实例化 Statement 类
   * @param string $baseSQL 基础 SQL
   */
  function __construct($baseSQL = null)
  {
    $this->baseSQL = $baseSQL;
  }
  /**
   * 获取 SQL
   * @return string|null
   */
  function getSQL()
  {
    return $this->baseSQL;
  }
  /**
   * 格式化数据
   * @param mixed $target 格式化的数据
   * @param string $stringQuote 字符串包围的符号；如果数据是字符串类型，会被该参数的值左右包围，传入 `false` 就不包围；例如 admin ，传入了 "`"符号，输出的数据就是 `admin`
   * @param 'json'|'serialize' $arrayFormatMethod 数组格式化的方法，json 就会被 json_encode 编码为字符串，serialize 就会被 serialize 函数格式化为字符串
   * @return string
   */
  static function format($target, $stringQuote = "`", $arrayFormatMethod = "json")
  {
    if (is_bool($target)) {
      $target = $target === true ? 1 : 0;
    } else if (is_string($target)) {
      $target = join("", [$stringQuote, $target, $stringQuote]);
    } else if (is_array($target)) {
      if ($arrayFormatMethod === 'json') {
        $target = json_encode($target);
      } else {
        $target = serialize($target);
      }
    } else if ($target instanceof Query) {
      $target = "(" . $target->getSQL() . ")";
    } else if ($target instanceof Statement) {
      $target = $target->getSQL();
    } else if (is_null($target)) {
      $target = "NULL";
    }

    return $target;
  }
  /**
   * 批量格式化
   * 遍历数组，每个元素都调用 `format`
   * @param array $target 被格式化的目标数组
   * @param string $stringQuote 字符串包围的符号；如果数据是字符串类型，会被该参数的值左右包围，传入 `false` 就不包围；例如 admin ，传入了 "`"符号，输出的数据就是 `admin`
   * @param 'json'|'serialize' $arrayFormatMethod 数组格式化的方法，json 就会被 json_encode 编码为字符串，serialize 就会被 serialize 函数格式化为字符串
   * @return array
   */
  static function batchFormat($target, $stringQuote = "`", $arrayFormatMethod = "json")
  {
    return array_map(function ($item) use ($stringQuote, $arrayFormatMethod) {
      return self::format($item, $stringQuote, $arrayFormatMethod);
    }, $target);
  }
  static function from($from, $asName)
  {
    if (is_string($from) && !preg_match("/\sas\s/i", $from)) {
      if (preg_match("/\s/", $from)) {
        $name = explode(" ", $from);
        $from = $name[0];
        $asName = $name[1];
      }

      if (preg_match("/\./", $from)) {
        $name = explode(".", $from);
        $databaseName = Statement::format($name[0]);
        $from = Statement::format($name[1]);
        $from = "{$databaseName}.{$from}";
      } else {
        $from = Statement::format($from);
      }
    } else if (is_callable($from)) {
      $subQuery = new Query();
      $from($subQuery);
      $from = "({$subQuery->getSQL()})";
    } else if ($from instanceof Query) {
      $subQuery = $from->getSQL();
      $from = "($subQuery)";
    }

    if ($asName && !preg_match("/\sas\s/i", $from)) {
      $asName = self::format($asName);
      $from = "{$from} AS {$asName}";
    }

    return $from;
  }
  static function where($conditions)
  {
    // debug($conditions);
    $conditionSQLs = [];

    array_reduce($conditions, function ($PrevConditionItem, $ConditionItem) use (&$conditionSQLs) {
      if ($ConditionItem['type'] === "boolean") {
        if ($PrevConditionItem && !$PrevConditionItem['boolean'] && $PrevConditionItem['type'] !== 'boolean')
          $conditionSQLs[] = $ConditionItem['boolean'];
      } else {
        if ($PrevConditionItem) {
          $boolean = $ConditionItem['boolean'] ?: "AND";

          if ($PrevConditionItem['type'] === "raw" && !preg_match("/\s(or|and)\s?$/i", $PrevConditionItem['value'])) {
            $conditionSQLs[] = $boolean;
          } else {
            $conditionSQLs[] = $boolean;
          }
        }

        if ($ConditionItem['column']) {
          $ConditionItem['column'] = self::format($ConditionItem['column']);
        }

        $statement = "";

        switch ($ConditionItem['type']) {
          //* 纯SQL
          case "raw":
            $statement = $ConditionItem['value'];
            break;
          //* 比较运算符
          case "comparsion":
            $column = $ConditionItem['column'];
            if ($column instanceof Statement) {
              $column = $column->getSQL();
            }

            $value = $ConditionItem['value'];

            if (is_callable($value) || $value instanceof Query) {
              if (is_callable($value)) {
                $subQuery = new Query();
                $value($subQuery);
                $value = $subQuery->getSQL();
              } else {
                $value = $value->getSQL();
              }
              $value = "({$value})";
            } else if (is_array($value)) {
              $ConditionItem['operator'] = "IN";
              $value = "(" . join(", ", self::batchFormat($value, "'")) . ")";
            } else {
              $value = self::format($value, "'");
            }

            // debug($ConditionItem);
            $statement = join(
              " ",
              [
                $column,
                $ConditionItem['operator'],
                $value
              ]
            );
            break;
          //* 列比较
          case "columnComparsion":
            $statement = join(" ", [$ConditionItem['column'], $ConditionItem['operator'], self::format($ConditionItem['value'])]);
            break;
          //* 子查询
          case "sub":
            $value = $ConditionItem['value'];
            if (is_callable($value)) {
              $subQuery = new Query();
              $value($subQuery);
              $value = $subQuery->getSQL();
            } else if ($value instanceof Query) {
              $value = $value->getSQL();
            }
            $statement = "({$value})";
            break;
          //* NULL 值
          case "nullValue":
            if ($ConditionItem['operator'] === "<=>") {
              $ConditionItem['operator'] = "IS NULL";
            }

            $params = [
              $ConditionItem['column'],
              $ConditionItem['operator']
            ];

            $statement = join(" ", $params);
            break;
          //* 范围
          case "rangeTesting":
            switch ($ConditionItem['operator']) {
              case "BETWEEN":
              case "NOT BETWEEN":
                $statement = join(" ", [
                  $ConditionItem['operator'],
                  join(" AND ", $ConditionItem['value'])
                ]);
                break;
              case "IN":
              case "NOT IN":
                $value = $ConditionItem['value'];
                if (is_array($value)) {
                  $value = join(", ", self::batchFormat($ConditionItem['value'], "'"));
                } else if (is_callable($value)) {
                  $subQuery = new Query();
                  $value($subQuery);
                  $value = $subQuery->getSQL();
                } else if ($value instanceof Query) {
                  $value = $value->getSQL();
                } else {
                  $value = self::format($ConditionItem['value'], "'");
                }
                $statement = join(" ", [
                  $ConditionItem['column'],
                  $ConditionItem['operator'],
                  "({$value})"
                ]);
                break;
            }
            break;
          //* 模式匹配
          case "patternMatching":
            switch ($ConditionItem['operator']) {
              case "LIKE":
              case "NOT LIKE":
                $statement = join(" ", [
                  $ConditionItem['column'],
                  $ConditionItem['operator'],
                  self::format($ConditionItem['value'], "'")
                ]);
                break;
            }
            break;
          //* 函数
          case "func":
            if (in_array($ConditionItem['funcName'], ["DATE", "YEAR", "MONTH", "DAY", "TUNE", "HOUR", "MINUTE", "SECOND"])) {
              $statement = join(" ", [$ConditionItem['column'], $ConditionItem['operator'], self::format($ConditionItem['value'])]);
            } else if (in_array($ConditionItem['funcName'], ["EXISTS", "NOT EXISTS"])) {
              $value = $ConditionItem['value'];
              if (is_callable($value)) {
                $subQuery = new Query();
                $value($subQuery);
                $value = $subQuery->getSQL();
              } else if ($value instanceof Query) {
                $value = $value->getSQL();
              }

              $statement = "{$ConditionItem['funcName']}({$value})";
            }

            break;
        }

        // debug($ConditionItem);
        $statement && $conditionSQLs[] = trim($statement);
      }

      return $ConditionItem;
    });

    return implode(" ", $conditionSQLs);
  }
  /**
   * 排序 SQL 生成s
   * @param array $orders 排序规则
   * @return string
   */
  static function order($orders)
  {
    if (!$orders) {
      return "";
    }

    $OrderSQLs = [];
    foreach ($orders as $orderItem) {
      if ($orderItem['type'] === 'general') {
        if ($orderItem['field'] instanceof Statement) {
          $OrderSQLs[] = $orderItem['field']->getSQL();
        } else {
          $field = is_int($orderItem['field']) ? $orderItem['field'] : "`{$orderItem['field']}`";
          $by = $orderItem['by'] ? strtoupper($orderItem['by']) : 'ASC';
          $OrderSQLs[] = join(" ", [$field, $by]);
        }
      } else if ($orderItem['type'] === 'raw') {
        $OrderSQLs[] = $orderItem['field'];
      } else if ($orderItem['type'] === 'random') {
        if (is_null($orderItem['by'])) {
          $OrderSQLs[] = "RAND()";
        } else {
          $orderItem['by'] = (int) $orderItem['by'];
          $OrderSQLs[] = "RAND({$orderItem['by']})";
        }
      }
    }

    if (!$OrderSQLs) {
      return "";
    }

    return "ORDER BY " . \implode(", ", $OrderSQLs);
  }
  /**
   * 限制操作的条数
   * @param int $limit 偏移值或者获取的条数
   * @param int $offset 获取的条数
   * @return string
   */
  static function pagination($limit = null, $offset = null)
  {
    if (!is_null($limit) && $limit instanceof Statement) {
      $limit = $limit->getSQL();
    }
    if (!is_null($offset) && $offset instanceof Statement) {
      $offset = $offset->getSQL();
    }

    $sql = "";
    if ($limit && $offset) {
      $sql = "LIMIT {$limit} OFFSET {$offset}";
    } else if ($offset) {
      $sql = "OFFSET {$offset}";
    } else {
      $sql = "LIMIT {$limit}";
    }

    return $sql;
  }
  /**
   * 生成插入 SQL
   * 支持单行和批量插入，自动检测数据格式：
   * - 单行：['col1' => 'val1', 'col2' => 'val2']
   * - 批量：[['col1' => 'val1'], ['col1' => 'val2']]
   * @param string $tableName 表名
   * @param array $data 插入数据
   * @param bool $isReplaceInto 是否使用 REPLACE INTO
   * @param bool $isIgnore 是否使用 INSERT IGNORE
   * @return string
   */
  static function insert($tableName, $data, $isReplaceInto = false, $isIgnore = false)
  {
    // 归一化：单行也包装为行列表
    $isAssoc = Arr::isAssoc($data);
    $rows = $isAssoc ? [$data] : $data;

    $startSql = $isReplaceInto
      ? "REPLACE INTO"
      : "INSERT" . ($isIgnore ? " IGNORE" : "") . " INTO";

    // 字段列表：从第一行取键名
    $fieldSQL = "";
    if (Arr::isAssoc($rows[0])) {
      $fieldSQL = "(" . \implode(",", self::batchFormat(\array_keys($rows[0]))) . ")";
    }

    // 值列表：batchFormat 内部 format 会处理 Statement/Query 实例（raw、子查询），不会加引号
    $valueSQLs = [];
    $valueSQL = "";
    if ($isAssoc || is_array($data[0])) {
      foreach ($rows as $row) {
        $valueSQLs[] = "(" . \implode(",", self::batchFormat($row, "'", 'json')) . ")";
      }
      $valueSQL = \implode(",", $valueSQLs);
    } else {
      $valueSQL = "(" . \implode(",", self::batchFormat($data, "'", 'json')) . ")";
    }

    return "$startSql $tableName$fieldSQL VALUES $valueSQL;";
  }
  static function update($tableName, $data, $extraStatement = "")
  {
    $updateData = self::batchFormat($data, "'", true);
    foreach ($updateData as $field => &$value) {
      if ($value === null)
        $value = "NULL";
      $value = "`$field` = $value";
    }
    $updateData = implode(",", $updateData);
    $sql = "UPDATE $tableName SET {$updateData} $extraStatement";
    return $sql;
  }
  static function batchUpdate($tableName, $fields, $datas, $extraStatement = "")
  {
    $updateData = [];
    foreach ($datas as $item) {
      foreach ($item as &$value) {
        if (is_null($value)) {
          $value = 'NULL';
        }
      }
      $updateData[] = array_combine($fields, $item);
    }
    $sql = self::insert($tableName, $updateData, true);
    $sql .= " $extraStatement";
    return $sql;
  }
  static function delete($tableName, $condition)
  {
    return "DELETE FROM $tableName $condition";
  }
  /**
   * 选择语句的字段 SQL 生成
   * @param array $fields 查询的字段名称
   * @param boolean $distinct 查询唯一
   * @return string|null
   */
  static function selectField($fields, $distinct = false)
  {
    $fieldSQLs = [];

    if (is_array($fields)) {
      if (count($fields)) {

        foreach ($fields as $item) {
          if ($item['type'] === 'name') {
            if (strpos($item['value'], ".") !== false) {
              $fieldSQLs[] = $item['value'];
            } else {
              $fieldSQLs[] = self::format($item['value']);
            }
          } else if ($item['type'] === "raw") {
            $fieldSQLs[] = $item['value'];
          } else if ($item['type'] === "sub") {
            if ($item['value'] instanceof Query) {
              $item['value'] = $item['value']->getSQL();
            } else if (is_callable($item['value'])) {
              $query = new Query();
              $item['value']($query);
              $item['value'] = $query->getSQL();
            }

            $fieldSQLs[] = "({$item['value']}) AS {$item['asName']}";
          }
        }
      }
    }

    $fieldSQL = $fieldSQLs ? join(", ", $fieldSQLs) : NULL;

    if ($distinct) {
      $fieldSQL = "DISTINCT {$fieldSQL}";
    }

    return $fieldSQL;
  }
  static function increment($tableName, $field, $value)
  {
    return "UPDATE `$tableName` SET `$field` = $field+$value ";
  }
  static function decrement($tableName, $field, $value)
  {
    return "UPDATE `$tableName` SET `$field` = $field-$value ";
  }
  /**
   * 生成 group by 语句
   * @param array $fieldNames 分组的字段名
   * @return string
   */
  static function groupBy($fieldNames)
  {
    if (!$fieldNames)
      return NULL;
    $fieldNames = self::batchFormat($fieldNames);

    return "GROUP BY " . join(", ", $fieldNames);
  }
}
