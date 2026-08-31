<?php

namespace kernel\Foundation\Database\PDO;

/**
 * 数据库操作门面 — 参考 Laravel DB 风格
 *
 * 提供两套 API：
 *   1. Query Builder: DB::table('users')->where('id', 1)->first()
 *   2. 原生 SQL（带参数绑定）: DB::select('SELECT * FROM users WHERE id = ?', [1])
 *
 * ------------------------------------------------------------------
 * 原生 SQL API 分三层
 * ------------------------------------------------------------------
 *
 * | 层级 | 方法 | 参数绑定 | 查询日志 | 返回结果 |
 * |------|------|---------|---------|---------|
 * | 便捷层（推荐） | `select` `selectOne` `scalar` `insert` `update` `delete` | 支持 | 否 | 数组 / 标量 / 行数 |
 * | 通用语句层 | `statement` `affectingStatement` `unprepared` | 支持 | 否 | bool / 行数 |
 * | 底层直查层 | `query` `exec` `prepare` `execute` | 视方法而定 | **是** | PDOStatement / 行数 |
 *
 * 底层直查层不做绑定判断，直接转发 PDO，因此自由度最高但也最危险；
 * 日常请用便捷层。
 *
 * ------------------------------------------------------------------
 * 代码组织（自上而下）
 * ------------------------------------------------------------------
 *
 *   属性 → 连接管理 → Query Builder 入口
 *   → 原生 SQL（读 / 写 / 通用语句）→ 原始表达式
 *   → 底层直查 → 连接信息 → 事务 → 查询日志
 */
class DB
{
  // ===================================================================
  // 属性：查询日志状态
  // ===================================================================

  /** @var array 已执行的 SQL 日志，每项 ['query' => string, 'bindings' => array, 'time' => float] */
  private static $queryLog = [];

  /** @var bool 是否记录日志（由 enableQueryLog() / disableQueryLog() 控制） */
  private static $loggingQueries = false;

  /** @var array<callable> 查询监听器，由 listen() 注册，每次记录日志时触发 */
  private static $listeners = [];

  // ===================================================================
  // 连接管理
  // ===================================================================

  /**
   * 切换或获取数据库连接
   *
   * @param string|null $name 连接名称，为 null 时使用默认连接
   *
   * @example
   * DB::connection('slave');
   * DB::table('users')->get();
   */
  public static function connection($name = null)
  {
    if ($name !== null) {
      Connections::useDriver($name);
    }
  }

  /**
   * 获取当前连接的底层 PDO 实例
   *
   * @return \PDO
   */
  public static function getPdo()
  {
    return Connections::getUseDriver()->getPDO();
  }

  // ===================================================================
  // Query Builder 入口
  // ===================================================================

  /**
   * 创建 Query 实例（Query Builder 入口）
   *
   * @param string|null $tableName 表名
   * @param Driver|null $databaseDriver 指定驱动，默认使用当前连接
   * @return Query
   *
   * @example
   * DB::table('users')->where('status', 1)->get();
   */
  public static function table($tableName = null, $databaseDriver = null)
  {
    return new Query($tableName, $databaseDriver);
  }

  // ===================================================================
  // 原生 SQL：读操作（带参数绑定）
  // ===================================================================

  /**
   * 执行 SELECT 查询并返回全部结果
   *
   * @param string $query    SQL 语句，支持 ? 或 :name 占位符
   * @param array  $bindings 参数绑定
   * @return array
   *
   * @example
   * DB::select('SELECT * FROM users WHERE status = ?', [1]);
   */
  public static function select($query, $bindings = [])
  {
    return Connections::getUseDriver()->fetchAll($query, $bindings);
  }

  /**
   * 执行 SELECT 查询并返回第一条记录
   *
   * @param string $query    SQL 语句，支持 ? 或 :name 占位符
   * @param array  $bindings 参数绑定
   * @return array|null
   *
   * @example
   * DB::selectOne('SELECT * FROM users WHERE id = ?', [1]);
   */
  public static function selectOne($query, $bindings = [])
  {
    $row = Connections::getUseDriver()->fetch($query, $bindings);
    return $row ?: null;
  }

  /**
   * 执行 SELECT 查询并返回单个标量值
   *
   * @param string $query    SQL 语句，支持 ? 或 :name 占位符
   * @param array  $bindings 参数绑定
   * @return mixed
   *
   * @example
   * DB::scalar('SELECT COUNT(*) FROM users WHERE status = ?', [1]);
   */
  public static function scalar($query, $bindings = [])
  {
    return Connections::getUseDriver()->fetchColumn($query, $bindings);
  }

  // ===================================================================
  // 原生 SQL：写操作（带参数绑定）
  // ===================================================================

