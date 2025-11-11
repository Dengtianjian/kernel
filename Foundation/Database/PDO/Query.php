<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Data\Arr;
use Kernel\Foundation\Database\Database;
use kernel\Foundation\Object\AbilityBaseObject;

/** //TODO
 * 1. //DONE 子查询实现
 * (SELECT user_id FROM orders);
 * 2. //DONE 多条件查询
 * SELECT * FROM `users` WHERE (`age` > 18 OR `status` = 'active') AND `email_verified` = 
 * 3. //DONE IN、NOT IN 子查询
 * SELECT * FROM `users` WHERE `id` IN (SELECT `user_id` FROM `orders` WHERE `total` > 100)
 * 4. //DONE 日期函数查询
 * 5. //DONE where 方法，第二个参数可接受多种运算符
 * where("username","=","admin")
 * where("uid","like","admin")
 * where("uid","in","admin")
 * ···
 * 6. //DONE 增加 orWhere，where 的 or 逻辑表达式版本
 * 7. //DONE Query一些方法可以增加比较运算符的方法就添加上
 * 8. //DONE 正则查询
 * 9. //DONE wherExists、whereNotExists
 * 10. //DONE where 第一个参数支持传入数组，数组里面的子数组支持设置运算符
 * 11. 解析 raw
 * 12. 多数据库连接、切换
 * 13. insert功能
 * 14. update 功能
 * 15. delete 功能
 * 16. //DONE get 功能
 * 17. 基础聚合函数 max、min、count、avg、sum
 */
