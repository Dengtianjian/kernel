<?php

namespace kernel\Foundation\Database\RDB\Drivers\PDO;

use kernel\Foundation\Database\RDB\Drivers\AbstractDriver;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\Path;
use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO 数据库驱动 — 关系型数据库的默认后端
 *
 * 内核自行建立 PDO 连接，是 {@see AbstractDriver} 的默认实现。
 * 所有与后端无关的编排逻辑（预处理流程、结果读取、SQL 类型判定）都在基类中，本类只实现底层原语。
 *
 * ## 连接入口
 *
 * 连接不在构造期建立：{@see __construct()} 不接收参数，由 {@see mysql()} / {@see sqlite()}
 * 按协议各自拼装 DSN 并完成连接，同一个类因而可承载多种 PDO 协议。
 *
 * ## 错误处理约定
 *
 * 连接属性默认取 `PDO::ATTR_ERRMODE_SILENT`（不取 `PDO::ERRMODE_EXCEPTION`），使失败以
 * `false` 返回、由 {@see AbstractDriver::execute()} 统一包装为内核 {@see Error}；
 * 同时各原语也捕获 `PDOException` 兜底，因此即便调用方在 `$options` 里覆盖成
 * `ERRMODE_EXCEPTION`，对外抛出的仍是内核 {@see Error}，不会漏出 `PDOException`。
 *
 * ## 使用方式
 *
 * ```php
 * // 按实例调用协议方法建立连接
 * $driver = (new PdoDriver())->mysql('127.0.0.1', 'root', 'pass', 'my_db');
 * Connections::addDriver($driver, 'default', true);
 *
 * // SQLite 协议：相对路径按框架根目录解析
 * $driver = (new PdoDriver())->sqlite('data/app.sqlite');
 * ```
 *
 * @see AbstractDriver 驱动抽象基类（本类的契约与共享逻辑）
 */
class PdoDriver extends AbstractDriver
{
  /**
   * 构造
   *
   * 只创建驱动实例，不建立连接 —— 连接由 {@see mysql()} / {@see sqlite()} 按 scheme 完成。
   *
   */
  public function __construct()
  {
  }

  // ===================================================================
  // 连接入口（按 scheme）
  // ===================================================================

  /**
   * 建立 MySQL 连接
   *
   * DSN 形如 `mysql:host={host};port={port};dbname={databaseName};charset={charset}`。
   *
   * @param string $host         数据库主机
   * @param string $user         账号
   * @param string $password     密码
   * @param string $databaseName 数据库名
   * @param int    $port         端口，默认 3306
   * @param string $charset      字符集，默认 utf8mb4
   * @param array  $options      额外的 PDO 连接属性，键为 `PDO::ATTR_*`；同名键优先于内建默认值
   * @throws Error 连接失败时抛出
   * @return $this 返回当前实例以支持链式调用
   */
  public function mysql($host, $user, $password, $databaseName, $port = 3306, $charset = "utf8mb4", $options = [])
  {
    return $this->connect("mysql:host={$host};port={$port};dbname={$databaseName};charset={$charset}", $user, $password, $options);
  }

  /**
   * 建立 SQLite 连接
   *
   * DSN 形如 `sqlite:{数据库文件路径}`，文件不存在时自动创建（PDO SQLite 的默认行为）。
   * SQLite 全部由此方法承载，**不是**独立后端 —— {@see driverName()} 仍返回
   * {@see AbstractDriver::DRIVER_PDO}（库内 sqlite 驱动已废除，故 `DRIVER_SQLITE` 不复存在）。
   *
   * ## 只读打开
   *
   * PDO 的 SQLite 构造没有 `$flags` 入口，严格只读要经 `$options` 传开库标志：
   *
   * ```php
   * $driver = (new PdoDriver())->sqlite('data/app.sqlite', [
   *   PDO::SQLITE_ATTR_OPEN_FLAGS => SQLITE3_OPEN_READONLY
   * ]);
   * ```
   *
   * `SQLITE3_OPEN_READONLY` 由 ext-sqlite3 定义（值为 1），未装该扩展时传字面量即可；
   * `PDO::SQLITE_ATTR_OPEN_FLAGS` 由 pdo_sqlite 提供。注意**不能**改用 `sqlite:file:…?mode=ro`
   * 这类 URI 写法，原因见 {@see resolveFilePath()}。
   *
   * @param string $databaseFilePath 数据库文件路径；相对路径按 {@see Path::root()} 解析，
   *   `:memory:` 为内存库，空字符串为私有临时库
   * @param array  $options          额外的 PDO 连接属性，键为 `PDO::ATTR_*`；同名键优先于内建默认值
   * @throws Error 连接失败时抛出
   * @return $this 返回当前实例以支持链式调用
   */
  public function sqlite($databaseFilePath, $options = [])
  {
    return $this->connect("sqlite:" . $this->resolveFilePath($databaseFilePath), null, null, $options);
  }

