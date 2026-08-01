<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 数据库查询构建器
 *
 * 提供流畅的链式 API 构建 SQL 查询，支持 SELECT / INSERT / UPDATE / DELETE，
 * 以及子查询、联结、聚合函数、分页等高级特性。
 *
 * 使用方式：
 * ```php
 * // 通过 table() 静态入口创建实例并链式调用
 * Query::table('users')->where('status', 'active')->get();
 *
 * // 或直接 new
 * (new Query('users'))->where('id', 1)->first();
 * ```
 *
 * @method static Query table(string|null $tableName = null, Driver|null $databaseDriver = null) 创建 Query 实例并指定表名
 */


class Query extends AbilityBaseObject
{
  /**
   * 当前 SQL 操作类型
   * 
   * 可选值: 'select' | 'insert' | 'replace' | 'update' | 'delete'
   * 决定 generateSQL() 生成的 SQL 语句类型
   * 
   * @var string
   */
  private $executeType = "";
  /**
   * 查询选项数组
   * 
   * 存储构建 SQL 所需的所有参数，结构如下：
   * - 'from'        => ['tableName' => string, 'asName' => string|null]
   * - 'select'      => ['fields' => array, 'distinct' => bool]
   * - 'conditions'  => array  // where 条件列表
   * - 'orders'      => array  // order by 排序列表
   * - 'pagination'  => ['limit' => int|null, 'offset' => int|null]
 * - 'joins'       => array  // JOIN 定义列表，每项: ['type'=>'INNER','table'=>'profiles','alias'=>'p','first'=>'users.id','operator'=>'=','second'=>'p.user_id']
 * - 'groupBy'     => array|null
 * - 'having'      => mixed|null
 * - 'data'        => mixed|null  // insert/update 数据
 * - 'insertData'  => array  // insert 专用数据
 * - 'updateData'  => array  // update 专用数据
 * - 'insertIsIgnore' => bool  // INSERT IGNORE 标记
   * 
   * @var array
   */
  private $options = [];
  /**
   * 可空过滤条件集合
   * 
   * 通过 filterNullWhere() 添加的条件存储在此数组中，
   * 在 generateSQL() 中生成 WHERE 子句之前会自动过滤值为空的条目
   * 
   * @var array
   */
  private $filterNullConditions = [];
  /**
   * 当前构建的 SQL 语句
   * 
   * 由 generateSQL() 生成并缓存，供执行方法使用
   * 
   * @var string
   */
  protected $sql = "";
  /**
   * 执行后是否自动重置查询参数
   * 
   * 默认为 true。设置为 false（通过 notReset()）后可保持查询状态，
   * 适用于需要在多次执行中复用同一查询参数的场景（如 chunk 分块、paginate 分页）
   * 
   * @var boolean
   */
  protected $executeReset = true;
  /**
   * 数据库驱动实例
   * 
   * 负责实际执行 SQL 查询、参数绑定、结果获取等底层操作
   * 
   * @var Driver
   */
  protected $databaseDriver = null;
  /**
   * 标识当前查询是否为子查询子句
   * 
   * 当为 true 时，generateSQL() 不拼接执行关键字（SELECT/INSERT/UPDATE/DELETE），
   * 只生成 FROM / WHERE / ORDER BY 等子句部分
   * 
   * @var boolean
   */
  protected $clause = false;
  /**
   * 参数绑定数组
   * 
   * 存储预处理占位符与值的映射，键为占位符名称（如 ':id'、':__in_0'），值为实际绑定值。
   * 在首次添加 WHERE IN 条件时自动生成，也可通过 bind()/addBindings() 手动添加。
   * 执行时通过 mergeBindings() 与调用参数合并，内部绑定优先于外部调用参数。
   * 
   * @var array
   */
  protected $bindings = [];
  /**
   * 自增绑定计数器
   * 
   * 用于 generateBindingKey() 生成唯一的内部占位符名称（格式 :__in_N），
   * 每次调用 generateBindingKey() 自动递增，确保同一查询中的 IN 占位符不重名
   * 
   * @var int
   */
  private $bindingCounter = 0;
  /**
   * 构造查询构建器实例
   *
   * @param string|null $tableName 表名（可选，也可通过 from() 链式设置）
   * @param Driver|null $databaseDriver 数据库驱动，默认使用当前连接
   *
   * @example
   * new Query('users');                     // 自动绑定默认驱动
   * new Query('users', $customDriver);      // 指定驱动
   */
  function __construct($tableName = null, $databaseDriver = null)
  {
    $this->reset();

    $this->options['from'] = [
      'tableName' => $tableName,
      "asName" => null
    ];
    $this->databaseDriver = $databaseDriver ?: Connections::getUseDriver();
  }
  /**
   * 设置执行 SQL 时使用的数据库驱动
   * 
   * 允许动态切换数据库连接，适用于多数据库或读写分离场景
   * 
   * @param Driver $driver 数据库驱动实例，需实现 Driver 接口
   * @return $this 返回当前实例以支持链式调用
   * 
   * @example
   * // 切换到只读从库
   * $readonlyDriver = Connections::getReaderDriver();
   * Query::table('users')->setDatabaseDriver($readonlyDriver)->where('id', 1)->first();
   */
  function setDatabaseDriver($driver)
  {
    $this->databaseDriver = $driver;

    return $this;
  }
  /**
   * 获取当前使用的数据库驱动
   * 
   * 返回当前查询构建器绑定的数据库驱动实例，可用于执行原生的 PDO 操作
   * 
   * @return Driver 数据库驱动实例
   * 
   * @example
   * $driver = $query->getDatabaseDriver();
   * $driver->query('SET NAMES utf8mb4');
   */
  function getDatabaseDriver()
  {
    return $this->databaseDriver;
  }
  /**
   * 获取当前查询绑定的表名
   * @return string|null
   */
  function getTableName()
  {
    return $this->options['from']['tableName'] ?? null;
  }
  /**
   * 静态工厂：创建 Query 实例并指定表名
   *
   * 命名更符合直觉，是 new Query($tableName) 的快捷方式
   *
   * @param string|null $tableName 表名
   * @param Driver|null $databaseDriver 数据库驱动
   * @return static
   *
   * @example
   * Query::table('users')->where('id', 1)->first();
   * Query::table('orders', $customDriver)->where('status', 'paid')->get();
   */
  static function table($tableName = null, $databaseDriver = null)
  {
    return new Query($tableName, $databaseDriver);
  }
  /**
   * 填充执行类型与选项
   *
   * 用于子查询或 Model 层注入预构建的查询状态，不经过完整链式调用
   *
   * @param string $executeType 执行类型 (select/insert/update/delete)
   * @param array $options 选项数组（from、select、conditions 等）
   * @return $this
   */
  function fill($executeType, $options)
  {
    $this->executeType = $executeType;
    $this->options = $options;

    return $this;
  }
  /**
   * 重置查询参数
   * 
   * 将所有查询选项恢复为初始状态，但保留 from（表名）和 databaseDriver 配置。
   * 受 $this->executeReset 标志控制，若已设为 false 则跳过重置。
   * 通常无需手动调用，executeWrite() 在 INSERT/UPDATE/DELETE 执行后会自动重置。
   * 
   * 重置内容包括：
   * - executeType（操作类型）
   * - orders（排序）、select 字段、conditions（WHERE 条件）
   * - pagination（分页）、groupBy、having、data
   * - SQL 语句缓存、绑定参数、绑定计数器
   * 
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see notReset() 禁止自动重置
   * @see executeWrite() 内部调用的重置触发点
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
        "joins" => [],
        "pagination" => [
          "limit" => null,
          "offset" => null
        ],
        "groupBy" => null,
        "having" => null,
        "data" => null
      ];
      $this->sql = '';
      $this->bindings = [];
      $this->bindingCounter = 0;
    }

    return $this;
  }
  /**
   * 添加绑定参数
   *
   * 向查询中添加预处理绑定参数，用于占位符的值替换。
   * 支持命名占位符（:name）和问号占位符（?）。
   *
   * @param string|int $key 占位符名称（如 ':id'）或位置索引
   * @param mixed $value 绑定的值
   * @return $this
   *
   * @example
   * // 命名占位符
   * $query->from('users')->whereRaw('status = :status')->bind(':status', 'active')->get();
   *
   * // 问号占位符
   * $query->from('users')->whereRaw('age > ?')->bind(0, 18)->get();
   */
  function bind($key, $value)
  {
    $this->bindings[$key] = $value;
    return $this;
  }
  /**
   * 批量添加绑定参数
   *
   * @param array $bindings 绑定参数数组，键为占位符名称/索引，值为绑定的值
   * @return $this
   *
   * @example
   * $query->from('users')
   *     ->whereRaw('status = :status AND age > :age')
   *     ->addBindings([':status' => 'active', ':age' => 18])
   *     ->get();
   */
  function addBindings(array $bindings)
  {
    foreach ($bindings as $key => $value) {
      $this->bindings[$key] = $value;
    }
    return $this;
  }
  /**
   * 获取所有累积的绑定参数
   *
   * @return array
   */
  function getBindings()
  {
    return $this->bindings;
  }
  /**
   * 生成唯一的内部占位符名称
   *
   * @return string 如 ':__in_0', ':__in_1' 等
   */
  private function generateBindingKey()
  {
    return ':__in_' . ($this->bindingCounter++);
  }
  /**
   * 合并累积的绑定参数与传入参数
   *
   * 内部 IN 占位符绑定优先，外部 $params 可覆盖同名键
   *
   * @param array $params 调用时传入的参数
   * @return array 合并后的绑定参数
   */
  private function mergeBindings($params)
  {
    if (empty($this->bindings)) {
      return $params;
    }
    return $this->bindings + $params;
  }
  /**
   * 禁止执行后自动重置查询参数
   * 
   * 设置后 INSERT/UPDATE/DELETE 执行完毕不会清空 options，
   * 适用于需要在多次执行中保持查询状态的场景。
   * 
   * @return $this 返回当前实例以支持链式调用
   * 
   * @example
   * // 分块处理时不希望每次重置导致丢失条件
   * $query->from('logs')->where('status', 'pending')->notReset();
   * do {
   *     $items = $query->page($page, 100)->get();
   *     // 处理数据...
   *     $page++;
   * } while (!empty($items));
   * 
   * @see reset() 重置查询参数的核心方法
   * @see chunk() 内部使用 notReset 保持查询条件
   */
  function notReset()
  {
    $this->executeReset = false;

    return $this;
  }
  /**
   * 获取当前查询对应的 SQL 语句（调试用）
   * 
   * 调用 generateSQL() 返回完整 SQL 字符串，可用于日志记录或调试。
   * 注意：返回的 SQL 中包含占位符，不会替换为实际值。
   * 
   * @return string 生成的 SQL 语句（含占位符）
   * 
   * @example
   * $sql = Query::table('users')->where('status', 'active')->getSQL();
   * // SELECT * FROM `users` WHERE `status` = 'active'
   * 
   * @see generateSQL() 内部 SQL 生成逻辑
   */
  function getSQL()
  {
    return $this->generateSQL();
  }
  /**
   * 根据当前选项生成完整 SQL 语句
   * 
   * 核心 SQL 生成方法，根据 executeType 和 options 动态拼接 SQL 各部分：
   * 1. 解析 from（表名/别名/子查询）
   * 2. 解析 select 字段（含 distinct）
   * 3. 根据 executeType 生成执行子句（SELECT/INSERT/REPLACE/UPDATE/DELETE）
   * 4. 拼接 WHERE 条件（自动过滤 filterNullConditions 中的空值条件）
   * 5. 拼接 ORDER BY、LIMIT/OFFSET、GROUP BY 子句
   * 
   * @return string 生成的完整 SQL 语句
   * 
   * @note DELETE 操作不使用 LIMIT/OFFSET，以防止误删超过预期数量的记录
   */
  protected function generateSQL()
  {
    $this->sql = "";

    $SQLs = [
      "execute" => null,
      "from" => null,
      "join" => null,
      "field" => null,
      "condition" => null,
      "order" => null,
      "pagination" => null,
      "groupBy" => null
    ];

    if ($this->options['from']) {
      $from = $this->options['from']['tableName'];
      $asName = $this->options['from']['asName'];

      $from = Statement::from($from, $asName);
    }

    if ($this->options['select']) {
      $SQLs['field'] = Statement::selectField($this->options['select']['fields'], $this->options['select']['distinct']);
    }

    switch ($this->executeType) {
      case "select":
        $SQLs['from'] = "FROM {$from}";

        $SQLs['field'] = $SQLs['field'] ?: "*";
        $SQLs['execute'] = "SELECT {$SQLs['field']}";
        $SQLs['field'] = null;
        break;
      case "insert":
      case "replace":
        $SQLs['execute'] = Statement::insert($from, $this->options['insertData'], $this->executeType === "replace", $this->options['insertIsIgnore'] ?? false);
        break;
      case "update":
        $SQLs['execute'] = Statement::update($from, $this->options['updateData']);
        break;
      case "delete":
        $SQLs['execute'] = Statement::delete($from, $this->sql);
        break;
    }

    if (!empty($this->options['joins']) && $this->executeType === 'select') {
      $SQLs['join'] = Statement::join($this->options['joins']);
    }

    if (count($this->options['conditions']) > 0 || count($this->filterNullConditions) > 0) {
      // 过滤值为空的 filterNull 条件
      $this->filterNullConditions = array_filter($this->filterNullConditions, function ($item) {
        return !is_null($item['value']) && !empty($item['value']);
      });

      if (count($this->options['conditions'])) {
        $whereSql = Statement::where($this->options['conditions']);
        $SQLs['condition'] = $this->executeType ? "WHERE {$whereSql}" : $whereSql;
      }
    }

    if (!empty($this->options['orders'])) {
      $SQLs['order'] = Statement::order($this->options['orders']);
    }
    if ($this->options['pagination'] && $this->executeType != "delete") {
      if (!is_null($this->options['pagination']['limit']) || !is_null($this->options['pagination']['offset'])) {
        $SQLs['pagination'] = Statement::pagination($this->options['pagination']['limit'], $this->options['pagination']['offset']);
      }
    }

    if ($this->options['groupBy']) {
      $SQLs['groupBy'] = Statement::groupBy($this->options['groupBy']);
    }

    $SQLs = array_filter($SQLs, function ($item) {
      return $item;
    });

    return join(" ", $SQLs);
  }
  /**
   * 创建原始 SQL 表达式包装器
   * 
   * 将字符串标记为原始 SQL，插入到查询中时不会被转义或加引号。
   * 适用于需要插入 SQL 函数调用、表达式等场景。
   * 
   * @param string $sql 原始 SQL 表达式
   * @return Statement 返回 Statement 对象，可传入 insert/update/where 等方法
   * 
   * @example
   * // INSERT 中使用 NOW() 函数
   * $query->from('users')->insert([
   *     'name'       => 'John',
   *     'created_at' => $query->raw('NOW()'),
   * ]);
   * 
   * // 聚合查询中使用表达式
   * $query->from('orders')->addSelect($query->raw('price * quantity as total'));
   * 
   * @warning 传入 raw 的值未经任何过滤，务必确保 SQL 来自可信来源，避免 SQL 注入风险
   */
  function raw($sql)
  {
    return new Statement($sql);
  }
  /**
   * 设置查询的主表
   * 
   * 指定查询操作的目标数据表，支持表别名
   * 
   * @example
   * // 设置用户表
   * ->from('users')
   * 
   * // 设置带别名的表
   * ->from('users', 'u')
   * 
   * @param string $tableName 表名
   * @param string|null $ASName 表别名（可选）
   * @return $this
   */
  function from($tableName, $ASName = null)
  {
    $this->options['from']['tableName'] = $tableName;
    $this->options['from']['asName'] = $ASName;
    return $this;
  }
  /**
   * 设置子查询作为数据源
   * 
   * 使用子查询或闭包作为查询的数据源
   * 
   * @example
   * // 使用子查询作为数据源
   * ->fromSub(function($query) {
   *     return $query->select('id', 'name')->from('users');
   * }, 'subquery')
   * 
   * @param callable|Query $callableOrQuery 子查询或闭包
   * @param string|null $ASName 子查询别名（可选）
   * @return $this
   */
  function fromSub($callableOrQuery, $ASName = null)
  {
    $this->options['from']['tableName'] = $callableOrQuery;
    $this->options['from']['asName'] = $ASName;

    return $this;
  }
  /**
   * 添加 JOIN 子句
   *
   * 在查询中添加 JOIN 关联，支持 INNER/LEFT/RIGHT JOIN 及其 ON 条件。
   * 多次调用可叠加多个 JOIN。ON 条件中的列名自动添加 `` 包围。
   *
   * @param string $table    关联表名，支持 `表名 AS 别名` 格式自动解析
   * @param string $first    ON 条件左侧列名（如 'users.id'）
   * @param string $operator 比较运算符（如 '='）
   * @param string $second   ON 条件右侧列名（如 'profiles.user_id'）
   * @param string $type     JOIN 类型：'INNER' | 'LEFT' | 'RIGHT'，默认 'INNER'
   * @return $this
   */
  function join($table, $first, $operator, $second, $type = 'INNER')
  {
    $this->executeType = $this->executeType ?: "select";

    $alias = null;
    if (stripos($table, ' as ') !== false) {
      $parts = preg_split('/\s+as\s+/i', $table);
      $table = $parts[0];
      $alias = $parts[1];
    }

    $this->options['joins'][] = [
      'type'     => strtoupper($type),
      'table'    => $table,
      'alias'    => $alias,
      'first'    => $first,
      'operator' => $operator,
      'second'   => $second,
    ];

    return $this;
  }
  /**
   * 添加 LEFT JOIN 子句
   * @param string $table 关联表名
   * @param string $first ON 条件左侧
   * @param string $operator 比较运算符
   * @param string $second ON 条件右侧
   * @return $this
   */
  function leftJoin($table, $first, $operator, $second)
  {
    return $this->join($table, $first, $operator, $second, 'LEFT');
  }
  /**
   * 添加 RIGHT JOIN 子句
   * @param string $table 关联表名
   * @param string $first ON 条件左侧
   * @param string $operator 比较运算符
   * @param string $second ON 条件右侧
   * @return $this
   */
  function rightJoin($table, $first, $operator, $second)
  {
    return $this->join($table, $first, $operator, $second, 'RIGHT');
  }
  /**
   * 添加 INNER JOIN 子句
   * @param string $table 关联表名
   * @param string $first ON 条件左侧
   * @param string $operator 比较运算符
   * @param string $second ON 条件右侧
   * @return $this
   */
  function innerJoin($table, $first, $operator, $second)
  {
    return $this->join($table, $first, $operator, $second, 'INNER');
  }
  /**
   * 设置查询字段
   *
   * 指定要查询的字段列表，支持多个参数
   * 
   * @example
   * // 查询指定字段
   * ->select('id', 'name', 'email')
   * 
   * // 查询所有字段
   * ->select('*')
   * 
   * @param mixed ...$column 字段名列表
   * @return $this
   */
  function select(...$column)
  {
    $this->executeType = "select";

    $this->addSelect(...$column);

    return $this;
  }
  /**
   * 设置原始SQL查询字段
   * 
   * 使用原始SQL表达式作为查询字段
   * 
   * @example
   * // 使用SQL表达式
   * ->selectRaw('COUNT(*) as total')
   * 
   * // 使用计算字段
   * ->selectRaw('price * quantity as total_price')
   * 
   * @param string $columnSQL 原始SQL字段表达式
   * @return $this
   */
  function selectRaw($columnSQL)
  {
    $this->executeType = "select";

    $this->options['select']['fields'][] = [
      "type" => "raw",
      "value" => $columnSQL
    ];

    return $this;
  }
  /**
   * 添加查询字段
   * 
   * 向现有查询字段列表中添加新字段
   * 自动识别字段类型（普通字段、原始SQL、带别名字段、SQL 实例）
   * 
   * @example
   * // 添加普通字段
   * ->addSelect('created_at')
   * 
   * // 添加带别名字段
   * ->addSelect('name as username')
   * 
   * // 添加多个字段
   * ->addSelect('field1', 'field2', 'field3')
   * 
   * @param mixed ...$column 字段名或表达式
   * @return $this
   */
  function addSelect(...$column)
  {
    array_push($this->options['select']['fields'], ...array_map(function ($fieldItem) {
      if ($fieldItem instanceof Statement) {
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
  /**
   * 设置子查询作为查询字段
   * 
   * 使用子查询的结果作为查询的一个字段
   * 
   * @example
   * // 使用子查询作为字段
   * ->selectSub(function($query) {
   *     return $query->selectRaw('COUNT(*)')->from('orders');
   * }, 'order_count')
   * 
   * @param callable|Query $callbackOrQuery 子查询或闭包
   * @param string $asName 字段别名
   * @return $this
   */
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
   * 设置去重查询
   * 
   * 查询结果去重，可指定去重字段
   * 
   * @example
   * // 简单去重
   * ->distinct()
   * 
   * // 按指定字段去重
   * ->distinct('category', 'status')
   * 
   * @param mixed ...$column 去重字段列表（可选）
   * @return $this
   */
  function distinct(...$column)
  {
    $this->executeType = "select";

    $this->options['select']['distinct'] = true;
    if ($column) {
      array_push($this->options['select']['fields'], ...array_map(function ($col) {
        return [
          "type" => "name",
          "value" => $col
        ];
      }, $column));
    }

    return $this;
  }
  /**
   * 设置排序条件
   * 
   * 按指定字段和方向排序查询结果
   * 
   * @example
   * // 升序排序
   * ->orderBy('created_at', 'ASC')
   * 
   * // 降序排序
   * ->orderBy('price', 'DESC')
   * 
   * // 多字段排序
   * ->orderBy('category', 'ASC')
   * ->orderBy('price', 'DESC')
   * 
   * @param string $column 排序字段
   * @param string $by 排序方向，'ASC' 或 'DESC'
   * @return $this
   */
  function orderBy($column, $by = "ASC")
  {
    $this->options['orders'][] = [
      "field" => $column,
      "by" => $by,
      "type" => "general"
    ];

    return $this;
  }
  /**
   * 设置原始SQL排序条件
   * 
   * 使用原始SQL表达式进行排序
   * 
   * @example
   * // 使用SQL表达式排序
   * ->orderByRaw('RAND()')
   * 
   * // 使用复杂排序条件
   * ->orderByRaw('FIELD(status, "active", "pending", "inactive")')
   * 
   * @param string $rawSQL 原始SQL排序表达式
   * @return $this
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
   * 设置随机排序
   * 
   * 对查询结果进行随机排序
   * 
   * @example
   * // 简单随机排序
   * ->orderRandom()
   * 
   * // 带种子的随机排序（保证可重复性）
   * ->orderRandom(12345)
   * 
   * @param mixed $seed 随机种子（可选）
   * @return $this
   */
  function orderRandom($seed = null)
  {
    $this->options['orders'][] = [
      "field" => null,
      "by" => $seed,
      "type" => "random"
    ];

    return $this;
  }
  /**
   * 设置分组条件
   * 
   * 按指定字段对查询结果进行分组
   * 
   * @example
   * // 单字段分组
   * ->groupBy('category')
   * 
   * // 多字段分组
   * ->groupBy('year', 'month')
   * 
   * @param mixed ...$column 分组字段列表
   * @return $this
   */
  function groupBy(...$column)
  {
    if (!$this->options['groupBy'])
      $this->options['groupBy'] = [];

    array_push($this->options['groupBy'], ...$column);

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
   * @see Statement 自定义 SQL 表达式类
   */
  function groupByRaw($rawSQL)
  {
    return $this->groupBy(new Statement($rawSQL));
  }
  /**
   * 设置查询结果数量限制（LIMIT）
   * 
   * 限制查询返回的最大记录数，对应 SQL LIMIT 子句
   * 
   * @param int|Statement $value 最多返回的记录数量
   * @return $this 返回当前实例以支持链式调用
   * 
   * @example
   * // 获取前 10 条记录
   * $query->from('users')->limit(10)->get();
   * 
   * @see take() 别名方法
   * @see offset() 配合使用实现分页
   * @see page() 更便捷的分页方式
   */
  function limit($value)
  {
    $this->options['pagination']['limit'] = $value;

    return $this;
  }
  /**
   * 设置查询结果数量限制（LIMIT 的别名）
   * 
   * 功能与 limit() 完全一致，提供更语义化的方法名
   * 
   * @param int|Statement $value 最多获取的记录数量
   * @return $this 返回当前实例以支持链式调用
   * 
   * @example
   * $query->from('users')->take(5)->get();
   * 
   * @see limit() 原始方法
   */
  function take($value)
  {
    return $this->limit($value);
  }
  /**
   * 设置查询结果的偏移量（OFFSET）
   * 
   * 跳过指定数量的记录后开始返回结果，对应 SQL OFFSET 子句。
   * 通常与 limit() 配合使用实现分页。
   * 
   * @param int|Statement $value 跳过的记录数量（偏移值）
   * @return $this 返回当前实例以支持链式调用
   * 
   * @example
   * // 跳过前 10 条，获取接下来的 10 条（第 2 页）
   * $query->from('users')->offset(10)->limit(10)->get();
   * 
   * @see skip() 别名方法
   * @see limit() 配合使用
   * @see page() 更便捷的分页方式
   */
  function offset($value)
  {
    $this->options['pagination']['offset'] = $value;

    return $this;
  }
  /**
   * 设置查询结果的偏移量（OFFSET 的别名）
   * 
   * 功能与 offset() 完全一致，命名更直观
   * 
   * @param int|Statement $value 跳过的记录数量
   * @return $this 返回当前实例以支持链式调用
   * 
   * @example
   * $query->from('users')->skip(20)->take(10)->get();
   * 
   * @see offset() 原始方法
   * @see limit() / take() 配合使用
   */
  function skip($value)
  {
    return $this->offset($value);
  }
  /**
   * 基于页码的便捷分页
   * 
   * 根据页码和每页数量自动计算 offset 值。
   * 等价于同时调用 limit($perPage) 和 offset(($page - 1) * $perPage)。
   * 
   * @param int $page    页码，从 1 开始（小于 1 时视为第 1 页）
   * @param int $perPage 每页记录数，默认 10
   * @return $this 返回当前实例以支持链式调用
   * 
   * @example
   * // 获取第 3 页，每页 20 条
   * $query->from('users')->page(3, 20)->get();
   * 
   * @see limit() / offset() 手动分页
   * @see paginate() 更完整的分页查询（含总数统计）
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
   * 分页查询（含总数统计）
   * 
   * 克隆当前查询实例执行 COUNT 统计总数，然后执行当前查询获取当前页数据，
   * 返回包含数据、页码、总数等信息的 Paginator 对象。
   * 需配合 limit()/offset() 或 page() 设置分页参数。
   * 
   * @param array $params 预处理参数，传递给底层 count() 和 get() 调用
   * @return Paginator 分页结果对象，包含 items、page、perPage、total 等信息
   * 
   * @example
   * // 手动设置 limit/offset
   * $paginator = $query->from('users')->limit(15)->paginate();
   * 
   * // 配合 page() 使用
   * $paginator = $query->from('users')->page(1, 20)->paginate();
   * foreach ($paginator->getItems() as $user) {
   *     echo $user['name'];
   * }
   * echo "共 {$paginator->getTotal()} 条记录";
   * 
   * @note 内部通过克隆查询实例执行 COUNT，不会影响原查询条件
   * @note 页码通过 offset / limit 反算得出，仅作估算参考
   * @see page() 设置分页参数
   * @see Paginator 分页结果封装类
   */
  function paginate($params = [])
  {
    $TotalQuery = clone $this;
    $Total = $TotalQuery->count("*", $params);

    $perPage = $this->options['pagination']['limit'] ?: 10;
    $offset = $this->options['pagination']['offset'] ?: 0;
    $page = ($offset / $perPage) + 1; // 简单推算当前页

    $Items = $this->get($params);

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

    // 数组列：递归展开每个条件元素，各自负责插入 boolean 分隔符
    if (is_array($column)) {
      foreach ($column as $fieldName => $param) {
        if (is_string($fieldName)) {
          $this->addWhere($fieldName, $operator, $param, $boolean);
        } else {
          $paramCount = count($param);
          $columnOperator = $operator;
          $columnBoolean = $boolean;
          $columnValue = $paramCount >= 3 ? $param[2] : $param[1];
          if ($paramCount >= 3) {
            $columnOperator = $param[1];
          }
          if ($paramCount >= 4) {
            $columnBoolean = $param[3];
          }

          $this->addWhere($param[0], $columnOperator, $columnValue, $columnBoolean);
        }
      }
      return $this;
    }

    // 非首个条件时插入 boolean 分隔符（AND/OR）
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

    if ($column instanceof Query || is_callable($column)) {
      $type = "sub";
      $value = $column;
      $column = null;
      $operator = null;
    } else if (is_string($column) && preg_match('/\s+(?:=|!=|<>|<=|>=|<|>|\(|\)|BETWEEN|IN|LIKE|IS\s+NULL|IS\s+NOT\s+NULL|REGEXP)(?:\s+|$)/i', $column)) {
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

    // IN / NOT IN 数组值 → 占位符绑定，防止 SQL 注入
    if (is_array($value) && !is_callable($value) && !($value instanceof Query)) {
      if ($type === 'comparsion') {
        // comparsion 类型 + 数组值 → 自动转为 IN
        $operator = 'IN';
        $type = 'rangeTesting';
      }
      if ($type === 'rangeTesting' && in_array($operator, ['IN', 'NOT IN'], true)) {
        $placeholders = [];
        foreach ($value as $val) {
          $key = $this->generateBindingKey();
          $placeholders[] = $key;
          $this->bindings[$key] = $val;
        }
        $value = new Statement(implode(', ', $placeholders));
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

    return $this;
  }
  /**
   * 基础 WHERE 条件
   *
   * 支持多种调用方式：
   * - where('column', 'value')                    // 默认操作符 '='
   * - where('column', 'operator', 'value')        // 指定操作符
   * - where(['col1' => 'val1', 'col2' => 'val2']) // 多条件数组
   * - where(['column','operator',"value'], ['column',"value"]]) // 多条件数组
   * - where(function ($q) { ... })                 // 闭包分组
   *
   * @example
   * // 闭包分组 - 括号包裹
   * $query->from('users')
   *     ->where('status', 'active')
   *     ->where(function ($q) {
   *         $q->where('age', '>', 18)->orWhere('role', 'vip');
   *     });
   * // → WHERE `status` = 'active' AND (`age` > '18' OR `role` = 'vip')
   *
   * @param mixed $column 列名、条件数组或闭包
   * @param mixed $valueOrOperator 值或操作符
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function where($column, $valueOrOperator = null, $value = null)
  {
    list($operator, $value) = $this->resolveWhereArgs($valueOrOperator, $value, func_num_args());
    return $this->addWhere($column, $operator, $value, "AND");
  }

  /**
   * 解析 where/orWhere 的 2/3 参数重载
   * 
   * 支持两种调用方式：
   * - 2 参数: where('column', 'value') → 操作符默认为 '='
   * - 3 参数: where('column', '>', 'value') → 使用指定操作符
   * 
   * @param mixed       $op  操作符（3参时）或值（2参时）
   * @param mixed       $val 值（3参时有效，2参时为 null）
   * @param int         $argc 实际传入的参数数量（通过 func_num_args() 获取）
   * @return array      返回 [$operator, $value]，操作符会被转为大写
   */
  private function resolveWhereArgs($op, $val, $argc)
  {
    if ($argc >= 3) {
      return [strtoupper($op), $val];
    }
    return [null, $op];
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
   * BETWEEN 范围条件
   * 
   * 筛选列值在指定区间 [min, max] 内的记录
   * 
   * @example
   * // 查询价格在 100 到 500 之间的商品
   * $query->from('products')->whereBetween('price', 100, 500)->get();
   * 
   * @param string $column 列名
   * @param mixed  $min    区间最小值
   * @param mixed  $max    区间最大值
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereNotBetween() 取反操作
   * @see orWhereBetween() OR 连接版本
   */
  function whereBetween($column, $min, $max)
  {
    return $this->addWhere($column, "BETWEEN", [
      $min,
      $max
    ]);
  }
  /**
   * NOT BETWEEN 范围条件
   * 
   * 筛选列值不在指定区间内的记录
   * 
   * @example
   * // 查询价格不在 100~500 区间的商品
   * $query->from('products')->whereNotBetween('price', 100, 500)->get();
   * 
   * @param string $column 列名
   * @param mixed  $min    区间最小值
   * @param mixed  $max    区间最大值
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereBetween() 正选操作
   * @see orWhereNotBetween() OR 连接版本
   */
  function whereNotBetween($column, $min, $max)
  {
    return $this->addWhere($column, "NOT BETWEEN", [
      $min,
      $max
    ]);
  }
  /**
   * IN 列表条件
   * 
   * 筛选列值在给定值集合或子查询结果中的记录。
   * 数组值会自动生成占位符绑定，防止 SQL 注入。
   * 
   * @example
   * // 值列表
   * $query->from('users')->whereIn('status', ['active', 'pending', 'verified'])->get();
   * 
   * // 子查询
   * $subQuery = Query::table('orders')->select('user_id')->where('amount', '>', 1000);
   * $query->from('users')->whereIn('id', $subQuery)->get();
   * 
   * @param string       $column       列名
   * @param array|Query  $valueOrQuery IN 的值数组或子查询
   * @return $this 返回当前实例以支持链式调用
   * 
   * @note 传入 Query 实例时生成子查询，传入数组时使用占位符绑定
   * @see whereNotIn() 取反操作
   * @see orWhereIn() OR 连接版本
   */
  function whereIn($column, $valueOrQuery)
  {
    return $this->addWhere($column, "IN", $valueOrQuery);
  }
  /**
   * NOT IN 列表条件
   * 
   * 筛选列值不在给定值集合或子查询结果中的记录
   * 
   * @example
   * // 排除指定状态
   * $query->from('users')->whereNotIn('status', ['banned', 'deleted'])->get();
   * 
   * // 子查询排除
   * $blockedIds = Query::table('blacklist')->select('user_id');
   * $query->from('users')->whereNotIn('id', $blockedIds)->get();
   * 
   * @param string       $column       列名
   * @param array|Query  $valueOrQuery NOT IN 的值数组或子查询
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereIn() 正选操作
   * @see orWhereNotIn() OR 连接版本
   */
  function whereNotIn($column, $valueOrQuery)
  {
    return $this->addWhere($column, "NOT IN", $valueOrQuery);
  }
  /**
   * IS NULL 条件
   * 
   * 筛选列值为 NULL 的记录
   * 
   * @example
   * // 查询未设置邮箱的用户
   * $query->from('users')->whereNull('email')->get();
   * 
   * @param string $column 列名
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereNotNull() 取反操作
   * @see orWhereNull() OR 连接版本
   */
  function whereNull($column)
  {
    return $this->addWhere($column, "IS NULL", null);
  }
  /**
   * IS NOT NULL 条件
   * 
   * 筛选列值不为 NULL 的记录
   * 
   * @example
   * // 查询已设置手机号的用户
   * $query->from('users')->whereNotNull('phone')->get();
   * 
   * @param string $column 列名
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereNull() 取反操作
   * @see orWhereNotNull() OR 连接版本
   */
  function whereNotNull($column)
  {
    return $this->addWhere($column, "IS NOT NULL", null);
  }
  /**
   * LIKE 模糊匹配条件
   * 
   * 使用 SQL LIKE 进行模式匹配。通配符 % 和 _ 需要在传入值中自行添加。
   * 
   * @example
   * // 前缀匹配
   * $query->from('users')->whereLike('name', 'John%')->get();
   * 
   * // 包含匹配
   * $query->from('products')->whereLike('description', '%keyword%')->get();
   * 
   * @param string $column 列名
   * @param string $value  匹配模式（需自行添加 % 和 _ 通配符）
   * @return $this 返回当前实例以支持链式调用
   * 
   * @note 传入值不会被自动添加通配符，需手动添加
   * @see whereNotLike() 取反操作
   * @see orWhereLike() OR 连接版本
   */
  function whereLike($column, $value)
  {
    return $this->addWhere($column, "LIKE", $value);
  }
  /**
   * NOT LIKE 不匹配条件
   * 
   * 筛选不匹配指定模式的记录
   * 
   * @example
   * // 排除测试用户
   * $query->from('users')->whereNotLike('email', '%@test.com')->get();
   * 
   * @param string $column 列名
   * @param string $value  不匹配的模式
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereLike() 正选操作
   * @see orWhereNotLike() OR 连接版本
   */
  function whereNotLike($column, $value)
  {
    return $this->addWhere($column, "NOT LIKE", $value);
  }
  /**
   * 列与列比较条件
   * 
   * 比较两个列的值，而非列与常量比较。
   * 支持两种调用方式：
   * - whereColumn('col1', 'col2')              → col1 = col2
   * - whereColumn('col1', '>', 'col2')         → col1 > col2
   * 
   * @example
   * // 查找余额低于信用额度的用户
   * $query->from('users')->whereColumn('balance', '<', 'credit_limit')->get();
   * 
   * // 查找修改时间大于创建时间的记录
   * $query->from('posts')->whereColumn('updated_at', '>', 'created_at')->get();
   * 
   * @param string      $column1             第一个列名
   * @param string      $operatorOrColumn2   操作符（3参时）或第二个列名（2参时）
   * @param string|null $column2             第二个列名（3参时使用）
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see orWhereColumn() OR 连接版本
   */
  function whereColumn($column1, $operatorOrColumn2, $column2 = null)
  {
    $args = func_num_args();
    $operator = $args === 3 ? $operatorOrColumn2 : "=";
    $column2 = $args === 3 ? $column2 : $operatorOrColumn2;

    return $this->addWhere($column1, $operator, $column2, "AND", null, "columnComparsion");
  }
  /**
   * 日期/时间函数条件通用方法
   * 
   * 将列名包装为 SQL 日期/时间函数调用，如 DATE(`column`)、YEAR(`column`) 等。
   * 所有 whereDate/whereYear/... 及其 OR 变体均通过此方法实现。
   * 
   * @param string $func     日期/时间 SQL 函数名（DATE/YEAR/MONTH/DAY/TIME/HOUR/MINUTE/SECOND）
   * @param string $column   数据库列名（会自动加上反引号）
   * @param string $operator 比较操作符（=、>、< 等）
   * @param mixed  $value    比较值
   * @param string $boolean  逻辑连接符（AND/OR），默认 AND
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereDate() / orWhereDate() 等 16 个日期/时间条件方法
   */
  private function addDateWhere($func, $column, $operator, $value, $boolean = "AND")
  {
    return $this->addWhere("{$func}(`$column`)", $operator, $value, $boolean, $func, "func");
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("DATE", $column, $operator, $value);
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("YEAR", $column, $operator, $value);
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("MONTH", $column, $operator, $value);
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("DAY", $column, $operator, $value);
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("TIME", $column, $operator, $value);
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("HOUR", $column, $operator, $value);
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("MINUTE", $column, $operator, $value);
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("SECOND", $column, $operator, $value);
  }

  /**
   * EXISTS 子查询条件
   * 
   * 使用 EXISTS 子查询，仅当子查询存在结果时当前记录才被选中。
   * 常用于关联表的存在性判断。
   * 
   * @example
   * // 查询有订单的用户
   * $query->from('users')->whereExists(function ($q) {
   *     $q->from('orders')->whereColumn('orders.user_id', 'users.id');
   * })->get();
   * 
   * // 使用 Query 实例
   * $subQuery = Query::table('orders')->where('status', 'paid');
   * $query->from('users')->whereExists($subQuery)->get();
   * 
   * @param Query|callable $queryOrCallable 子查询实例或闭包
   * @return $this 返回当前实例以支持链式调用
   * 
   * @note 闭包接收一个新的 Query 实例作为参数
   * @see whereNotExists() 取反操作
   * @see orWhereExists() OR 连接版本
   */
  function whereExists($queryOrCallable)
  {
    return $this->addWhere(null, null, $queryOrCallable, "AND", "EXISTS", "func");
  }

  /**
   * NOT EXISTS 子查询条件
   * 
   * 使用 NOT EXISTS 子查询，仅当子查询无结果时当前记录才被选中
   * 
   * @example
   * // 查询没有订单的用户
   * $query->from('users')->whereNotExists(function ($q) {
   *     $q->from('orders')->whereColumn('orders.user_id', 'users.id');
   * })->get();
   * 
   * @param Query|callable $queryOrCallable 子查询实例或闭包
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereExists() 正选操作
   * @see orWhereNotExists() OR 连接版本
   */
  function whereNotExists($queryOrCallable)
  {
    return $this->addWhere(null, null, $queryOrCallable, "AND", "NOT EXISTS", "func");
  }
  /**
   * OR WHERE 条件
   *
   * 用法与 where() 相同，但使用 OR 连接。
   * 支持闭包分组：传入 callable 时，闭包内的所有条件会自动用括号包裹。
   *
   * @example
   * // 基础用法
   * $query->from('users')->where('status', 'active')->orWhere('role', 'admin');
   * // → WHERE `status` = 'active' OR `role` = 'admin'
   *
   * // 闭包分组
   * $query->from('users')
   *     ->where('status', 'active')
   *     ->orWhere(function ($q) {
   *         $q->where('votes', '>', 100)->where('title', '<>', 'Admin');
   *     });
   * // → WHERE `status` = 'active' OR (`votes` > '100' AND `title` <> 'Admin')
   *
   * @param mixed $column 列名、条件数组或闭包
   * @param mixed $valueOrOperator 值或操作符
   * @param mixed $value 值（当使用三个参数时）
   * @return $this
   */
  function orWhere($column, $valueOrOperator = null, $value = null)
  {
    list($operator, $value) = $this->resolveWhereArgs($valueOrOperator, $value, func_num_args());
    return $this->addWhere($column, $operator, $value, "OR");
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
   * OR BETWEEN 范围条件
   * 
   * 以 OR 连接，筛选列值在指定区间内的记录。
   * 用法与 whereBetween() 相同，但使用 OR 连接符。
   * 
   * @example
   * $query->from('products')
   *     ->where('category', 'electronics')
   *     ->orWhereBetween('price', 100, 500)
   *     ->get();
   * 
   * @param string $column 列名
   * @param mixed  $min    区间最小值
   * @param mixed  $max    区间最大值
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereBetween() AND 连接版本
   * @see orWhereNotBetween() 取反操作
   */
  function orWhereBetween($column, $min, $max)
  {
    return $this->addWhere($column, "BETWEEN", [$min, $max], "OR");
  }

  /**
   * OR NOT BETWEEN 范围条件
   * 
   * 以 OR 连接，筛选列值不在指定区间内的记录
   * 
   * @param string $column 列名
   * @param mixed  $min    区间最小值
   * @param mixed  $max    区间最大值
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see orWhereBetween() 正选操作
   * @see whereNotBetween() AND 连接版本
   */
  function orWhereNotBetween($column, $min, $max)
  {
    return $this->addWhere($column, "NOT BETWEEN", [$min, $max], "OR");
  }

  /**
   * OR IN 列表条件
   * 
   * 以 OR 连接，筛选列值在给定值集合中的记录
   * 
   * @example
   * $query->from('users')
   *     ->where('role', 'admin')
   *     ->orWhereIn('status', ['active', 'pending'])
   *     ->get();
   * 
   * @param string       $column       列名
   * @param array|Query  $valueOrQuery IN 的值数组或子查询
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereIn() AND 连接版本
   * @see orWhereNotIn() 取反操作
   */
  function orWhereIn($column, $valueOrQuery)
  {
    return $this->addWhere($column, "IN", $valueOrQuery, "OR");
  }

  /**
   * OR NOT IN 列表条件
   * 
   * 以 OR 连接，筛选列值不在给定值集合中的记录
   * 
   * @param string       $column       列名
   * @param array|Query  $valueOrQuery NOT IN 的值数组或子查询
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see orWhereIn() 正选操作
   * @see whereNotIn() AND 连接版本
   */
  function orWhereNotIn($column, $valueOrQuery)
  {
    return $this->addWhere($column, "NOT IN", $valueOrQuery, "OR");
  }

  /**
   * OR IS NULL 条件
   * 
   * 以 OR 连接，筛选列值为 NULL 的记录
   * 
   * @example
   * $query->from('users')
   *     ->where('status', 'active')
   *     ->orWhereNull('deleted_at')
   *     ->get();
   * 
   * @param string $column 列名
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereNull() AND 连接版本
   * @see orWhereNotNull() 取反操作
   */
  function orWhereNull($column)
  {
    return $this->addWhere($column, "IS NULL", null, "OR");
  }

  /**
   * OR IS NOT NULL 条件
   * 
   * 以 OR 连接，筛选列值不为 NULL 的记录
   * 
   * @param string $column 列名
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see orWhereNull() 取反操作
   * @see whereNotNull() AND 连接版本
   */
  function orWhereNotNull($column)
  {
    return $this->addWhere($column, "IS NOT NULL", null, "OR");
  }

  /**
   * OR LIKE 模糊匹配条件
   * 
   * 以 OR 连接，使用 LIKE 进行模糊匹配
   * 
   * @example
   * $query->from('users')
   *     ->where('role', 'admin')
   *     ->orWhereLike('name', 'John%')
   *     ->get();
   * 
   * @param string $column 列名
   * @param mixed  $value  匹配模式（需自行添加通配符）
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereLike() AND 连接版本
   * @see orWhereNotLike() 取反操作
   */
  function orWhereLike($column, $value)
  {
    return $this->addWhere($column, "LIKE", $value, "OR");
  }

  /**
   * OR NOT LIKE 不匹配条件
   * 
   * 以 OR 连接，筛选不匹配指定模式的记录
   * 
   * @param string $column 列名
   * @param mixed  $value  不匹配的模式
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see orWhereLike() 正选操作
   * @see whereNotLike() AND 连接版本
   */
  function orWhereNotLike($column, $value)
  {
    return $this->addWhere($column, "NOT LIKE", $value, "OR");
  }

  /**
   * OR 列与列比较条件
   * 
   * 以 OR 连接，比较两个列的值。
   * 支持两种调用方式：
   * - orWhereColumn('col1', 'col2')              → col1 = col2
   * - orWhereColumn('col1', '>', 'col2')         → col1 > col2
   * 
   * @example
   * $query->from('users')
   *     ->where('status', 'active')
   *     ->orWhereColumn('balance', '<', 'min_balance')
   *     ->get();
   * 
   * @param string      $column1             第一个列名
   * @param string      $operatorOrColumn2   操作符（3参时）或第二个列名（2参时）
   * @param string|null $column2             第二个列名（3参时使用）
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereColumn() AND 连接版本
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("DATE", $column, $operator, $value, "OR");
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("YEAR", $column, $operator, $value, "OR");
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("MONTH", $column, $operator, $value, "OR");
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("DAY", $column, $operator, $value, "OR");
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("TIME", $column, $operator, $value, "OR");
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("HOUR", $column, $operator, $value, "OR");
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("MINUTE", $column, $operator, $value, "OR");
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
    list($operator, $value) = $this->resolveWhereArgs($operatorOrValue, $value, func_num_args());
    return $this->addDateWhere("SECOND", $column, $operator, $value, "OR");
  }

  /**
   * OR EXISTS 子查询条件
   * 
   * 以 OR 连接，使用 EXISTS 子查询进行存在性判断
   * 
   * @example
   * $query->from('users')
   *     ->where('vip', 1)
   *     ->orWhereExists(function ($q) {
   *         $q->from('orders')->whereColumn('orders.user_id', 'users.id')
   *           ->where('amount', '>', 1000);
   *     })->get();
   * 
   * @param Query|callable $queryOrCallable 子查询实例或闭包
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see whereExists() AND 连接版本
   * @see orWhereNotExists() 取反操作
   */
  function orWhereExists($queryOrCallable)
  {
    return $this->addWhere(null, null, $queryOrCallable, "OR", "EXISTS", "func");
  }

  /**
   * OR NOT EXISTS 子查询条件
   * 
   * 以 OR 连接，使用 NOT EXISTS 子查询判断不存在
   * 
   * @param Query|callable $queryOrCallable 子查询实例或闭包
   * @return $this 返回当前实例以支持链式调用
   * 
   * @see orWhereExists() 正选操作
   * @see whereNotExists() AND 连接版本
   */
  function orWhereNotExists($queryOrCallable)
  {
    return $this->addWhere(null, null, $queryOrCallable, "OR", "NOT EXISTS", "func");
  }
  
  /**
   * 从数组中批量添加过滤条件，自动跳过空值
   *
   * 接收关联数组（如 $_GET），将键作为字段名、值作为条件值，
   * 自动过滤值为 null、空字符串的项，仅添加有效条件。
   * 适用于前端传入的搜索/筛选参数场景，无需控制器逐个判断。
   *
   * @example
   * // $_GET = ['age' => '18', 'name' => '', 'nickname' => '']
   * $query->from('users')->whereFilter($_GET)->get();
   * // → SELECT * FROM `users` WHERE `age` = '18'
   *
   * // 多个参数同时有效
   * // $_GET = ['age' => '18', 'role' => 'admin']
   * $query->from('users')->whereFilter($_GET)->get();
   * // → SELECT * FROM `users` WHERE `age` = '18' AND `role` = 'admin'
   *
   * @param array $data 关联数组，键为字段名，值为条件值
   * @param string $operator 逻辑连接符（AND/OR），默认 AND
   * @return $this
   *
   * @see where() 标准条件方法
   */
  function whereFilter($data, $operator = "AND")
  {
    foreach ($data as $fieldName => $value) {
      if ($value === null || $value === '') {
        continue;
      }
      $this->addWhere($fieldName, "=", $value, $operator);
    }

    return $this;
  }
  /**
   * 获取查询结果的第一条记录
   * 
   * 自动添加 LIMIT 1 约束后执行查询，返回第一条记录。
   * 若没有匹配记录，返回 false。
   * 
   * @param array $params 预处理参数，用于 SQL 中的占位符替换
   * @return array|false 返回关联数组形式的记录，无结果时返回 false
   * 
   * @example
   * // 获取 ID 为 1 的用户
   * $user = Query::table('users')->where('id', 1)->first();
   * if ($user) {
   *     echo $user['name'];
   * }
   * 
   * @note 此方法会强制添加 LIMIT 1，覆盖之前设置的 limit()
   * @note 返回 false 表示没有匹配记录，需用 === 判断
   * @see get() 获取所有记录
   * @see value() 直接获取单个列值
   */
  function first($params = [])
  {
    $this->executeType = "select";
    $this->limit(1);
    $this->sql = $this->generateSQL();
    $data = $this->databaseDriver->first($this->sql, $this->mergeBindings($params));
    return $data ?: false;
  }
  /**
   * 获取第一条记录的指定列值
   * 
   * 查询第一条记录并直接返回指定列的值，无需手动访问数组。
   * 记录不存在或列名不存在时返回 null。
   * 
   * @param string $column 要获取值的列名
   * @param array  $params 预处理参数
   * @return mixed|null 返回列值；记录不存在或列不存在时返回 null
   * 
   * @example
   * // 直接获取用户名
   * $name = Query::table('users')->where('id', 1)->value('name');
   * echo $name; // "John"
   * 
   * @note 底层调用 first()，因此会自动添加 LIMIT 1
   * @see first() 获取完整记录
   * @see pluck() 获取多行的单列值
   */
  function value($column, $params = [])
  {
    $data = $this->first($params);

    if (!is_array($data) && !Arr::isAssoc((array) $data))
      return null;
    if (!array_key_exists($column, $data))
      return null;

    return $data[$column];
  }
  /**
   * 获取所有查询结果
   * 
   * 执行查询并返回所有匹配的记录，每条记录为关联数组。
   * 
   * @param array $params 预处理参数，用于 SQL 中的占位符替换
   * @return array 返回二维数组，每行一个关联数组。无结果时返回空数组 []
   * 
   * @example
   * // 获取所有活跃用户
   * $users = Query::table('users')->where('status', 'active')->get();
   * foreach ($users as $user) {
   *     echo $user['name'];
   * }
   * 
   * @note 与 first() 不同，get() 没有强制 LIMIT 约束，会返回所有匹配数据
   * @see first() 获取第一条记录
   * @see cursor() 大数据集时使用游标逐行处理
   */
  function get($params = [])
  {
    $this->executeType = "select";
    $this->sql = $this->generateSQL();

    $data = $this->databaseDriver->all($this->sql, $this->mergeBindings($params));

    return $data;
  }
  /**
   * 提取指定列的值作为数组
   * 
   * 查询所有结果并提取指定列的值。可选择使用另一列作为结果数组的键名。
   * 内部使用 PHP 的 array_column() 实现。
   * 
   * @param string      $column   要提取值的列名
   * @param string|null $indexKey 作为数组键名的列名（可选，为 null 时使用数字索引）
   * @param array       $params   预处理参数
   * @return array 返回一维数组（无 indexKey）或关联数组（有 indexKey）
   * 
   * @example
   * // 提取名称列表 [0 => 'John', 1 => 'Jane', ...]
   * $names = Query::table('users')->pluck('name');
   * 
   * // 以 ID 为键的名称映射 [1 => 'John', 2 => 'Jane', ...]
   * $nameMap = Query::table('users')->pluck('name', 'id');
   * 
   * @note 此方法会清空之前的 select 设置，重新设置查询字段
   * @see value() 获取单条记录的单列值
   * @see get() 获取完整记录
   */
  function pluck($column, $indexKey = null, $params = [])
  {
    $this->options['select']['fields'] = [];
    $this->addSelect($column);
    if ($indexKey)
      $this->addSelect($indexKey);

    $this->executeType = "select";
    $this->sql = $this->generateSQL();

    $data = $this->databaseDriver->all($this->sql, $this->mergeBindings($params));

    return array_column($data, $column, $indexKey);
  }
  /**
   * 使用游标（Generator）逐行遍历查询结果
   * 
   * 创建 PDOStatement 游标逐行取回数据，通过 yield 返回生成器。
   * 适用于处理大量数据时避免一次性加载到内存中，显著降低内存占用。
   * 
   * @param array $params 预处理参数
   * @return \Generator 生成器，每次迭代 yield 一条关联数组形式的记录
   * 
   * @example
   * // 逐行处理百万级数据，内存友好
   * foreach ($query->from('logs')->cursor() as $row) {
   *     processLog($row);
   * }
   * 
   * @note 使用 PDO::CURSOR_SCROLL 游标模式，逐行 fetch
   * @note 生成器不支持 rewind，只能遍历一次
   * @see chunk() 分块处理替代方案
   * @see chunkStream() 基于 ID 的分块流式处理
   */
  function cursor($params = [])
  {
    $this->executeType = "select";
    $this->sql = $this->generateSQL();

    /**
     * @var \PDOStatement
     */
    $PDOStatement = $this->databaseDriver->prepare($this->sql, [
      \PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL
    ]);

    $allParams = $this->mergeBindings($params);
    if (!empty($allParams)) {
      $this->databaseDriver->bindValues($PDOStatement, $allParams);
    }

    $PDOStatement->execute();

    while ($record = $PDOStatement->fetch(\PDO::FETCH_ASSOC)) {
      yield $record;
    }
  }
  /**
   * 分块处理查询结果
   * 
   * 将结果集按指定大小分块，每次回调处理一个数据块。
   * 内部使用 page() + paginate() 实现分页，适用于批量处理。
   * 回调返回 false 可提前中断处理。
   * 
   * @param int      $size     每块的大小（记录数）
   * @param callable $callback 处理回调，签名: function(array $items, int $page): ?bool
   *                           - $items: 当前块的记录数组
   *                           - $page:  当前页码（从 1 开始）
   *                           - 返回 false 可中断处理，返回其他值继续
   * @return bool 处理完成返回 true，被中断返回 false
   * 
   * @example
   * // 每 100 条处理一次，返回 false 终止
   * $query->from('users')->chunk(100, function ($users, $page) {
   *     foreach ($users as $user) {
   *         sendEmail($user['email']);
   *     }
   *     if ($page >= 10) return false; // 最多处理 10 页
   * });
   * 
   * @note 使用 notReset() 避免每次分页重置查询条件
   * @note 大偏移量场景下 OFFSET 性能较差，考虑使用 chunkById()
   * @see chunkById() 基于 ID 的高效分块处理
   * @see chunkStream() 基于生成器的逐条流式处理
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
   * 基于 ID 的分块处理（高性能）
   * 
   * 使用 WHERE id > lastId 替代 OFFSET，避免大偏移量带来的性能下降。
   * 适用于有自增 ID 主键的大表分块处理。
   * 
   * @param int      $size     每块的大小
   * @param callable $callback 处理回调，签名: function(array $items): ?bool
   *                           - $items: 当前块的记录数组
   *                           - 返回 false 可中断处理
   * @param string   $column   用于分块的递增列名，默认 'id'
   * @return bool 处理完成返回 true，被中断返回 false
   * 
   * @example
   * // 按 ID 升序分块处理，避免 OFFSET 性能问题
   * $query->from('logs')->chunkById(500, function ($logs) {
   *     foreach ($logs as $log) {
   *         archive($log);
   *     }
   * });
   * 
   * @note 自动按指定列升序排序，并移除已有的同名排序列
   * @note 每块最后一条记录的列值作为下一次查询的起始点
   * @see chunk() 基于 OFFSET 的分块处理
   * @see chunkStream() 生成器版本
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
   * 基于 ID 的分块流式处理（生成器）
   * 
   * 结合了 chunkById 的高性能（WHERE id > lastId）和 cursor 的生成器模式，
   * 逐条 yield 记录，内存占用极小。
   * 
   * @param int    $size   每块的大小
   * @param string $column 用于分块的递增列名，默认 'id'
   * @return \Generator 生成器，每次迭代 yield 一条记录
   * 
   * @example
   * // 逐条处理百万级数据
   * foreach ($query->from('logs')->chunkStream(1000, 'id') as $record) {
   *     processRecord($record);
   * }
   * 
   * @note 内部以块为单位查询，但逐条 yield 返回
   * @see chunkById() 回调版本
   * @see cursor() 更简单的游标遍历（无分块策略）
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
   * 聚合查询通用方法
   * 
   * 通过 addSelect() 添加聚合函数表达式后执行 first() 获取单值结果。
   * 所有 count/max/min/avg/sum 方法均委托此方法执行。
   * 
   * @param string $func   聚合函数名（COUNT/MAX/MIN/AVG/SUM）
   * @param string $column 目标列名（传入 raw() 表达式）
   * @param array  $params 预处理参数
   * @return mixed|false 聚合结果值，查询失败（无结果）时返回 false
   * 
   * @see count() / max() / min() / avg() / sum() 具体聚合方法
   */
  private function aggregate($func, $column, $params = [])
  {
    $this->addSelect($this->raw("{$func}({$column})"));
    $data = $this->first($params);
    return $data === false ? false : $data[array_key_first($data)];
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
  function count($column = "*", $params = [])
  {
    return $this->aggregate("COUNT", $column, $params);
  }

  /**
   * 获取指定列的最大值
   * 
   * 执行 MAX 聚合查询，返回指定列中的最大值
   * 适用于数值、日期等可比较的数据类型
   * 
   * @example
   * // 获取价格最大值
   * ->max('price')
   * 
   * // 获取最新创建时间
   * ->max('created_at')
   * 
   * @param string $column 要计算最大值的列名
   * @param array  $params 预处理参数
   * @return mixed|false 返回最大值，查询失败返回 false
   * 
   * @note 此方法会修改 SELECT 子句，添加 MAX 聚合函数
   * @note 如果查询结果为空，返回 false
   * @see first() 用于获取第一条记录
   */
  function max($column, $params = [])
  {
    return $this->aggregate("MAX", $column, $params);
  }

  /**
   * 获取指定列的最小值
   * 
   * 执行 MIN 聚合查询，返回指定列中的最小值
   * 
   * @example
   * // 获取最低价格
   * ->min('price')
   * 
   * // 获取最早注册时间
   * ->min('created_at')
   * 
   * @param string $column 要计算最小值的列名
   * @param array  $params 预处理参数
   * @return mixed|false 返回最小值，查询失败或结果为空时返回 false
   * 
   * @note 此方法会修改 SELECT 子句，添加 MIN 聚合函数
   * @see aggregate() 内部聚合查询实现
   * @see max() 获取最大值
   */
  function min($column, $params = [])
  {
    return $this->aggregate("MIN", $column, $params);
  }

  /**
   * 计算指定列的平均值
   * 
   * 执行 AVG 聚合查询，返回指定列中所有值的算术平均值
   * 
   * @example
   * // 计算平均价格
   * ->avg('price')
   * 
   * // 按条件计算平均分
   * Query::table('scores')->where('subject', 'math')->avg('score')
   * 
   * @param string $column 要计算平均值的列名
   * @param array  $params 预处理参数
   * @return float|false 返回平均值（浮点数），查询失败或结果为空时返回 false
   * 
   * @note 此方法会修改 SELECT 子句，添加 AVG 聚合函数
   * @note NULL 值会被自动忽略，不参与计算
   * @see aggregate() 内部聚合查询实现
   */
  function avg($column, $params = [])
  {
    return $this->aggregate("AVG", $column, $params);
  }

  /**
   * 计算指定列的总和
   * 
   * 执行 SUM 聚合查询，返回指定列中所有值的总和
   * 
   * @example
   * // 计算订单总金额
   * ->sum('amount')
   * 
   * // 按用户计算消费总额
   * Query::table('orders')->where('user_id', $uid)->sum('amount')
   * 
   * @param string $column 要计算总和的列名
   * @param array  $params 预处理参数
   * @return float|int|false 返回总和，查询失败或结果为空时返回 false
   * 
   * @note 此方法会修改 SELECT 子句，添加 SUM 聚合函数
   * @note NULL 值会被自动忽略，不参与计算
   * @see aggregate() 内部聚合查询实现
   */
  function sum($column, $params = [])
  {
    return $this->aggregate("SUM", $column, $params);
  }
  /**
   * EXISTS / NOT EXISTS 查询通用方法
   * 
   * 将当前查询包装为 EXISTS 或 NOT EXISTS 子查询，返回布尔值。
   * 内部先生成 SELECT SQL，再用 SELECT EXISTS(...) 或 SELECT NOT EXISTS(...) 包裹执行。
   * 
   * @param bool  $not    是否取反（true = NOT EXISTS，false = EXISTS）
   * @param array $params 预处理参数
   * @return bool 查询结果，true 表示存在（或不存在，取决于 $not）
   * 
   * @see exists() / notExists() 具体入口方法
   */
  private function checkExists($not, $params = [])
  {
    $this->executeType = "select";
    $this->sql = $this->generateSQL();
    $prefix = $not ? "NOT EXISTS" : "EXISTS";
    $this->sql = "SELECT {$prefix}({$this->sql}) as exist";
    $data = $this->databaseDriver->first($this->sql, $this->mergeBindings($params));
    return boolval($data[array_key_first($data)]);
  }

  /**
   * 查询是否存在
   *
   * 将当前查询包装为 EXISTS 子查询，高效判断是否有满足条件的记录
   *
   * @example
   * // 检查是否存在活跃用户
   * $exists = Query::table('users')->where('status', 'active')->exists();
   *
   * @return bool `true`=存在，`false`=不存在
   *
   * @note 此方法会忽略 select()、orderBy() 等子句，仅检查条件
   * @note 等价于 SQL: SELECT EXISTS(SELECT * FROM ... WHERE ...)
   * @see notExists() 检查是否不存在
   */
  function exists($params = [])
  {
    return $this->checkExists(false, $params);
  }

  /**
   * 查询是否不存在
   *
   * 将当前查询包装为 NOT EXISTS 子查询，判断是否没有满足条件的记录
   *
   * @example
   * // 检查用户是否没有订单
   * $noOrders = Query::table('orders')->where('user_id', $userId)->notExists();
   *
   * @return bool `true`=不存在，`false`=存在
   *
   * @note 等价于 SQL: SELECT NOT EXISTS(SELECT * FROM ... WHERE ...)
   * @see exists() 检查是否存在
   */
  function notExists($params = [])
  {
    return $this->checkExists(true, $params);
  }
  /**
   * 设置写操作状态并返回 SQL（不执行，供外层获取 SQL 后自行执行）
   *
   * @param  string      $type    操作类型: insert, replace, update, delete
   * @param  mixed       $data    数据（insert/replace/update 需要）
   * @param  array       $options 额外选项，如 ['insertIsIgnore' => true]
   * @return string
   */
  public function writeSql($type, $data = null, $options = [])
  {
    $this->executeType = $type;
    if (in_array($type, ['insert', 'replace'])) {
      $this->options['insertData'] = $data;
      $this->options['insertIsIgnore'] = $options['insertIsIgnore'] ?? false;
    } elseif ($type === 'update') {
      $this->options['updateData'] = $data;
    }
    return $this->generateSQL();
  }

  /**
   * 执行写操作（INSERT/UPDATE/DELETE）的通用方法
   * 
   * 生成 SQL → 合并绑定参数 → 执行查询 → 自动重置选项。
   * 有绑定参数时使用 prepare + execute（预处理防注入），无参数时使用 query。
   * 
   * @param array $params 预处理参数，将与内部 bindings 合并
   * @return int|\PDOStatement|bool 返回影响行数或 PDOStatement（取决于驱动实现），失败返回 false
   * 
   * @note 执行后自动调用 reset() 清理查询选项，下一次链式调用从干净状态开始
   * @see insert() / update() / delete() 具体入口方法
   */
  private function executeWrite($params = [])
  {
    $this->sql = $this->generateSQL();
    $allParams = $this->mergeBindings($params);
    $result = empty($allParams)
      ? $this->databaseDriver->query($this->sql)
      : $this->databaseDriver->execute($this->sql, $allParams);
    $this->reset();
    return $result;
  }

  /**
   * 执行插入操作
   * 
   * 支持单行和批量插入，自动检测数据格式：
   * - 单行：['col1' => 'val1', 'col2' => 'val2']
   * - 批量：[['col1' => 'val1'], ['col1' => 'val2']]
   * 
   * @example
   * // 单行插入
   * $query->from('users')->insert(['name' => 'John', 'age' => 18]);
   * 
   * // 批量插入
   * $query->from('logs')->insert([
   *   ['type' => 'login', 'uid' => 1],
   *   ['type' => 'logout', 'uid' => 1],
   * ]);
   * 
   * // REPLACE INTO
   * $query->from('users')->insert(['id' => 1, 'name' => 'John'], true);
   * 
   * // INSERT IGNORE
   * $query->from('users')->insert(['name' => 'John'], false, true);
   * 
   * // 使用 raw 表达式
   * $query->from('users')->insert([
   *   'name'       => 'John',
   *   'created_at' => $query->raw('NOW()'),
   * ]);
   * 
   * @param array $data 插入数据
   * @param bool $isReplaceInto 是否使用 REPLACE INTO，默认 false
   * @param bool $isIgnore 是否使用 INSERT IGNORE，默认 false
   * @param bool $returnId 是否返回自增 ID 而非执行结果，默认 false
   * @return int|bool 当 $returnId 为 true 时返回自增 ID，否则返回执行结果（true/false）
   * 
   * @note 自动检测数据格式：关联数组为单行，索引数组为批量
   * @note 批量插入时，所有行必须拥有相同的列结构
   * @note 值可以是 raw 表达式（$query->raw('NOW()')）或子查询（Query 实例）
   * @see insertGetId() 直接获取自增 ID 的便捷方法
   */
  function insert($data, $isReplaceInto = false, $isIgnore = false, $returnId = false, $params = [])
  {
    $this->executeType = $isReplaceInto ? "replace" : "insert";
    $this->options['insertData'] = $data;
    $this->options['insertIsIgnore'] = $isIgnore;
    $result = $this->executeWrite($params);
    return $returnId ? $this->databaseDriver->insertId() : $result;
  }
  /**
   * 执行插入操作并返回自增 ID
   * 
   * insert() 的便捷方法，插入成功后直接返回自增 ID
   * 
   * @example
   * // 插入并获取 ID
   * $id = $query->from('users')->insertGetId(['name' => 'John']);
   * 
   * // REPLACE INTO 并获取 ID
   * $id = $query->from('users')->insertGetId(['id' => 1, 'name' => 'John'], true);
   * 
   * @param array $data 插入数据
   * @param bool $isReplaceInto 是否使用 REPLACE INTO，默认 false
   * @param bool $isIgnore 是否使用 INSERT IGNORE，默认 false
   * @return int|string 返回自增 ID
   * 
   * @note 等同于 insert($data, $isReplaceInto, $isIgnore, true)
   * @see insert() 完整的插入方法
   */
  function insertGetId($data, $isReplaceInto = false, $isIgnore = false, $params = [])
  {
    return $this->insert($data, $isReplaceInto, $isIgnore, true, $params);
  }
  /**
   * 执行更新操作
   * 
   * 更新当前查询表的数据，必须结合 where() 指定更新范围，否则会更新全表
   * 
   * @example
   * // 基础更新
   * $query->from('users')->where('id', 1)->update(['name' => 'Jane']);
   * 
   * // 多字段更新
   * $query->from('users')->where('status', 'inactive')->update([
   *   'status' => 'active',
   *   'updated_at' => $query->raw('NOW()'),
   * ]);
   * 
   * @param array $data 要更新的字段和值，键为字段名，值为新值
   * @return int|bool 返回影响的行数或 false（执行失败）
   * 
   * @note 必须结合 where() 使用，否则会更新全表数据
   * @note 值可以是 raw 表达式（$query->raw('NOW()')）或子查询（Query 实例）
   * @note 调用后会自动 reset()，可链式进行下一次查询
   * @see where() 添加更新条件
   * @see insert() 插入操作
   */
  function update($data, $params = [])
  {
    $this->executeType = "update";
    $this->options['updateData'] = $data;
    return $this->executeWrite($params);
  }
  /**
   * 执行删除操作
   * 
   * 删除当前查询表的数据，必须结合 where() 指定删除范围，否则会删除全表
   * 
   * @example
   * // 按主键删除
   * $query->from('users')->where('id', 1)->delete();
   * 
   * // 按条件批量删除
   * $query->from('logs')->where('created_at', '<', '2024-01-01')->delete();
   * 
   * @return int|bool 返回影响的行数或 false（执行失败）
   * 
   * @note 必须结合 where() 使用，否则会删除全表数据
   * @note 调用后会自动 reset()，可链式进行下一次查询
   * @see where() 添加删除条件
   */
  function delete($params = [])
  {
    $this->executeType = "delete";
    return $this->executeWrite($params);
  }
}