  /**
   * 执行 INSERT 语句
   *
   * @param string $query    INSERT SQL，支持 ? 或 :name 占位符
   * @param array  $bindings 参数绑定
   * @return bool
   *
   * @example
   * DB::insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Tom', 'tom@example.com']);
   */
  public static function insert($query, $bindings = [])
  {
    return Connections::getUseDriver()->execute($query, $bindings) !== false;
  }

  /**
   * 执行 INSERT 并返回自增 ID
   *
   * @param string $query    INSERT SQL
   * @param array  $bindings 参数绑定
   * @return string|int
   *
   * @example
   * $id = DB::insertGetId('INSERT INTO users (name) VALUES (?)', ['Tom']);
   */
  public static function insertGetId($query, $bindings = [])
  {
    $result = Connections::getUseDriver()->execute($query, $bindings);
    if ($result !== false) {
      return Connections::getUseDriver()->insertId();
    }
    return 0;
  }

  /**
   * 执行 UPDATE 语句，返回受影响行数
   *
   * @param string $query    UPDATE SQL
   * @param array  $bindings 参数绑定
   * @return int 受影响行数
   *
   * @example
   * DB::update('UPDATE users SET status = ? WHERE id = ?', [1, 5]);
   */
  public static function update($query, $bindings = [])
  {
    $result = Connections::getUseDriver()->execute($query, $bindings);
    return is_int($result) ? $result : 0;
  }

  /**
   * 执行 DELETE 语句，返回受影响行数
   *
   * @param string $query    DELETE SQL
   * @param array  $bindings 参数绑定
   * @return int 受影响行数
   *
   * @example
   * DB::delete('DELETE FROM users WHERE id = ?', [5]);
   */
  public static function delete($query, $bindings = [])
  {
    $result = Connections::getUseDriver()->execute($query, $bindings);
    return is_int($result) ? $result : 0;
  }

  // ===================================================================
  // 原生 SQL：通用语句
  // ===================================================================

  /**
   * 执行任意 SQL 语句（不返回结果集）
   *
   * @param string $query    SQL 语句
   * @param array  $bindings 参数绑定
   * @return bool
   *
   * @example
   * DB::statement('DROP TABLE IF EXISTS tmp_logs');
   */
  public static function statement($query, $bindings = [])
  {
    return Connections::getUseDriver()->execute($query, $bindings) !== false;
  }

  /**
   * 执行任意 SQL 语句并返回受影响行数
   *
   * @param string $query    SQL 语句
   * @param array  $bindings 参数绑定
   * @return int 受影响行数
   */
  public static function affectingStatement($query, $bindings = [])
  {
    $result = Connections::getUseDriver()->execute($query, $bindings);
    return is_int($result) ? $result : 0;
  }

  /**
   * 执行原始 SQL（不经过预处理绑定）
   *
   * @param string $query 原始 SQL
   * @return bool
   */
  public static function unprepared($query)
  {
    return Connections::getUseDriver()->exec($query) !== false;
  }

  // ===================================================================
  // 原始表达式
  // ===================================================================

  /**
   * 创建原始 SQL 表达式，用于 Query Builder 中的片段注入
   *
   * @param string $value 原始 SQL 片段
   * @return Statement
   *
   * @example
   * DB::table('users')->select(DB::raw('COUNT(*) AS total'))->first();
   * DB::table('users')->orderByRaw(DB::raw('id DESC'))->get();
   */
  public static function raw($value)
  {
    return new Statement($value);
  }

  // ===================================================================
  // 底层直查（不做绑定判断，直接转发 PDO）
  // ===================================================================
  //
  // 这一层会写入查询日志（query / exec / execute），自由度最高也最危险。
  // 日常请用上层的 select / insert 等便捷方法。
  //

  /**
   * 执行 SQL 查询。SELECT 返回 PDOStatement，写操作返回受影响行数
   *
   * @param string $sql SQL 语句
   * @return \PDOStatement|int
   */
  public static function query($sql)
  {
    self::logQuery($sql, []);
    return Connections::getUseDriver()->query($sql);
  }

  /**
   * 执行 SQL 并仅返回受影响行数（不返回结果集，比 query() 更高效）
   *
   * @param string $sql SQL 语句
   * @return int
   */
  public static function exec($sql)
  {
    self::logQuery($sql, []);
    return Connections::getUseDriver()->exec($sql);
  }

  /**
   * 预处理 SQL 语句
   *
   * @param string $query   SQL 模板
   * @param array  $options PDOStatement 选项
   * @return \PDOStatement
   */
  public static function prepare($query, $options = [])
  {
    return Connections::getUseDriver()->prepare($query, $options);
  }

  /**
   * 预处理 + 绑定 + 执行
   * SELECT 返回 PDOStatement，其余返回受影响行数
   *
   * @param string $query  SQL 模板
   * @param array  $params 绑定参数
   * @return \PDOStatement|int
   */
  public static function execute($query, $params = [])
  {
    self::logQuery($query, $params);
    return Connections::getUseDriver()->execute($query, $params);
  }