//* 

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class Query extends AbilityBaseObject
{
  private $executeType = "";
  private $options = [];
  private $conditions = [];
  private $filterNullConditions = [];
  protected $sql = "";
  /**
   * 执行语句后重置 options
   * @var boolean
   */
  protected $executeReset = true;
  protected $databaseDriver = null;
  /**
   * 标识是**子句**
   * @var boolean
   */
  protected $clause = false;
  function __construct($tableName = null, $databaseDriver = null)
  {
    $this->reset();

    $this->options['from'] = [
      'tableName' => $tableName,
      "asName" => null
    ];
    $this->databaseDriver = $databaseDriver ?: Connections::getUseDriver();
  }
  static function create($tableName = null, $databaseDriver = null)
  {
    return new Query($tableName, $databaseDriver);
  }
  function fill($executeType, $options)
  {
    $this->executeType = $executeType;
    $this->options = $options;

    return $this;
  }
  /**
   * 设置执行 SQL 时使用的数据库驱动
   * @param mixed $driver 数据库驱动
   * @return static
   */
  function setDatabaseDriver($driver)
  {
    $this->databaseDriver = $driver;

    return $this;
  }
  /**
   * 重置查询参数
   * @return static
   */
  function reset()
  {
    if ($this->executeReset) {
      $this->executeType = null;
      $this->options = [
        "orders" => [],
        "select" => [
          "fields" => [],
          "distinct" => false
        ],
        "conditions" => [],
        "pagination" => [
          "limit" => null,
          "offset" => null
        ],
        "groupBy" => null,
        "having" => null,
        "data" => null
      ];
      $this->sql = '';
    }

    return $this;
  }
  /**
   * 执行 SQL 语句后不重置查询参数
   * @return static
   */
  function notReset()
  {
    $this->executeReset = false;

    return $this;
  }
  /**
   * 获取生成的参数
   * @return string
   */
  function getSQL()
  {
    return $this->generateSQL();
  }
  /**
   * 生成 SQL 语句
   * @return string
   */
  protected function generateSQL()
  {
    $this->sql = "";

    $SQLs = [
      "execute" => null,
      "from" => null,
      "field" => null,
      "condition" => null,
      "order" => null,
      "pagination" => null,
      "groupBy" => null
    ];

    if ($this->options['from']) {
      $from = $this->options['from']['tableName'];
      $asName = $this->options['from']['asName'];

      $from = SQL::from($from, $asName);

      $SQLs['from'] = "FROM {$from}";
    }

    if ($this->options['select']) {
      $SQLs['field'] = SQL::selectField($this->options['select']['fields'], $this->options['select']['distinct']);
    }

    switch ($this->executeType) {
      case "select":
        $SQLs['field'] = $SQLs['field'] ?: "*";
        $SQLs['execute'] = "SELECT {$SQLs['field']}";
        $SQLs['field'] = null;
        break;
      case "insert":
      case "replace":
        $SQLs['execute'] = SQL::insert($this->tableName, $this->options['insertData'], $this->executeType === "replace");
        break;
      case "batchInsert":
      case "batchReplace":
        $SQLs['execute'] = SQL::batchInsert($this->tableName, $this->options['batchInsert']['fields'], $this->options['batchInsert']['values'], $this->executeType === "batchReplace");
        break;
      case "batchInsertIgnore":
        // $SQLs['execute'] = SQL::batchInsertIgnore($this->tableName, $this->options['batchInsert']['fields'], $this->options['batchInsert']['values'], $this->executeType === "batchReplace");
        break;
      case "update":
        $SQLs['execute'] = SQL::update($this->tableName, $this->options['updateData']);
        break;
      case "batchUpdate":
        $SQLs['execute'] = SQL::batchUpdate($this->tableName, $this->options['batchUpdateData']['fields'], $this->options['batchUpdateData']['values']);
        break;
      case "delete":
        $SQLs['execute'] = SQL::delete($this->tableName, $this->sql);
        break;
      case "increment":
        $SQLs['execute'] = SQL::increment($this->tableName, $this->options['increment']['field'], $this->options['increment']['value']);
        break;
      case "decrement":
        $SQLs['execute'] = SQL::decrement($this->tableName, $this->options['decrement']['field'], $this->options['decrement']['value']);
        break;
    }

    if (count($this->options['conditions']) > 0 || count($this->filterNullConditions) > 0) {
      $conditions = array_filter($this->filterNullConditions, function ($item) {
        return !is_null($item['value']) || !empty($item['value']);
      });
      $conditions = array_merge($this->conditions, $conditions);

      if (count($this->options['conditions'])) {
        $whereSql = SQL::where($this->options['conditions']);
        $SQLs['condition'] = $this->executeType ? "WHERE {$whereSql}" : $whereSql;
      }
    }

    if ($this->options['order']) {
      $orderSQL = SQL::order($this->options['orders']);

      $SQLs['order'] = $orderSQL;
    }
    if ($this->options['pagination'] && $this->executeType != "delete") {
      $SQLs['pagination'] = SQL::pagination($this->options['pagination']['limit'], $this->options['pagination']['offset']);
    }

    if ($this->options['groupBy']) {
      $SQLs['groupBy'] = SQL::groupBy($this->options['groupBy']);
    }

    $SQLs = array_filter($SQLs, function ($item) {
      return $item;
    });

    return join(" ", $SQLs);
  }
  function from($tableName, $ASName = null)
  {
    $this->options['from']['tableName'] = $tableName;
    $this->options['from']['asName'] = $ASName;
    return $this;
  }
  function fromSub($callableOrQuery, $ASName = null)
  {
    $this->options['from']['tableName'] = $callableOrQuery;
    $this->options['from']['asName'] = $ASName;

    return $this;
  }
  /**
   * select 语句
   * @param array $fieldNames 字段名称
   * @return static
   */
  function select(...$fieldNames)
  {
    $this->executeType = "select";

    $this->addSelect(...$fieldNames);

    return $this;
  }
  /**
   * select raw 方法，传入的是字段 SQL
   * @param string $fieldSQL 字段 SQL
   * @return static
   */
  function selectRaw($fieldSQL)
  {
    $this->executeType = "select";

    $this->options['select']['fields'][] = [
      "type" => "raw",
      "value" => $fieldSQL
    ];

    return $this;
  }
  /**
   * 添加查询的字段
   * @param array $column 查询的字段
   * @return static
   */
  function addSelect(...$column)
  {
    array_push($this->options['select']['fields'], ...array_map(function ($fieldItem) {
      if ($fieldItem instanceof SQL) {
        return [
          "type" => "raw",
          "value" => $fieldItem->getSQL()
        ];
      } else if (is_string($fieldItem)) {
        if (preg_match("/\s(as|AS)\s/", $fieldItem) || preg_match("/,/", $fieldItem)) {
          return [
            "type" => "raw",
            "value" => $fieldItem
          ];
        } else {
          return [
            "type" => "name",
            "value" => $fieldItem
          ];
        }
      }
    }, $column));

    return $this;
  }
  function selectSub($callbackOrQuery, $asName)
  {
    $this->executeType = "select";

    $this->options['select']['fields'][] = [
      "type" => "sub",
      "value" => $callbackOrQuery,
      "asName" => $asName
    ];

    return $this;
  }
  /**
   * 去重
   * @param array $fieldNames 字段名称，没有则为 *，即所有字段去重
   * @return static
   */
  function distinct(...$fieldNames)
  {
    $this->executeType = "select";

    $this->options['select']['distinct'] = true;
    if ($fieldNames) {
      array_push($this->options['select']['fields'], ...$fieldNames);
    }

    return $this;
  }
  /**
   * 排序
   * @param string|SQL $field 字段名
   * @param string $by 顺序，ASC=正序、DESC=倒序
   * @return static
   */
  function orderBy($field, $by = "ASC")
  {
    $this->options['orders'][] = [
      "field" => $field,
      "by" => $by,
      "type" => "general"
    ];

    return $this;
  }
  /**
   * 排序 raw 方法
   * @param string $rawSQL 排序 SQL 字段
   * @return static
   */
  function orderByRaw($rawSQL)
  {
    $this->options['orders'][] = [
      "field" => $rawSQL,
      "by" => null,
      "type" => "raw"
    ];

    return $this;
  }
  /**
   * 随机排序
   * @param string|array $seed 种子
   * @return static
   */
  function orderRandom($seed = null)
  {
    $this->options['order'][] = [
      "field" => null,
      "by" => $seed,
      "type" => "random"
    ];

    return $this;
  }
  /**
   * 分组
   * @param string|SQL $fieldNames 分组的字段名
   * @return static
   */
  function groupBy(...$fieldNames)
  {
    if (!$this->options['groupBy'])
      $this->options['groupBy'] = [];

    array_push($this->options['groupBy'], ...$fieldNames);

    return $this;
  }
  /**
   * 原始 SQL GROUP BY 子句
   * 
   * 使用原始 SQL 表达式设置 GROUP BY 子句的便捷方法
   * 将原始 SQL 包装为 SQL 对象后传递给 groupBy 方法
   * 
   * @example
   * // 简单分组
   * ->groupByRaw('category, status')
   * 
   * // 使用函数分组
   * ->groupByRaw('YEAR(created_at), MONTH(created_at)')
   * 
   * // 使用表达式分组
   * ->groupByRaw('CASE WHEN price > 100 THEN "premium" ELSE "standard" END')
   * 
   * @param string $rawSQL 原始 SQL 分组表达式
   * @return $this 返回当前实例以支持链式调用
   * 
   * @note 使用原始 SQL 时需要注意 SQL 注入风险，确保传入的 SQL 是安全的
   * @see groupBy() 实际处理分组的核心方法
   * @see SQL 自定义 SQL 表达式类
   */
  function groupByRaw($rawSQL)
  {
    return $this->groupBy(new SQL($rawSQL));
  }
  /**
   * 设置查询的 `limit` 值
   * 
   * @param int|SQL $value 条数
   * @return static
   */
  function limit($value)
  {
    $this->options['pagination']['limit'] = $value;

    return $this;
  }
  /**
   * 设置查询的 `limit` 值
   * @param int|SQL $value 条数
   * @return static
   */
  function take($value)
  {
    return $this->limit($value);
  }
  /**
   * 设置查询的 `offset` 值  
   * @param int|SQL $value 偏移值
   * @return static
   */
  function offset($value)
  {
    $this->options['pagination']['offset'] = $value;

    return $this;
  }
  /**
   * 设置查询的 `offset` 值   
   * offset 的别名
   * @param int|SQL $value 偏移值
   * @return static
   */
  function skip($value)
  {
    return $this->offset($value);
  }
  /**
   * 设置查询的 `limit` 值  
   * limit 的别名，但是会根据传入的页码计算偏移值
   * @param int $page 页码
   * @param int $perPage 每页获取的数量
   * @return static
   */
  function page($page, $perPage = 10)
  {
    $offset = 0;
    if ($page > 0) {
      $offset = $page * $perPage - $perPage;
    }
    $this->limit($perPage)->offset($offset);

    return $this;
  }
  /**
   * 分页查询
   * @return Paginator
   */
  function paginate()
  {
    $TotalQuery = clone $this;
    $Total = $TotalQuery->count();

    $page = $this->options['pagination']['offset'] ?: 1;
    $perPage = $this->options['pagination']['limit'] ?: 10;

    $Items = $this->get();

    return new Paginator($Items, $page, $perPage, $Total);
  }
  /**
   * 核心条件添加方法
   * 
   * 处理各种类型的 WHERE 条件，包括普通比较、子查询、原始SQL、函数条件等
   * 支持数组条件、嵌套条件等多种复杂场景
   * 
   * @param mixed $column 列名，可以是字符串、数组、闭包或Query对象
   * @param string|null $operator 操作符，如 '=', '>', 'LIKE', 'BETWEEN' 等
   * @param mixed $value 条件值，可以是标量、数组、Query对象或闭包
   * @param string $boolean 逻辑连接符，'AND' 或 'OR'，默认为 'AND'
   * @param string|null $funcName 函数名称，用于函数条件如 DATE(), YEAR() 等
   * @param string $type 条件类型，包括：
   *                    - 'comparsion': 普通比较（默认）
   *                    - 'sub': 子查询
   *                    - 'raw': 原始SQL
   *                    - 'nullValue': NULL值判断
   *                    - 'rangeTesting': 范围测试（BETWEEN/IN）
   *                    - 'patternMatching': 模式匹配（LIKE）
   *                    - 'columnComparsion': 列比较
   *                    - 'func': 函数条件
   * @return $this 返回当前实例以支持链式调用
   */
  protected function addWhere($column, $operator = null, $value = null, $boolean = "AND", $funcName = null, $type = "comparsion")
  {
    $operator = $operator ?: "=";
    $boolean = is_null($boolean) ? "AND" : $boolean;

    if (count($this->options['conditions'])) {
      $this->options['conditions'][] = [
        "column" => null,
        "value" => null,
        "operator" => null,
        "type" => "boolean",
        "boolean" => $boolean,
        "funcName" => null
      ];
    }

    if (is_array($column)) {
      foreach ($column as $fieldName => $param) {
        if (is_string($fieldName)) {
          $this->addWhere($fieldName, $operator, $param);
        } else {
          $paramCount = count($param);
          $columnOperator = $operator;
          $columnBoolean = $boolean;
          $columnValue = $paramCount >= 3 ? $param[2] : $param[1];
          if ($paramCount >= 3) {
            $columnOperator = $param[1];
          }
          if ($paramCount >= 4) {
            $columnOperator = $param[3];
          }

          $this->addWhere($param[0], $columnOperator, $columnValue, $columnBoolean);
        }
      }
    } else {
      if ($column instanceof Query || is_callable($column)) {
        $type = "sub";
      } else if (is_string($column) && preg_match("/(\s[=|(|)|<|>|BETWEEN|IN|LIKE|NULL|REGEXP]\s+)+/i", $column)) {
        $type = "raw";
        $value = $column;
        $column = null;
        $operator = null;
      } else {
        if (in_array($operator, ["IS NULL", "IS NOT NULL"])) {
          $type = "nullValue";
        } else if (in_array($operator, ["BETWEEN", "NOT BETWEEN", "IN", "NOT IN"])) {
          $type = "rangeTesting";
        } else if (in_array($operator, ["LIKE", "NOT LIKE"])) {
          $type = "patternMatching";
        } else if (is_null($value)) {
          $type = "nullValue";
          if ($operator === "!=" || $operator === "<>") {
            $operator = "IS NOT NULL";
          } else {
            $operator = "IS NULL";
          }
        }
      }

      $this->options['conditions'][] = [
        "column" => $column,
        "value" => $value,
        "operator" => $operator,
        "type" => $type,
        "boolean" => $boolean,
        "funcName" => $funcName
      ];
    }

    return $this;
  }
  /**
   * 基础 WHERE 条件
   * 
   * 支持多种调用方式：
   * - where('column', 'value')                    // 默认操作符 '='
   * - where('column', 'operator', 'value')        // 指定操作符
   * - where(['col1' => 'val1', 'col2' => 'val2']) // 多条件数组
   * - where(['column','operator',"value"], ['column',"value"]]) // 多条件数组
   * 
   * @param mixed $column 列名或条件数组
   * @param mixed $valueOrOperator 值或操作符
   * @param mixed $value 值（当使用三个参数时）
   * @param string $boolean 逻辑连接符，默认为 'AND'
   * @return $this
   */
  function where($column, $valueOrOperator = null, $value = null, $boolean = "AND")
  {
    $args = func_num_args();

    if ($args >= 3) {
      $valueOrOperator = strtoupper($valueOrOperator);
    } else {
      $value = $valueOrOperator;
      $valueOrOperator = null;
    }

    return $this->addWhere($column, $valueOrOperator, $value, $boolean);
  }
  /**
   * 原始 SQL WHERE 条件
   * 
   * 直接使用原始SQL表达式作为条件
   * 注意：需要确保SQL的安全性，防止SQL注入
   * 
   * @param string $sql 原始SQL表达式
   * @return $this
   */
  function whereRaw($sql)
  {
    $this->addWhere($sql, null, null, "AND", null, "raw");

    return $this;
  }
  /**
   * BETWEEN 条件
   * 
   * @param string $column 列名
   * @param mixed $min 最小值
   * @param mixed $max 最大值
   * @return $this
   */
  function whereBetween($column, $min, $max)
  {
    return $this->addWhere($column, "BETWEEN", [
      $min,
      $max
    ]);
  }
  /**
   * NOT BETWEEN 条件
   * 
   * @param string $column 列名
   * @param mixed $min 最小值
   * @param mixed $max 最大值
   * @return $this
   */
  function whereNotBetween($column, $min, $max)
  {
    return $this->addWhere($column, "NOT BETWEEN", [
      $min,
      $max
    ]);
  }
  /**
   * IN 条件
   * 
   * @param string $column 列名
   * @param array|Query $valueOrQuery IN的值数组或子查询
   * @return $this
   */
  function whereIn($column, $valueOrQuery)
  {
    return $this->addWhere($column, "IN", $valueOrQuery);
  }
  /**
   * NOT IN 条件
   * 
   * @param string $column 列名
   * @param array|Query $valueOrQuery NOT IN的值数组或子查询
   * @return $this
   */
  function whereNotIn($column, $valueOrQuery)
  {
    return $this->addWhere($column, "NOT IN", $valueOrQuery);
  }
  /**
   * NULL 值条件
   * 
   * @param string $column 列名
   * @return $this
   */
  function whereNull($column)
  {
    return $this->addWhere($column, "IS NULL", null);
  }
  /**
   * NOT NULL 值条件
   * 
   * @param string $column 列名
   * @return $this
   */
  function whereNotNull($column)
  {
    return $this->addWhere($column, "IS NOT NULL", null);
  }
  /**
   * LIKE 条件
   * 
   * @param string $column 列名
   * @param string $value 匹配值
   * @return $this
   */
  function whereLike($column, $value)
  {
    return $this->addWhere($column, "LIKE", $value);
  }
  /**
   * NOT LIKE 条件
   * 
   * @param string $column 列名
   * @param string $value 不匹配的值
   * @return $this
   */
  function whereNotLike($column, $value)
  {
    return $this->addWhere($column, "NOT LIKE", $value);
  }
  /**
   * 列比较条件
   * 
   * 比较两个列的值
   * 支持两种调用方式：
   * - whereColumn('col1', 'col2')           // 默认操作符 '='
   * - whereColumn('col1', 'operator', 'col2') // 指定操作符
   * 
   * @param string $column1 第一个列名
   * @param string $operatorOrColumn2 操作符或第二个列名
   * @param string|null $column2 第二个列名（当使用三个参数时）
   * @return $this
   */
  function whereColumn($column1, $operatorOrColumn2, $column2 = null)
  {
    $args = func_num_args();
    $operator = $args === 3 ? $operatorOrColumn2 : "=";
    $column2 = $args === 3 ? $column2 : $operatorOrColumn2;

    return $this->addWhere($column1, $operator, $column2, "AND", null, "columnComparsion");
  }
  /**
   * 日期条件
   * 
   * 对日期部分进行比较
   * 
   * @param string $column 列名
   * @param string $operatorOrValue 操作符或值
   * @param string $value 值（当使用三个参数时）
   * @return $this
   */
  function whereDate($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("DATE(`$column`)", $operator, $value, "AND", "DATE", "func");
  }

  /**
   * 年份条件
   * 
   * 对年份部分进行比较
   * 
   * @param string $column 列名
   * @param int|string $operatorOrValue 操作符或值
   * @param int|string $value 值（当使用三个参数时）
   * @return $this
   */
  function whereYear($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("YEAR(`$column`)", $operator, $value, "AND", "YEAR", "func");
  }

  /**
   * 月份条件
   * 
   * 对月份部分进行比较
   * 
   * @param string $column 列名
   * @param int|string $operatorOrValue 操作符或值
   * @param int|string $value 值（当使用三个参数时）
   * @return $this
   */
  function whereMonth($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("MONTH(`$column`)", $operator, $value, "AND", "MONTH", "func");
  }

  /**
   * 天数条件
   * 
   * 对天数部分进行比较
   * 
   * @param string $column 列名
   * @param int|string $operatorOrValue 操作符或值
   * @param int|string $value 值（当使用三个参数时）
   * @return $this
   */
  function whereDay($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("DAY(`$column`)", $operator, $value, "AND", "DAY", "func");
  }

  /**
   * 时间条件
   * 
   * 对时间部分进行比较
   * 
   * @param string $column 列名
   * @param string $operatorOrValue 操作符或值
   * @param string $value 值（当使用三个参数时）
   * @return $this
   */
  function whereTime($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("TIME(`$column`)", $operator, $value, "AND", "TIME", "func");
  }

  /**
   * 小时条件
   * 
   * 对小时部分进行比较
   * 
   * @param string $column 列名
   * @param int|string $operatorOrValue 操作符或值
   * @param int|string $value 值（当使用三个参数时）
   * @return $this
   */
  function whereHour($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("HOUR(`$column`)", $operator, $value, "AND", "HOUR", "func");
  }

  /**
   * 分钟条件
   * 
   * 对分钟部分进行比较
   * 
   * @param string $column 列名
   * @param int|string $operatorOrValue 操作符或值
   * @param int|string $value 值（当使用三个参数时）
   * @return $this
   */
  function whereMinute($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("MINUTE(`$column`)", $operator, $value, "AND", "MINUTE", "func");
  }

  /**
   * 秒数条件
   * 
   * 对秒数部分进行比较
   * 
   * @param string $column 列名
   * @param int|string $operatorOrValue 操作符或值
   * @param int|string $value 值（当使用三个参数时）
   * @return $this
   */
  function whereSecond($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("SECOND(`$column`)", $operator, $value, "AND", "SECOND", "func");
  }

  /**
   * EXISTS 条件
   * 
   * @param Query|callable $queryOrCallable 子查询或闭包
   * @return $this
   */
  function whereExists($queryOrCallable)
  {
    return $this->addWhere(null, null, $queryOrCallable, "AND", "EXISTS", "func");
  }

  /**
   * NOT EXISTS 条件
   * 
   * @param Query|callable $queryOrCallable 子查询或闭包
   * @return $this
   */
  function whereNotExists($queryOrCallable)
  {
    return $this->addWhere(null, null, $queryOrCallable, "AND", "NOT EXISTS", "func");
  }
  /**
   * OR WHERE 条件
   * 
   * 用法与 where() 相同，但使用 OR 连接
   * 
   * @param mixed $column 列名或条件数组
   * @param mixed $valueOrOperator 值或操作符
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function orWhere($column, $valueOrOperator = null, $value = null)
  {
    return $this->where($column, $valueOrOperator, $value, "OR");
  }

  /**
   * OR 原始 SQL WHERE 条件
   * 
   * 直接使用原始SQL表达式作为条件，使用 OR 连接
   * 注意：需要确保SQL的安全性，防止SQL注入
   * 
   * @param string $sql 原始SQL表达式
   * @return $this
   */
  function orWhereRaw($sql)
  {
    return $this->addWhere($sql, null, null, "OR", null, "raw");
  }

  /**
   * OR BETWEEN 条件
   * 
   * @param string $column 列名
   * @param mixed $min 最小值
   * @param mixed $max 最大值
   * @return $this
   */
  function orWhereBetween($column, $min, $max)
  {
    return $this->addWhere($column, "BETWEEN", [$min, $max], "OR");
  }

  /**
   * OR NOT BETWEEN 条件
   * 
   * @param string $column 列名
   * @param mixed $min 最小值
   * @param mixed $max 最大值
   * @return $this
   */
  function orWhereNotBetween($column, $min, $max)
  {
    return $this->addWhere($column, "NOT BETWEEN", [$min, $max], "OR");
  }

  /**
   * OR IN 条件
   * 
   * @param string $column 列名
   * @param array|Query $valueOrQuery IN的值数组或子查询
   * @return $this
   */
  function orWhereIn($column, $valueOrQuery)
  {
    return $this->addWhere($column, "IN", $valueOrQuery, "OR");
  }

  /**
   * OR NOT IN 条件
   * 
   * @param string $column 列名
   * @param array|Query $valueOrQuery NOT IN的值数组或子查询
   * @return $this
   */
  function orWhereNotIn($column, $valueOrQuery)
  {
    return $this->addWhere($column, "NOT IN", $valueOrQuery, "OR");
  }

  /**
   * OR NULL 值条件
   * 
   * @param string $column 列名
   * @return $this
   */
  function orWhereNull($column)
  {
    return $this->addWhere($column, "IS NULL", null, "OR");
  }

  /**
   * OR NOT NULL 值条件
   * 
   * @param string $column 列名
   * @return $this
   */
  function orWhereNotNull($column)
  {
    return $this->addWhere($column, "IS NOT NULL", null, "OR");
  }

  /**
   * OR LIKE 条件
   * 
   * @param string $column 列名
   * @param mixed $value 匹配值
   * @return $this
   */
  function orWhereLike($column, $value)
  {
    return $this->addWhere($column, "LIKE", $value, "OR");
  }

  /**
   * OR NOT LIKE 条件
   * 
   * @param string $column 列名
   * @param mixed $value 不匹配的值
   * @return $this
   */
  function orWhereNotLike($column, $value)
  {
    return $this->addWhere($column, "NOT LIKE", $value, "OR");
  }

  /**
   * OR 列比较条件
   * 
   * 比较两个列的值，使用 OR 连接
   * 支持两种调用方式：
   * - orWhereColumn('col1', 'col2')           // 默认操作符 '='
   * - orWhereColumn('col1', 'operator', 'col2') // 指定操作符
   * 
   * @param string $column1 第一个列名
   * @param string $operatorOrColumn2 操作符或第二个列名
   * @param string|null $column2 第二个列名（当使用三个参数时）
   * @return $this
   */
  function orWhereColumn($column1, $operatorOrColumn2, $column2 = null)
  {
    $args = func_num_args();
    $operator = $args === 3 ? $operatorOrColumn2 : "=";
    $column2 = $args === 3 ? $column2 : $operatorOrColumn2;

    return $this->addWhere($column1, $operator, $column2, "OR", null, "columnComparsion");
  }

  /**
   * OR 日期条件
   * 
   * 对日期部分进行比较，使用 OR 连接
   * 
   * @param string $column 列名
   * @param mixed $operatorOrValue 操作符或值
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function orWhereDate($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("DATE(`$column`)", $operator, $value, "OR", "DATE", "func");
  }

  /**
   * OR 年份条件
   * 
   * 对年份部分进行比较，使用 OR 连接
   * 
   * @param string $column 列名
   * @param mixed $operatorOrValue 操作符或值
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function orWhereYear($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("YEAR(`$column`)", $operator, $value, "OR", "YEAR", "func");
  }

  /**
   * OR 月份条件
   * 
   * 对月份部分进行比较，使用 OR 连接
   * 
   * @param string $column 列名
   * @param mixed $operatorOrValue 操作符或值
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function orWhereMonth($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("MONTH(`$column`)", $operator, $value, "OR", "MONTH", "func");
  }

  /**
   * OR 天数条件
   * 
   * 对天数部分进行比较，使用 OR 连接
   * 
   * @param string $column 列名
   * @param mixed $operatorOrValue 操作符或值
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function orWhereDay($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("DAY(`$column`)", $operator, $value, "OR", "DAY", "func");
  }

  /**
   * OR 时间条件
   * 
   * 对时间部分进行比较，使用 OR 连接
   * 
   * @param string $column 列名
   * @param mixed $operatorOrValue 操作符或值
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function orWhereTime($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("TIME(`$column`)", $operator, $value, "OR", "TIME", "func");
  }

  /**
   * OR 小时条件
   * 
   * 对小时部分进行比较，使用 OR 连接
   * 
   * @param string $column 列名
   * @param mixed $operatorOrValue 操作符或值
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function orWhereHour($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("HOUR(`$column`)", $operator, $value, "OR", "HOUR", "func");
  }

  /**
   * OR 分钟条件
   * 
   * 对分钟部分进行比较，使用 OR 连接
   * 
   * @param string $column 列名
   * @param mixed $operatorOrValue 操作符或值
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function orWhereMinute($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("MINUTE(`$column`)", $operator, $value, "OR", "MINUTE", "func");
  }

  /**
   * OR 秒数条件
   * 
   * 对秒数部分进行比较，使用 OR 连接
   * 
   * @param string $column 列名
   * @param mixed $operatorOrValue 操作符或值
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function orWhereSecond($column, $operatorOrValue, $value = null)
  {
    $args = func_num_args();
    $value = $args === 3 ? $value : $operatorOrValue;
    $operator = $args === 3 ? $operatorOrValue : "=";

    return $this->addWhere("SECOND(`$column`)", $operator, $value, "OR", "SECOND", "func");
  }

  /**
   * OR EXISTS 条件
   * 
   * @param Query|callable $queryOrCallable 子查询或闭包
   * @return $this
   */
  function orWhereExists($queryOrCallable)
  {
    return $this->addWhere(null, null, $queryOrCallable, "OR", "EXISTS", "func");
  }

  /**
   * OR NOT EXISTS 条件
   * 
   * @param Query|callable $queryOrCallable 子查询或闭包
   * @return $this
   */
  function orWhereNotExists($queryOrCallable)
  {
    return $this->addWhere(null, null, $queryOrCallable, "OR", "NOT EXISTS", "func");
  }

  function filterNullWhere($params, $value = null, $glue = "=", $operator = "AND")
  {
    if (is_string($params) && \preg_match_all("/\s+[=|<|>|BETWEEN|IN|LIKE|NULL|REGEXP]+/i", $params)) {
      array_push($this->filterNullConditions, [
        "statement" => $params,
        "fieldName" => null,
        "value" => null,
        "glue" => null,
        "operator" => $operator
      ]);
    } else if (is_array($params)) {
      foreach ($params as $fieldName => $param) {
        array_push($this->filterNullConditions, [
          "statement" => null,
          "fieldName" => $fieldName,
          "value" => $param,
          "glue" => $glue,
          "operator" => $operator
        ]);
      }
    } else {
      array_push($this->filterNullConditions, [
        "statement" => null,
        "fieldName" => $params,
        "value" => $value,
        "glue" => $glue,
        "operator" => $operator
      ]);
    }

    return $this;
  }
  function insert($data, $isReplaceInto = false)
  {
    if ($isReplaceInto) {
      $this->executeType = "replace";
    } else {
      $this->executeType = "insert";
    }
    $this->options['insertData'] = $data;
    $this->sql = $this->generateSQL();
    $this->reset();
    return $this;
  }
  function batchInsert($fieldNames, $values, $isReplaceInto = false)
  {
    if ($isReplaceInto) {
      $this->executeType = "batchReplace";
    } else {
      $this->executeType = "batchInsert";
    }
    $this->options['batchInsert'] = [
      "fields" => $fieldNames,
      "values" => $values
    ];

    $this->sql = $this->generateSQL();
    $this->reset();
    return $this;
  }
  function batchInsertIgnore($fieldNames, $values)
  {
    $this->executeType = "batchInsertIgnore";
    $this->options['batchInsert'] = [
      "fields" => $fieldNames,
      "values" => $values
    ];

    $this->sql = $this->generateSQL();
    $this->reset();
    return $this;
  }
  function update($data)
  {
    $this->executeType = "update";
    $this->options['updateData'] = $data;
    $this->sql = $this->generateSQL();
    $this->reset();
    return $this;
  }
  function batchUpdate($fieldNames, $values)
  {
    $this->executeType = "batchUpdate";
    $this->options['batchUpdateData'] = [
      "fields" => $fieldNames,
      "values" => $values
    ];
    $this->sql = $this->generateSQL();
    $this->reset();
    return $this;
  }
  function delete($directly = false)
  {
    if ($directly) {
      $this->executeType = "delete";
    } else {
      $this->executeType = "softDelete";
    }
    $this->sql = $this->generateSQL();
    $this->reset();
    return $this;
  }
  /**
   * 获取查询结果的第一条记录
   * 
   * 执行查询并返回结果集中的第一条记录
   * 如果没有找到记录，返回 false
   * 
   * @return array|false 返回记录数组或false
   */
  function first()
  {
    $this->executeType = "select";
    $this->sql = $this->generateSQL();

    $data = $this->databaseDriver->fetchAll($this->sql);
    if (!$data)
      return false;

    return $data[array_key_first($data)];
  }
  /**
   * 获取第一条记录的指定列值
   * 
   * 查询第一条记录并返回指定列的值
   * 如果记录不存在或列不存在，返回 null
   * 
   * @param string $column 要获取值的列名
   * @return mixed|null 返回列值或null
   */
  function value($column)
  {
    $data = $this->first();

    if (!is_array($data) && !Arr::isAssoc((array) $data))
      return null;
    if (!array_key_exists($column, $data))
      return null;

    return $data[$column];
  }
  /**
   * 获取所有查询结果
   * 
   * 执行查询并返回所有结果记录
   * 
   * @return array|string 返回记录数组
   */
  function get()
  {
    $this->executeType = "select";
    $this->sql = $this->generateSQL();

    $data = $this->databaseDriver->fetchAll($this->sql);

    return $data;
  }
  /**
   * 提取指定列的值作为数组
   * 
   * 查询结果并提取指定列的值，可选择使用另一列作为键
   * 
   * @example
   * // 提取名称列表
   * ->pluck('name')
   * 
   * // 提取以ID为键的名称列表
   * ->pluck('name', 'id')
   * 
   * @param string $column 要提取值的列名
   * @param string|null $indexKey 作为数组键的列名（可选）
   * @return array 返回键值对数组
   */
  function pluck($column, $indexKey = null)
  {
    $this->options['select']['fields'] = [];
    $this->addSelect($column);
    if ($indexKey)
      $this->addSelect($indexKey);

    $this->executeType = "select";
    $this->sql = $this->generateSQL();

    $data = $this->databaseDriver->fetchAll($this->sql);

    return array_column($data, $column, $indexKey);
  }
  /**
   * 使用游标遍历查询结果
   * 
   * 使用生成器逐行返回查询结果，适用于处理大量数据
   * 减少内存占用，提高大数据集处理性能
   * 
   * @return \Generator 返回生成器，每次迭代返回一条记录
   */
  function cursor()
  {
    $this->executeType = "select";
    $this->sql = $this->generateSQL();

    /**
     * @var \PDOStatement
     */
    $PDOStatement = $this->databaseDriver->prepare($this->sql, [
      \PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL
    ]);
    $PDOStatement->execute();

    while ($record = $PDOStatement->fetch(\PDO::FETCH_ASSOC)) {
      if (!$record)
        break;

      yield $record;
    }
  }
  /**
   * 分块处理查询结果
   * 
   * 将结果集分块处理，每次处理指定数量的记录
   * 适用于处理大量数据，避免内存溢出
   * 
   * @example
   * ->chunk(100, function($items, $page) {
   *     foreach ($items as $item) {
   *         // 处理每条记录
   *     }
   *     return true; // 返回 false 可中断处理
   * });
   * 
   * @param int $size 每块的大小
   * @param callable $callback 处理回调函数，接收当前块数据和页码
   * @return bool 处理完成返回true，被中断返回false
   */
  function chunk($size, $callback)
  {
    $page = 1;
    $pageItems = 0;

    do {
      /**
       * @var Paginator
       */
      $result = $this->notReset()->page($page, $size)->paginate();
      $pageItems = $result->getPageSize();
      if ($pageItems === 0) {
        break;
      }

      if ($callback($result->getItems(), $page) === false) {
        return false;
      }

      $page++;
    } while ($pageItems === $size);

    return true;
  }
  /**
   * 基于ID的分块处理
   * 
   * 使用ID字段进行高效的分块处理，避免偏移量性能问题
   * 适用于大数据量的分页处理
   * 
   * @param int $size 每块的大小
   * @param callable $callback 处理回调函数
   * @param string $column 用于分块的列名，默认为"id"
   * @return bool 处理完成返回true，被中断返回false
   */
  function chunkById($size, $callback, $column = "id")
  {
    $pageItems = 0;
    $lastId = null;

    do {
      $this->options['orders'] = array_filter($this->options['orders'], function ($item) use ($column) {
        return $item['field'] !== $column;
      });

      $this->orderBy($column, "ASC");
      if ($lastId) {
        $this->where($column, ">", $lastId);
      }

      $items = $this->select()->limit($size)->get();
      $pageItems = count($items);

      if (!$items || $pageItems === 0) {
        break;
      }

      $lastId = $items[array_key_last($items)][$column];

      if ($callback($items) === false) {
        return false;
      }

    } while ($pageItems === $size);

    return true;
  }
  /**
   * 基于ID的分块流式处理
   * 
   * 使用生成器逐块返回数据，适用于流式处理大量数据
   * 结合了chunkById的高效性和生成器的低内存占用
   * 
   * @param int $size 每块的大小
   * @param string $column 用于分块的列名，默认为"id"
   * @return \Generator 返回生成器，每次迭代返回一个数据块
   */
  function chunkStream($size, $column = "id")
  {
    $pageItems = 0;
    $lastId = null;

    do {
      $this->options['orders'] = array_filter($this->options['orders'], function ($item) use ($column) {
        return $item['field'] !== $column;
      });

      $this->orderBy($column, "ASC");
      if ($lastId) {
        $this->where($column, ">", $lastId);
      }

      $items = $this->select()->limit($size)->get();
      $pageItems = count($items);

      if (!$items || $pageItems === 0) {
        break;
      }

      $lastId = $items[array_key_last($items)][$column];

      foreach ($items as $item) {
        yield $item;
      }

    } while ($pageItems === $size);

    return true;
  }
  /**
   * 统计查询结果数量
   * 
   * 执行 COUNT 聚合查询，统计满足条件的记录数量
   * 可以指定统计的列，默认为统计所有记录数
   * 
   * @example
   * // 统计所有记录
   * ->count()
   * 
   * // 统计指定列的非空值数量
   * ->count('user_id')
   * 
   * // 使用 DISTINCT 统计
   * ->count('DISTINCT category')
   * 
   * @param string $column 要统计的列名，默认为 "*" 表示所有记录
   * @return int|false 返回统计数量，查询失败返回 false
   * 
   * @note 此方法会修改 SELECT 子句，添加 COUNT 聚合函数
   * @note 如果查询结果为空，返回 false
   * @see raw() 用于创建原始 SQL 表达式
   */
  function count($column = "*")
  {
    $this->executeType = "select";
    $this->addSelect($this->raw("COUNT({$column})"));
    $this->sql = $this->generateSQL();

    $data = $this->databaseDriver->fetch($this->sql);

    if (!$data)
      return false;

    return $data[array_key_first($data)];
  }
  function increment($field, $value)
  {
    $this->executeType = "increment";
    $this->options["increment"] = [
      "field" => $field,
      "value" => $value
    ];
    $this->sql = $this->generateSQL();
    $this->reset();
    return $this;
  }
  function decrement($field, $value)
  {
    $this->executeType = "decrement";
    $this->options["decrement"] = [
      "field" => $field,
      "value" => $value
    ];
    $this->sql = $this->generateSQL();
    $this->reset();
    return $this;
  }

  /**
   * 查询是否存在
   * @return bool `true`=存在，`false`=不存在
   */
  function exists()
  {
    $this->executeType = "select";
    $this->sql = $this->generateSQL();
    $this->sql = "SELECT EXISTS($this->sql) as exist";

    $data = $this->databaseDriver->fetch($this->sql);

    return boolval($data[array_key_first($data)]);
  }
  /**
   * 查询是否不存在
   * @return bool true`=不存在，`false`=存在
   */
  function notExists()
  {
    $this->executeType = "select";
    $this->sql = $this->generateSQL();
    $this->sql = "SELECT NOT EXISTS($this->sql) as exist";

    $data = $this->databaseDriver->fetch($this->sql);

    return boolval($data[array_key_first($data)]);
  }
  /**
   * 原始 SQL 语句
   * @param mixed $sql
   * @return SQL
   */
  function raw($sql)
  {
    return new SQL($sql);
  }
}