  /**
   * 按 DSN 建立连接
   *
   * @param string      $dsn      PDO 数据源名称（含 scheme 前缀）
   * @param string|null $user     账号；该 scheme 无账号概念时传 null
   * @param string|null $password 密码；该 scheme 无密码概念时传 null
   * @param array       $options  额外的 PDO 连接属性
   * @throws Error 连接失败时抛出
   * @return $this 返回当前实例以支持链式调用
   */
  protected function connect($dsn, $user = null, $password = null, $options = [])
  {
    try {
      $this->connection = new PDO($dsn, $user, $password, $options + [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT
      ]);
    } catch (PDOException $exception) {
      throw new Error("数据库连接失败", 500, "DatabaseConnectError:500", $exception->getMessage());
    }

    return $this;
  }

  // ===================================================================
  // 底层原语
  // ===================================================================

  /**
   * 当前后端标识
   *
   * @return string {@see AbstractDriver::DRIVER_PDO}
   */
  public function driverName()
  {
    return self::DRIVER_PDO;
  }

  /**
   * 获取最后插入的自增 ID
   *
   * @return string|int 无自增 ID 时返回 "0"
   */
  public function insertId()
  {
    return $this->connection->lastInsertId();
  }

  /**
   * 获取最近一次操作错误信息
   *
   * @return array `[SQLSTATE, 错误码, 错误信息]` 三元组
   */
  public function error()
  {
    return $this->connection->errorInfo();
  }

  /**
   * 转义字符串用于安全的 SQL 拼接
   *
   * @param string $string 待转义字符串
   * @param int    $type   参数类型，透传给 PDO
   * @return string|false
   */
  public function quote($string, $type = PDO::PARAM_STR)
  {
    return $this->connection->quote($string, $type);
  }

  /**
   * 执行一条原始 SQL
   *
   * 查询类语句（SELECT / SHOW / DESCRIBE / EXPLAIN）返回 `PDOStatement`，
   * 其余返回受影响行数（取语句的 `rowCount()`）。
   *
   * @param string $querySQL SQL 语句
   * @throws Error
   * @return PDOStatement|int
   */
  public function query($querySQL)
  {
    try {
      $statement = $this->connection->query($querySQL);
    } catch (PDOException $exception) {
      $statement = false;
    }

    if ($statement === false) {
      $this->throwDatabaseError();
    }

    if ($this->isSelectStatement($querySQL)) {
      return $statement;
    }

    return $statement->rowCount();
  }

  /**
   * 预处理 SQL 语句
   *
   * 支持命名（`:name`）和问号（`?`）占位符，同一语句不能混用。
   *
   * @param string $query   SQL 语句模板
   * @param array  $options 语句属性，键为 `PDO::ATTR_*`
   * @throws Error
   * @return PDOStatement
   */
  public function prepare($query, $options = [])
  {
    try {
      $statement = $this->connection->prepare($query, $options);
    } catch (PDOException $exception) {
      $statement = false;
    }

    if ($statement === false) {
      $this->throwDatabaseError();
    }

    return $statement;
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
      $result = $this->connection->beginTransaction();
    } catch (PDOException $exception) {
      $result = false;
    }

    if ($result === false) {
      $this->throwDatabaseError();
    }

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
    } catch (PDOException $exception) {
      $result = false;
    }

    if ($result === false) {
      $this->throwDatabaseError();
    }

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
      $result = $this->connection->rollBack();
    } catch (PDOException $exception) {
      $result = false;
    }

    if ($result === false) {
      $this->throwDatabaseError();
    }

    return true;
  }

  /**
   * 检查当前是否处于事务中
   *
   * 覆写基类默认实现：直接问 PDO 句柄，因此连绕过本驱动的 `beginTransaction()` 也能识别。
   *
   * @return bool
   */
  public function inTransaction()
  {
    return $this->connection->inTransaction();
  }

  // ===================================================================
  // 内部
  // ===================================================================

  /**
   * 解析 SQLite 数据库文件路径
   *
   * `:memory:`（内存库）与空字符串（私有临时库）是 SQLite 的特殊文件名，必须原样透传；
   * 绝对路径直接使用；其余按框架根目录拼接。
   *
   * ⚠️ 因此**不支持** SQLite 的 URI 文件名写法（如 `file:/abs/x.db?mode=ro`）：它以 `file:`
   * 开头、不匹配下面的绝对路径判定，会被当成相对路径拼到框架根目录下。需要只读请见 {@see sqlite()}。
   *
   * @param string $databaseFilePath 传入的路径
   * @return string 可拼进 DSN 的路径
   */
  private function resolveFilePath($databaseFilePath)
  {
    if ($databaseFilePath === null || $databaseFilePath === "" || $databaseFilePath === ":memory:") {
      return (string) $databaseFilePath;
    }

    if (preg_match("#^(?:[A-Za-z]:[\\\\/]|[\\\\/])#", $databaseFilePath)) {
      return $databaseFilePath;
    }

    return Path::join(Path::root(), $databaseFilePath);
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
