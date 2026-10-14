<?php

namespace kernel\Foundation\Database\RDB\Drivers\Mysqli;

use kernel\Foundation\Database\RDB\Drivers\AbstractDriver;
use kernel\Foundation\Exception\Error;
use PDO;

/**
 * mysqli 数据库驱动 — 复用已有连接或自行建连的关系型后端
 *
 * 首参是 `\mysqli` 实例时**包装**它（不建连、不改其字符集）；首参是字符串时**按连接参数自建连接**，
 * 参数顺序与 {@see \kernel\Foundation\Database\RDB\Drivers\PDO\PdoDriver::__construct()} 一致：
 *
 * ```php
 * // 自行建连
 * Connections::addDriver(new MysqliDriver('127.0.0.1', 'root', 'pass', 'my_db', 3306));
 *
 * // 复用 Discuz 已建好的连接
 * Connections::addDriver(new MysqliDriver(\DB::object()->curlink));
 * ```
 *
 * 复用模式是 Discuz! X 环境下的典型用法：数据库由 Discuz 自己连接，内核直接借用它的句柄，
 * 从而让内核的 Query / DB / Model 能在 Discuz 已建好的连接上执行。
 *
 * ## 与 PDO 后端的差异
 *
 * | 差异点 | 说明 |
 * |---|---|
 * | 命名占位符 | mysqli 只认问号（`?`）占位符，命名占位符由 {@see MysqliStatement::normalizeNamedPlaceholders()} 在执行前改写 |
 *
 * ## 事务状态
 *
 * mysqli 没有 `inTransaction()`，本驱动沿用 {@see AbstractDriver::inTransaction()} 的默认实现
 * （读标记位），因此只反映**本驱动自己开启的事务**：若外层（如 Discuz 自身）已在事务中，本类并不知情。
 *
 * @see MysqliStatement mysqli 语句包装器
 * @see AbstractDriver  驱动抽象基类（本类的契约与共享逻辑）
 */
class MysqliDriver extends AbstractDriver
{

  /**
   * 建立或包装 mysqli 连接
   *
   * 首参传 `\mysqli` 实例时直接包装（不建连，也不改动它的字符集）；传字符串时按连接参数自行建连。
   *
   * ```php
   * new MysqliDriver('127.0.0.1', 'root', 'pass', 'my_db', 3306);   // 自行建连
   * new MysqliDriver(\DB::object()->curlink);                       // 复用已有连接
   * ```
   *
   * @param \mysqli|string $hostOrConnection mysqli 连接实例，或数据库主机
   * @param string|null $user 账号（自行建连时使用）
   * @param string|null $password 密码（自行建连时使用）
   * @param string|null $databaseName 数据库名（自行建连时使用）
   * @param int|null $port 端口，默认 3306（自行建连时使用）
   * @param string $charset 字符集，默认 utf8mb4（仅自行建连时设置）
   * @throws Error 入参既非 mysqli 连接又非主机名，或自行建连/设置字符集失败时抛出
   */
  function __construct($hostOrConnection, $user = null, $password = null, $databaseName = null, $port = 3306, $charset = "utf8mb4")
  {
    if ($hostOrConnection instanceof \mysqli) {
      $this->connection = $hostOrConnection;

      return;
    }

    if (!is_string($hostOrConnection) || $hostOrConnection === "") {
      throw new Error("mysqli 驱动需要 mysqli 连接实例或主机名", 500, "DatabaseError:500:MysqliConnectionTypeError");
    }

    $connection = mysqli_init();
    if ($connection === false) {
      throw new Error("mysqli 初始化失败", 500, "DatabaseConnectError:500", mysqli_connect_error());
    }

    $connected = false;
    $connectError = null;
    try {
      // 连接失败统一抛内核 Error，故用 @ 抑制 mysqli 自带的警告
      $connected = @$connection->real_connect($hostOrConnection, $user, $password, $databaseName, $port);
    } catch (\mysqli_sql_exception $exception) {
      $connectError = $exception->getMessage();
    }

    if (!$connected) {
      throw new Error("数据库连接失败", 500, "DatabaseConnectError:500", $connectError ?: $connection->connect_error);
    }

    if ($charset) {
      try {
        $charsetSet = @$connection->set_charset($charset);
      } catch (\mysqli_sql_exception $exception) {
        $charsetSet = false;
      }

      if ($charsetSet === false) {
        throw new Error("数据库字符集设置失败", 500, "DatabaseConnectError:500", $connection->error);
      }
    }

    $this->connection = $connection;
  }

