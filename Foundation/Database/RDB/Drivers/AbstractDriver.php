<?php

namespace kernel\Foundation\Database\RDB\Drivers;

use kernel\Foundation\Database\RDB\Drivers\Mysqli\MysqliStatement;
use kernel\Foundation\Exception\Error;
use PDO;
use PDOStatement;

/**
 * 关系型数据库驱动抽象基类 — 数据库操作的底层引擎
 *
 * 本类定义驱动必须提供的「底层原语」，并在基类中实现所有与后端无关的编排逻辑
 * （预处理流程、结果读取、SQL 类型判定、错误包装），因此**新增后端只需实现原语**。
 *
 * ## 两种后端
 *
 * | 后端 | 实现类 | 连接来源 |
 * |---|---|---|
 * | `pdo`（默认） | {@see PdoDriver} | 内核按主机 / 账号 / 密码建立 PDO 连接；SQLite 走同一类的 {@see PdoDriver::sqlite()} |
 * | `mysqli` | {@see MysqliDriver} | 按主机 / 账号 / 密码建立连接，也可**包装已有** `mysqli` 连接（如 Discuz 的 `\DB::object()->curlink`） |
 *
 * 当前后端用 {@see driverName()} 判断，返回 {@see DRIVER_PDO} / {@see DRIVER_MYSQLI}。
 * SQLite **不是**独立后端 —— 它由 PDO 后端的 `sqlite:` DSN 承载，`driverName()` 同样返回 `pdo`。
 *
 * ## 职责范围
 *
 * - **连接管理**：{@see getConnection()} 暴露原生句柄（`PDO` / `mysqli`）
 * - **SQL 执行**：{@see query()} 自动区分查询类（返回可读取的结果对象）与写操作（返回受影响行数）
 * - **预处理**：{@see prepare()} + {@see bindValues()} + {@see execute()} 完整参数绑定流程，
 *   {@see bindValues()} 自动根据 PHP 值类型推断绑定类型
 * - **便捷查询**：{@see fetch()} / {@see fetchAll()} / {@see fetchColumn()} /
 *   {@see fetchObject()} / {@see fetchFunc()}，统一支持传参预处理与直查两种模式
 * - **事务**：{@see beginTransaction()} / {@see commit()} / {@see rollBack()} / {@see inTransaction()}
 *
 * ## 使用方式
 *
 * 驱动通常不直接使用，而是通过 {@see Connections} 注册后由 {@see \kernel\Foundation\Database\RDB\DB} 门面、
 * {@see \kernel\Foundation\Database\RDB\Query} 构建器、{@see \kernel\Foundation\Database\RDB\Model}
 * 等上层组件间接调用。需要底层操作时才直接用：
 *
 * ```php
 * $driver = (new PdoDriver())->mysql('127.0.0.1', 'root', 'pass', 'my_db');
 * $rows  = $driver->fetchAll('SELECT * FROM users WHERE status = ?', [1]);
 * $count = $driver->fetchColumn('SELECT COUNT(*) FROM users');
 *
 * // 复用外部已有的 mysqli 连接
 * $driver = new MysqliDriver(\DB::object()->curlink);
 *
 * // SQLite（由 PDO 后端承载；相对路径按框架根目录解析，文件不存在则自动创建）
 * $driver = (new PdoDriver())->sqlite('data/app.sqlite');
 * ```
 *
 * @see PdoDriver    PDO 后端实现（含 SQLite）
 * @see MysqliDriver mysqli 后端实现
 * @see Connections  多连接管理器
 * @see DB           数据库门面（上层统一入口）
 */
abstract class AbstractDriver
{
  /** 后端标识：PDO */
  const DRIVER_PDO = "pdo";
  /** 后端标识：mysqli */
  const DRIVER_MYSQLI = "mysqli";

  /**
   * 原生连接句柄
   *
   * 由具体驱动在建立或包装连接时注入：PDO 后端是 `\PDO`，mysqli 后端是 `\mysqli`。
   * 对外统一经 {@see getConnection()} 读取。
   *
   * @var \PDO|\mysqli|null
   */
  protected $connection = null;

  /**
   * 是否处于本驱动开启的事务中
   *
   * 供 {@see inTransaction()} 的默认实现读取，由具体驱动在开始 / 提交 / 回滚事务时维护。
   *
   * @var bool
   */
  protected $inTransaction = false;

