<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Output;


/**
 * SQL 语句生成器 — 底层结构化条件到 SQL 字符串的转换引擎
 *
 * Statement 是 ORM 的 SQL 生成层，负责将 Query 构建器产生的结构化条件数组
 * 转换为合法的 SQL 字符串片段。所有方法均为静态方法，无状态、无副作用。
 *
 * ## 核心职责
 *
 * - **查询构建**：SELECT 字段、FROM 子句、WHERE 条件、ORDER BY、GROUP BY、LIMIT/OFFSET
 * - **数据操作**：INSERT、UPDATE、BATCH UPDATE、DELETE、INCREMENT/DECREMENT
 * - **数据格式化**：PHP 值 → SQL 安全字符串（`format`/`batchFormat`），自动处理
 *   NULL、布尔、数组（JSON/序列化）、Query 子查询、Statement 实例
 *
 * ## 使用方式
 *
 * 大部分情况下你不需要直接使用 Statement，而是通过 Query 构建器间接调用。
 * 少数需要原生 SQL 片段或自定义 SQL 生成的场景可直接使用：
 *
 * ```php
 * // 直接生成 SQL 片段
 * Statement::from('users', 'u');       // `users` AS `u`
 * Statement::where($conditions);       // WHERE 子句字符串
 * Statement::insert('logs', $data);    // INSERT INTO ...
 *
 * // 通过 DB::raw() 快捷创建 Statement 实例嵌入 Query
 * DB::table('orders')->where('created_at', '>', DB::raw('NOW()'));
 * ```
 *
 * ## 条件类型参考
 *
 * WHERE 条件支持类型：`comparsion` | `raw` | `sub` | `nullValue` |
 * `rangeTesting` | `patternMatching` | `columnComparsion` | `func` | `boolean`
 *
 * @see Query::addWhere()   查询构建器 — 条件数组的组装层
 * @see Driver  PDO 驱动 — SQL 执行层
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
      // 处理 table.column 或 db.table.column 格式：分别用反引号包围各段
      if ($stringQuote === '`' && str_contains($target, '.')) {
        $parts = array_map(function ($part) use ($stringQuote) {
          return $stringQuote . $part . $stringQuote;
        }, explode('.', $target));
        $target = implode('.', $parts);
      } else {
        $target = join("", [$stringQuote, $target, $stringQuote]);
      }
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
  /**
   * 生成 FROM 子句
   *
   * 支持表名、库.表、子查询等多种 FROM 数据源格式，
   * 自动处理别名（AS）和 `` 包围。
   *
   * @param string|callable|Query $from   数据源：表名字符串、子查询回调或 Query 实例
   * @param string|null           $asName 别名（已含 AS 则跳过）
   * @return string 格式化后的 FROM 子句，如 `users` AS `u`
   */
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
  /**
   * 生成 WHERE 子句
   *
   * 遍历结构化条件数组，递归生成完整的 WHERE SQL 字符串。
   * 支持 8 种条件类型，自动处理布尔连接符（AND/OR）的插入。
   *
   * ## 条件数组结构
   *
   * 每个条件项的基本结构：
   * ```
   * [
   *   'type'    => 'comparsion',  // 条件类型（必填）
   *   'boolean' => 'AND',         // 与前一个条件的连接符，默认 AND。首项自动忽略
   *   'column'  => 'status',      // 列名（raw/sub 类型可选）
   *   'operator'=> '=',           // 运算符（raw/nullValue 可选）
   *   'value'   => 1,             // 值（可为标量/数组/回调/Query）
   * ]
   * ```
   *
   * ## 支持的条件类型
   *
   * | 类型 | 说明 | 示例 |
   * |------|------|------|
   * | `comparsion` | 比较运算 | `status = 1`、`id IN (1,2,3)` |
   * | `columnComparsion` | 列对列比较 | `updated_at > created_at` |
   * | `nullValue` | NULL 判断 | `deleted_at IS NULL` |
   * | `rangeTesting` | 范围测试 | `age BETWEEN 18 AND 60`、`status IN (1,2)` |
   * | `patternMatching` | 模式匹配 | `name LIKE '%John%'` |
   * | `func` | 函数条件 | `DATE(created_at) = '2025-01-01'`、`EXISTS(...)` |
   * | `raw` | 原始 SQL | `FIND_IN_SET(?, tags)` |
   * | `sub` | 子查询 | `(SELECT COUNT(*) FROM ...)` |
   * | `boolean` | 纯连接符 | 仅 AND / OR，不产生条件 SQL |
   *
   * @param array $conditions 结构化条件数组，由 Query::addWhere() 组装
   * @return string 完整的 WHERE SQL（不含 "WHERE" 关键字），如 `status` = 1 AND `name` LIKE '%John%'
   */
  static function where($conditions)
  {
    // debug($conditions);
    $conditionSQLs = [];
    $prevConditionItem = null;

    foreach ($conditions as $ConditionItem) {
      if ($ConditionItem['type'] === "boolean") {
        if ($prevConditionItem && !$prevConditionItem['boolean'] && $prevConditionItem['type'] !== 'boolean')
          $conditionSQLs[] = $ConditionItem['boolean'];
      } else {
        if ($prevConditionItem) {
          $boolean = $ConditionItem['boolean'] ?: "AND";

          if ($prevConditionItem['type'] === "raw" && !preg_match("/\s(or|and)\s?$/i", $prevConditionItem['value'])) {
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

      $prevConditionItem = $ConditionItem;
    }

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
  /**
   * 生成 UPDATE SQL
   *
   * 根据关联数组生成 SET 子句，null 值自动转为 NULL，可附加额外条件（WHERE/ORDER/LIMIT）。
   *
   * @param string $tableName      表名
   * @param array  $data           要更新的字段键值对，如 ['name' => 'Alice', 'status' => 1]
   * @param string $extraStatement 额外 SQL 片段（如 WHERE id = 1），不含 UPDATE/SET 关键字
   * @return string 完整的 UPDATE 语句
   *
   * @example
   * ```php
   * Statement::update('users', ['name' => 'Bob', 'status' => 1], 'WHERE id = 5');
   * // UPDATE users SET `name`='Bob',`status`=1 WHERE id = 5
   *
   * Statement::update('users', ['deleted_at' => null], 'WHERE id = 1');
   * // UPDATE users SET `deleted_at`=NULL WHERE id = 1
   * ```
   */
  static function update($tableName, $data, $extraStatement = "")
  {
    $updateData = self::batchFormat($data, "'", 'json');
    foreach ($updateData as $field => &$value) {
      if ($value === null)
        $value = "NULL";
      $value = "`$field` = $value";
    }
    $updateData = implode(",", $updateData);
    $sql = "UPDATE $tableName SET {$updateData} $extraStatement";
    return $sql;
  }
  /**
   * 生成批量更新 SQL
   *
   * 通过 REPLACE INTO 实现批量更新，适用于多条记录的一次性更新操作。
   * 注意：REPLACE INTO 会删除旧行再插入新行，因此未指定的字段将丢失或被设为默认值。
   *
   * @param string $tableName      表名
   * @param array  $fields         字段名列表，如 ['id', 'name', 'status']
   * @param array  $datas          二维数据数组，每行按 $fields 的顺序排列值
   * @param string $extraStatement 额外 SQL 片段（拼接在 REPLACE INTO 之后）
   * @return string 完整的 REPLACE INTO 语句
   *
   * @example
   * ```php
   * Statement::batchUpdate('users',
   *     ['id', 'name', 'status'],
   *     [[1, 'Alice', 1], [2, 'Bob', 0], [3, 'Carol', 1]]
   * );
   * // REPLACE INTO users (`id`,`name`,`status`) VALUES (1,'Alice',1),(2,'Bob',0),(3,'Carol',1);
   * ```
   */
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
  /**
   * 生成 DELETE SQL
   *
   * @param string $tableName 表名
   * @param string $condition 条件子句（含 WHERE），由 Query 构建好传入
   * @return string 完整的 DELETE 语句
   *
   * @example
   * ```php
   * Statement::delete('users', 'WHERE id = 1');
   * // DELETE FROM users WHERE id = 1
   * ```
   */
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
  /**
   * 生成字段自增 SQL
   *
   * 在数据库层面执行字段加法，避免并发读写的数据竞争问题。
   * 生成的 SQL 不含 WHERE 条件，实际使用时由 Query 拼接。
   *
   * @param string   $tableName 表名
   * @param string   $field     字段名
   * @param int|float $value    增量值
   * @return string UPDATE 片段
   *
   * @example
   * ```php
   * Statement::increment('articles', 'view_count', 1);
   * // UPDATE `articles` SET `view_count` = view_count+1
   * ```
   */
  static function increment($tableName, $field, $value)
  {
    return "UPDATE `$tableName` SET `$field` = $field+$value ";
  }
  /**
   * 生成字段自减 SQL
   *
   * 与 increment 对称，在数据库层面执行字段减法。
   *
   * @param string   $tableName 表名
   * @param string   $field     字段名
   * @param int|float $value    减量值
   * @return string UPDATE 片段
   *
   * @example
   * ```php
   * Statement::decrement('inventory', 'stock', 5);
   * // UPDATE `inventory` SET `stock` = stock-5
   * ```
   */
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
  /**
   * 生成 JOIN 子句
   *
   * 将结构化 JOIN 数组转为 SQL 片段，自动处理表名 `` 包围、
   * 表别名（AS）和 ON 条件。
   *
   * @param array $joins 格式与 Query::$options['joins'] 一致：
   *   ['type' => 'INNER', 'table' => 'profiles', 'alias' => 'p', 'first' => 'u.id', 'operator' => '=', 'second' => 'p.user_id']
   * @return string 如 "INNER JOIN `profiles` AS `p` ON `u`.`id` = `p`.`user_id`"
   */
  static function join($joins)
  {
    $sqls = [];
    foreach ($joins as $join) {
      $table = "`{$join['table']}`";
      if ($join['alias']) {
        $table .= " AS `{$join['alias']}`";
      }
      $first  = self::format($join['first']);
      $second = self::format($join['second']);
      $sqls[] = "{$join['type']} JOIN {$table} ON {$first} {$join['operator']} {$second}";
    }
    return implode(" ", $sqls);
  }
}