  // ===================================================================
  // 底层原语
  // ===================================================================

  /**
   * 当前后端标识
   *
   * @return string {@see AbstractDriver::DRIVER_MYSQLI}
   */
  public function driverName()
  {
    return self::DRIVER_MYSQLI;
  }

  /**
   * 获取最后插入的自增 ID
   *
   * @return string|int
   */
  public function insertId()
  {
    return $this->connection->insert_id;
  }

  /**
   * 获取最近一次操作错误信息
   *
   * @return array `[SQLSTATE, 错误码, 错误信息]` 三元组，与 PDO `errorInfo()` 结构一致
   */
  public function error()
  {
    return [
      $this->connection->sqlstate,
      $this->connection->errno,
      $this->connection->error
    ];
  }

  /**
   * 转义字符串用于安全的 SQL 拼接
   *
   * mysqli 没有参数类型的概念，统一按字符串转义并补上单引号，与 `PDO::quote()` 的输出形态一致。
   *
   * @param string $string 待转义字符串
   * @param int    $type   参数类型，仅用于保持签名一致，mysqli 忽略
   * @return string
   */
  public function quote($string, $type = PDO::PARAM_STR)
  {
    return "'" . $this->connection->real_escape_string($string) . "'";
  }

  /**
   * 执行一条原始 SQL
   *
   * 查询类语句（SELECT / SHOW / DESCRIBE / EXPLAIN）返回可读取结果的
   * {@see MysqliStatement}，其余返回受影响行数。
   *
   * @param string $querySQL SQL 语句
   * @throws Error
   * @return MysqliStatement|int
   */
  public function query($querySQL)
  {
    $result = $this->runQuery($querySQL);

    if ($this->isSelectStatement($querySQL)) {
      return new MysqliStatement($this->connection, $result);
    }

    return $this->connection->affected_rows;
  }

  /**
   * 预处理 SQL 语句
   *
   * 支持问号（`?`）与命名（`:name`）占位符：命名占位符会先被改写为问号，并记录位置映射，
   * 供 {@see MysqliStatement::bindValue()} 按名绑定。
   *
   * @param string $query   SQL 语句模板
   * @param array  $options 语句选项，mysqli 无对应物，忽略
   * @throws Error
   * @return MysqliStatement
   */
  public function prepare($query, $options = [])
  {
    list($normalizedSQL, $namedPositions) = MysqliStatement::normalizeNamedPlaceholders($query);

    try {
      $statement = $this->connection->prepare($normalizedSQL);
    } catch (\mysqli_sql_exception $exception) {
      $statement = false;
    }

    if ($statement === false) {
      $this->throwDatabaseError();
    }

    return new MysqliStatement($this->connection, $statement, $namedPositions);
  }

  /**
   * 开始事务
   *
   * @throws Error
   * @return bool true
   */
  public function beginTransaction()
  {
    try {
      $result = $this->connection->begin_transaction();
    } catch (\mysqli_sql_exception $exception) {
      $result = false;
    }

    if ($result === false) {
      $this->throwDatabaseError();
    }

    $this->inTransaction = true;

    return true;
  }

  /**
   * 提交事务
   *
   * @throws Error
   * @return bool true
   */
  public function commit()
  {
    try {
      $result = $this->connection->commit();
    } catch (\mysqli_sql_exception $exception) {
      $result = false;
    }

    if ($result === false) {
      $this->throwDatabaseError();
    }

    $this->inTransaction = false;

    return true;
  }

  /**
   * 回滚事务
   *
   * @throws Error
   * @return bool true
   */
  public function rollBack()
  {
    try {
      $result = $this->connection->rollback();
    } catch (\mysqli_sql_exception $exception) {
      $result = false;
    }

    if ($result === false) {
      $this->throwDatabaseError();
    }

    $this->inTransaction = false;

    return true;
  }

  // ===================================================================
  // 内部
  // ===================================================================

  /**
   * 执行一条 SQL，失败时抛内核错误
   *
   * @param string $sql
   * @throws Error
   * @return \mysqli_result|bool
   */
  private function runQuery($sql)
  {
    try {
      $result = $this->connection->query($sql);
    } catch (\mysqli_sql_exception $exception) {
      $result = false;
    }

    if ($result === false) {
      $this->throwDatabaseError();
    }

    return $result;
  }

  /**
   * 以内核 Error 抛出最近一次数据库错误
   *
   * @throws Error
   * @return void
   */
  private function throwDatabaseError()
  {
    throw new Error("数据库错误", 500, "DatabaseError:500:" . $this->errno(), $this->error());
  }
}