  // ===================================================================
  // 底层原语（由具体驱动实现）
  // ===================================================================

  /**
   * 当前后端标识
   *
   * @return string {@see DRIVER_PDO} / {@see DRIVER_MYSQLI}
   */
  abstract public function driverName();

  /**
   * 获取最后插入的自增 ID
   *
   * @return string|int
   */
  abstract public function insertId();

  /**
   * 获取最近一次操作错误信息
   *
   * 统一为 PDO `errorInfo()` 的三元结构：`[SQLSTATE, 错误码, 错误信息]`。
   *
   * @return array
   */
  abstract public function error();

  /**
   * 转义字符串用于安全的 SQL 拼接
   *
   * @param string $string 待转义字符串
   * @param int    $type   参数类型，默认 PDO::PARAM_STR（mysqli 后端忽略，统一按字符串转义）
   * @return string|false
   */
  abstract public function quote($string, $type = PDO::PARAM_STR);

  /**
   * 执行一条原始 SQL
   *
   * 查询类语句（SELECT / SHOW / DESCRIBE / EXPLAIN）返回可读取的结果对象，
   * 其余返回受影响行数。
   *
   * @param string $querySQL SQL 语句
   * @throws Error
   * @return PDOStatement|MysqliStatement|int
   */
  abstract public function query($querySQL);
  /**
   * 预处理 SQL 语句
   *
   * 支持命名（`:name`）和问号（`?`）占位符，同一语句不能混用。
   *
   * @param string $query   SQL 语句模板
   * @param array  $options 语句选项（PDO 后端透传给 PDO；mysqli 后端无对应物，忽略）
   * @throws Error
   * @return PDOStatement|MysqliStatement
   */
  abstract public function prepare($query, $options = []);

  /**
   * 开始事务
   *
   * @throws Error
   * @return bool true
   */
  abstract public function beginTransaction();

  /**
   * 提交事务
   *
   * @throws Error
   * @return bool true
   */
  abstract public function commit();

  /**
   * 回滚事务
   *
   * @throws Error
   * @return bool true
   */
  abstract public function rollBack();

  // ===================================================================
  // 共享实现（与后端无关）
  // ===================================================================

  /**
   * 获取原生连接句柄
   *
   * @return \PDO|\mysqli|null PDO 后端（含 SQLite）返回 \PDO，mysqli 后端返回 \mysqli；尚未注入连接时为 null
   */
  public function getConnection()
  {
    return $this->connection;
  }

  /**
   * 获取最近一次操作的 SQLSTATE 错误码
   *
   * 直接取 {@see error()} 三元组的首项，因此无需后端各自实现。
   *
   * @return string
   */
  public function errno()
  {
    return $this->error()[0];
  }

  /**
   * 执行一条不需要结果集的 SQL，返回受影响行数
   *
   * 基于 {@see query()} 实现：写语句在部分后端直接返回受影响行数（int），
   * 在另一些后端返回语句对象，此时取其 `rowCount()` 作为受影响行数。
   *
   * @param string $statement SQL 语句
   * @throws Error
   * @return int 受影响行数
   */
  public function exec($statement)
  {
    $result = $this->query($statement);

    if (is_int($result)) {
      return $result;
    }

    return $result->rowCount();
  }

  /**
   * 检查当前是否处于事务中
   *
   * 默认读本类的 `$inTransaction` 标记位，由具体驱动在 {@see beginTransaction()} /
   * {@see commit()} / {@see rollBack()} 中维护；能直接问到底层句柄事务状态的后端可覆写本方法。
   *
   * @return bool
   */
  public function inTransaction()
  {
    return $this->inTransaction;
  }