  /**
   * 转义字符串
   *
   * @param string $string 待转义字符串
   * @param int    $type   PDO::PARAM_*
   * @return string|false
   */
  public static function quote($string, $type = \PDO::PARAM_STR)
  {
    return Connections::getUseDriver()->quote($string, $type);
  }

  // ===================================================================
  // 连接信息
  // ===================================================================

  /**
   * 最后插入的自增 ID
   *
   * @return string
   */
  public static function insertId()
  {
    return Connections::getUseDriver()->insertId();
  }

  /**
   * 最近一次操作错误信息
   *
   * @return array
   */
  public static function error()
  {
    return Connections::getUseDriver()->error();
  }

  /**
   * 最近一次操作 SQLSTATE 错误码
   *
   * @return string
   */
  public static function errno()
  {
    return Connections::getUseDriver()->errno();
  }

  // ===================================================================
  // 事务
  // ===================================================================
  //
  // 推荐用 transaction() 闭包：异常自动回滚，并支持死锁重试（$attempts）。
  // 手动控制时用 begin() / commit() / rollback() 三件套。
  //

  /**
   * 开始事务
   *
   * @return bool
   */
  public static function begin()
  {
    return Connections::getUseDriver()->beginTransaction();
  }

  /**
   * 提交事务
   *
   * @return bool
   */
  public static function commit()
  {
    return Connections::getUseDriver()->commit();
  }

  /**
   * 回滚事务
   *
   * @return bool
   */
  public static function rollback()
  {
    return Connections::getUseDriver()->rollBack();
  }

  /**
   * 事务闭包执行（支持重试）
   *
   * @param callable $callback  事务闭包，接收 Driver 实例
   * @param int      $attempts  死锁重试次数，默认 1
   * @return mixed
   * @throws \Exception
   *
   * @example
   * DB::transaction(function ($driver) {
   *     DB::table('users')->insert([...]);
   *     DB::table('logs')->insert([...]);
   * });
   */
  public static function transaction(callable $callback, $attempts = 1)
  {
    $driver = Connections::getUseDriver();

    for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
      $driver->beginTransaction();
      try {
        $result = $callback($driver);
        $driver->commit();
        return $result;
      } catch (\Exception $e) {
        $driver->rollBack();

        // 死锁时重试
        if ($currentAttempt < $attempts && self::causedByDeadlock($e)) {
          continue;
        }

        throw $e;
      }
    }
  }

  /**
   * 是否在事务中
   *
   * @return bool
   */
  public static function inTransaction()
  {
    return Connections::getUseDriver()->inTransaction();
  }

  /**
   * 判断异常是否由死锁导致（供 transaction() 重试判定使用）
   *
   * 同时匹配 MySQL(1213) 与 SQLSTATE(40001) 的死锁错误码。
   *
   * @param \Exception $e
   * @return bool
   */
  private static function causedByDeadlock($e)
  {
    $message = $e->getMessage();
    return strpos($message, 'Deadlock') !== false
      || strpos($message, '1213') !== false
      || strpos($message, '40001') !== false;
  }

  // ===================================================================
  // 查询日志
  // ===================================================================
  //
  // 需先 enableQueryLog() 才会往 $queryLog 里记录；
  // listen() 注册的监听器则无论开关都会触发。
  //
  // 注意：只有底层直查层（query / exec / execute）会调用 logQuery()，
  // 便捷层（select / insert 等）不会写入日志。
  //

  /**
   * 开启查询日志
   */
  public static function enableQueryLog()
  {
    self::$loggingQueries = true;
  }

  /**
   * 关闭查询日志
   */
  public static function disableQueryLog()
  {
    self::$loggingQueries = false;
  }

  /**
   * 获取查询日志
   *
   * @return array 每项 ['query' => string, 'bindings' => array, 'time' => float]
   */
  public static function getQueryLog()
  {
    return self::$queryLog;
  }

  /**
   * 清空查询日志
   */
  public static function flushQueryLog()
  {
    self::$queryLog = [];
  }

  /**
   * 注册查询监听器
   *
   * @param callable $callback function($query, $bindings, $time)
   *
   * @example
   * DB::listen(function ($query, $bindings, $time) {
   *     Log::info($query, ['bindings' => $bindings, 'time' => $time]);
   * });
   */
  public static function listen(callable $callback)
  {
    self::$listeners[] = $callback;
  }

  /**
   * 记录一条查询（内部使用）
   *
   * @param string $query
   * @param array  $bindings
   */
  private static function logQuery($query, $bindings = [])
  {
    $time = microtime(true);

    if (self::$loggingQueries) {
      self::$queryLog[] = compact('query', 'bindings', 'time');
    }

    foreach (self::$listeners as $listener) {
      $listener($query, $bindings, $time);
    }
  }
}