  /**
   * 判断 SQL 是否为查询类语句（返回结果集）
   *
   * @param string $sql
   * @return bool
   */
  protected function isSelectStatement($sql)
  {
    $trimmed = trim($sql);
    $firstWord = strtoupper(strstr($trimmed, ' ', true) ?: $trimmed);
    return in_array($firstWord, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN', 'DESC'], true);
  }

  /**
   * 获取参数对应的绑定类型常量
   *
   * @param mixed $value 参数值
   * @return int PDO::PARAM_* 常量
   */
  private function getParamType($value)
  {
    if (is_int($value)) return PDO::PARAM_INT;
    if (is_bool($value)) return PDO::PARAM_BOOL;
    if (is_null($value)) return PDO::PARAM_NULL;
    return PDO::PARAM_STR;
  }

  /**
   * 绑定参数数组到预处理语句
   *
   * 支持命名（`:name`）和问号（`?`）两种占位符，自动根据值类型选择合适的绑定类型。
   *
   * @param PDOStatement|MysqliStatement $statement 预处理语句对象
   * @param array $params 参数数组，命名参数用关联数组，问号占位用索引数组
   */
  public function bindValues($statement, $params)
  {
    foreach ($params as $key => $value) {
      $type = $this->getParamType($value);
      $statement->bindValue(
        is_int($key) ? $key + 1 : $key,
        $value,
        $type
      );
    }
  }

  /**
   * 执行预处理 SQL，完成 prepare → bind → execute 流程
   *
   * 查询类语句返回语句对象供遍历，其余返回受影响行数。
   *
   * @param string $query  SQL 语句模板
   * @param array  $params 绑定参数
   * @throws Error
   * @return PDOStatement|MysqliStatement|int 查询类返回语句对象，其余返回受影响行数
   */
  public function execute($query, $params = [])
  {
    $statement = $this->prepare($query);

    if (!empty($params)) {
      $this->bindValues($statement, $params);
    }

    $result = $statement->execute();
    if ($result === false) {
      throw new Error("数据库错误", 500, "DatabaseError:500:" . $this->errno(), $this->error());
    }

    if ($this->isSelectStatement($query)) {
      return $statement;
    }
    return $statement->rowCount();
  }

  /**
   * 查询单行数据
   *
   * 传入 $params 时走预处理路径，否则走直查路径。
   *
   * @param string $querySQL SQL 语句或模板
   * @param array  $params 参数绑定数组，为空时直查
   * @param int    $mode 获取模式，默认 PDO::FETCH_ASSOC
   * @param int    $cursorOrientation 游标方向
   * @param int    $cursorOffset 游标偏移
   * @return array|object|false
   */
  public function fetch($querySQL, $params = [], $mode = PDO::FETCH_ASSOC, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
  {
    $statement = empty($params) ? $this->query($querySQL) : $this->execute($querySQL, $params);
    return $statement->fetch($mode, $cursorOrientation, $cursorOffset);
  }

  /**
   * 查询全部数据
   *
   * 传入 $params 时走预处理路径，否则走直查路径。
   *
   * @param string $querySQL SQL 语句或模板
   * @param array  $params 参数绑定数组，为空时直查
   * @param int    $mode 获取模式，默认 PDO::FETCH_ASSOC
   * @return array
   */
  public function fetchAll($querySQL, $params = [], $mode = PDO::FETCH_ASSOC)
  {
    $statement = empty($params) ? $this->query($querySQL) : $this->execute($querySQL, $params);
    return $statement->fetchAll($mode);
  }

  /**
   * 查询单个列的值
   *
   * 传入 $params 时走预处理路径，否则走直查路径。
   *
   * @param string $querySQL SQL 语句或模板
   * @param array  $params 参数绑定数组，为空时直查
   * @param int    $column 列索引，默认 0
   * @return mixed
   */
  public function fetchColumn($querySQL, $params = [], $column = 0)
  {
    $statement = empty($params) ? $this->query($querySQL) : $this->execute($querySQL, $params);
    return $statement->fetchColumn($column);
  }

  /**
   * 查询并返回对象
   *
   * 传入 $params 时走预处理路径，否则走直查路径。
   *
   * @param string $querySQL SQL 语句或模板
   * @param array  $params 参数绑定数组，为空时直查
   * @param string $class 类名，默认 stdClass
   * @param array  $constructorArgs 构造函数参数
   * @return object|false
   */
  public function fetchObject($querySQL, $params = [], $class = "stdClass", $constructorArgs = [])
  {
    $statement = empty($params) ? $this->query($querySQL) : $this->execute($querySQL, $params);
    return $statement->fetchObject($class, $constructorArgs);
  }

  /**
   * 通过回调函数处理查询结果
   *
   * @param string   $querySQL SQL 语句或模板
   * @param callable $callback 回调函数
   * @param array    $params 参数绑定数组，为空时直查
   * @return array
   */
  public function fetchFunc($querySQL, $callback, $params = [])
  {
    $statement = empty($params) ? $this->query($querySQL) : $this->execute($querySQL, $params);
    return $statement->fetchAll(PDO::FETCH_FUNC, $callback);
  }
}
