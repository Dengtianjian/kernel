<?php

namespace kernel\Foundation\Database\PDO;

use kernel\Foundation\Error;
use PDO;

/**
 * PDO 驱动封装 — 数据库操作的底层引擎
 *
 * Driver 直接包裹 PHP 原生 PDO 实例，提供连接建立、SQL 执行、预处理语句、
 * 事务管理等基础能力，是 ORM 四层架构的最底层。
 *
 * ## 职责范围
 *
 * - **连接管理**：构造时建立 PDO 连接，通过 `getPDO()` 暴露原生实例
 * - **SQL 执行**：`query()` 自动区分 SELECT（返回 PDOStatement）和写操作（返回受影响行数），
 *   写操作内部使用 `PDO::exec()` 确保跨驱动一致性
 * - **预处理**：`prepare()` + `bindValues()` + `execute()` 完整参数绑定流程，
 *   `bindValues()` 自动根据 PHP 值类型推断 PDO 参数类型
 * - **便捷查询**：`first()` / `all()` / `value()` / `object()` / `map()`
 *   统一支持传参预处理和直查两种模式
 * - **事务**：`beginTransaction()` / `commit()` / `rollBack()` / `inTransaction()`
 *
 * ## 使用方式
 *
 * Driver 通常不直接使用，而是通过 Connections 注册后由 DB 门面、Query 构建器、
 * Model 等上层组件间接调用。仅在需要底层 PDO 操作时直接使用：
 *
 * ```php
 * $driver = new Driver('127.0.0.1', 'root', 'pass', 'my_db', 3306);
 * $rows  = $driver->all('SELECT * FROM users WHERE status = ?', [1]);
 * $count = $driver->value('SELECT COUNT(*) FROM users');
 * ```
 *
 * @see Connections 多连接管理器
 * @see DB         数据库门面（上层统一入口）
 */
class Driver
{
  /** @var PDO PDO 连接实例 */
  private PDO $PDOInstance;
  /**
   * 创建 PDO 数据库连接
   *
   * @param string     $hostname 主机名
   * @param string     $username 用户名
   * @param string     $password 密码
   * @param string     $database 数据库名
   * @param int        $port     端口，默认 3306
   * @param array|null $options  PDO 连接选项
   * @throws Error
   */
  public function __construct($hostname = null, $username = null, $password = null, $database = null, $port = 3306, $options = null)
  {
    try {
      $this->PDOInstance = new PDO("mysql:dbname=$database;host=$hostname;port=$port", $username, $password, $options);
    } catch (\PDOException $e) {
      throw new Error(
        "数据连接失败：" . $e->getMessage(),
        500,
        join(":", ['PDO', 500000, $e->getCode()]),
        $e->getTrace()
      );
    }
  }
  /**
   * 设置 PDO 连接属性
   *
   * @param int   $attribute 属性常量，如 PDO::ATTR_ERRMODE
   * @param mixed $value     属性值
   * @return bool
   */
  public function setAttribute($attribute, $value)
  {
    return $this->PDOInstance->setAttribute($attribute, $value);
  }
  /**
   * 获取 PDO 连接属性
   *
   * @param int $attribute 属性常量
   * @return mixed
   */
  public function getAttribute($attribute)
  {
    return $this->PDOInstance->getAttribute($attribute);
  }
  /**
   * 获取最近一次操作错误信息
   *
   * @return array
   */
  public function error()
  {
    return $this->PDOInstance->errorInfo();
  }
  /**
   * 获取最近一次操作 SQLSTATE 错误码
   *
   * @return string
   */
  public function errno()
  {
    return $this->PDOInstance->errorCode();
  }
  /**
   * 获取最后插入的自增 ID
   *
   * @return string
   */
  public function insertId()
  {
    return $this->PDOInstance->lastInsertId();
  }
  /**
   * 获取底层 PDO 实例
   *
   * @return PDO
   */
  public function getPDO()
  {
    return $this->PDOInstance;
  }
  /**
   * 转义字符串用于安全的 SQL 拼接
   *
   * @param string $string 待转义字符串
   * @param int    $type   参数类型，默认 PDO::PARAM_STR
   * @return string|false
   */
  public function quote($string, $type = PDO::PARAM_STR)
  {
    return $this->PDOInstance->quote($string, $type);
  }
  /**
   * 判断 SQL 是否为查询类语句（返回结果集）
   *
   * @param string $sql
   * @return bool
   */
  private function isSelectStatement($sql)
  {
    $trimmed = trim($sql);
    $firstWord = strtoupper(strstr($trimmed, ' ', true) ?: $trimmed);
    return in_array($firstWord, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN', 'DESC'], true);
  }
  /**
   * 执行 SQL 查询
   * 查询类语句返回 PDOStatement，写操作返回受影响行数
   *
   * @param string $querySQL SQL 语句
   * @throws Error
   * @return \PDOStatement|int
   */
  public function query($querySQL)
  {
    if ($this->isSelectStatement($querySQL)) {
      $statement = $this->PDOInstance->query($querySQL);
      if ($statement === false) {
        throw new Error("数据库错误", 500, "DatabaseError:500:" . $this->errno(), [
          'error' => $this->error(),
          'sql' => $querySQL,
        ]);
      }
      return $statement;
    }

    $result = $this->PDOInstance->exec($querySQL);
    if ($result === false) {
      throw new Error("数据库错误", 500, "DatabaseError:500:" . $this->errno(), [
        'error' => $this->error(),
        'sql' => $querySQL,
      ]);
    }
    return $result;
  }
  /**
   * 执行一条 SQL 语句并返回受影响行数
   * 适用于不需要结果集的 DDL/DML 操作，比 query() 更高效
   *
   * @param string $statement SQL 语句
   * @throws Error
   * @return int 受影响行数
   */
  public function exec($statement)
  {
    $result = $this->PDOInstance->exec($statement);
    if ($result === false) {
      throw new Error("数据库错误", 500, "DatabaseError:500:" . $this->errno(), $this->error());
    }
    return $result;
  }
  /**
   * 开始事务
   *
   * @throws Error
   * @return true
   */
  public function beginTransaction()
  {
    $result = $this->PDOInstance->beginTransaction();
    if (!$result) {
      throw new Error("数据库错误", 500, "BeginTransactionError:500:" . $this->errno(), $this->error());
    }
    return true;
  }
  /**
   * 提交事务
   *
   * @throws Error
   * @return true
   */
  public function commit()
  {
    $result = $this->PDOInstance->commit();
    if (!$result) {
      throw new Error("数据库错误", 500, "CommitTransactionError:500:" . $this->errno(), $this->error());
    }
    return true;
  }
  /**
   * 检查当前是否处于事务中
   *
   * @return bool
   */
  public function inTransaction()
  {
    return $this->PDOInstance->inTransaction();
  }
  /**
   * 回滚事务
   *
   * @throws Error
   * @return true
   */
  public function rollBack()
  {
    $result = $this->PDOInstance->rollBack();
    if (!$result) {
      throw new Error("数据库错误", 500, "RollbackTransactionError:500:" . $this->errno(), $this->error());
    }
    return true;
  }
  /**
   * 预处理 SQL 语句
   * 支持命名（:name）和问号（?）占位符，同一语句不能混用
   *
   * @param string $query   SQL 语句模板
   * @param array  $options PDOStatement 属性设置
   * @throws Error
   * @return \PDOStatement
   */
  public function prepare($query, $options = [])
  {
    $statement = $this->PDOInstance->prepare($query, $options);
    if ($statement === false) {
      throw new Error("预处理语句失败", 500, "PrepareError:500:" . $this->errno(), $this->error());
    }
    return $statement;
  }
  /**
   * 获取参数对应的 PDO 类型常量
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
   * 支持命名（:name）和问号（?）两种占位符，自动根据值类型选择合适的 PDO 绑定类型
   *
   * @param \PDOStatement $statement 预处理语句对象
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
   * SELECT 返回 PDOStatement 供遍历，写操作返回受影响行数
   *
   * @param string $query  SQL 语句模板
   * @param array  $params 绑定参数
   * @throws Error
   * @return \PDOStatement|int SELECT 返回 PDOStatement，其余返回受影响行数
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
   * 传入 $params 时走预处理路径，否则走直查路径
   *
   * @param string $querySQL SQL 语句或模板
   * @param array $params 参数绑定数组，为空时直查
   * @param int $mode 获取模式，默认 PDO::FETCH_ASSOC
   * @param int $cursorOrientation 游标方向
   * @param int $cursorOffset 游标偏移
   * @return array|false
   */
  public function first($querySQL, $params = [], $mode = PDO::FETCH_ASSOC, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0)
  {
    $PDOStatement = empty($params) ? $this->query($querySQL) : $this->execute($querySQL, $params);
    return $PDOStatement->fetch($mode, $cursorOrientation, $cursorOffset);
  }
  /**
   * 查询全部数据
   * 传入 $params 时走预处理路径，否则走直查路径
   *
   * @param string $querySQL SQL 语句或模板
   * @param array $params 参数绑定数组，为空时直查
   * @param int $mode 获取模式，默认 PDO::FETCH_ASSOC
   * @return array
   */
  public function all($querySQL, $params = [], $mode = PDO::FETCH_ASSOC)
  {
    $PDOStatement = empty($params) ? $this->query($querySQL) : $this->execute($querySQL, $params);
    return $PDOStatement->fetchAll($mode);
  }
  /**
   * 查询单个列的值
   * 传入 $params 时走预处理路径，否则走直查路径
   *
   * @param string $querySQL SQL 语句或模板
   * @param array $params 参数绑定数组，为空时直查
   * @param int $column 列索引，默认 0
   * @return mixed
   */
  public function value($querySQL, $params = [], $column = 0)
  {
    $PDOStatement = empty($params) ? $this->query($querySQL) : $this->execute($querySQL, $params);
    return $PDOStatement->fetchColumn($column);
  }
  /**
   * 查询并返回对象
   * 传入 $params 时走预处理路径，否则走直查路径
   *
   * @param string $querySQL SQL 语句或模板
   * @param array $params 参数绑定数组，为空时直查
   * @param string $class 类名，默认 stdClass
   * @param array $constructorArgs 构造函数参数
   * @return object|false
   */
  public function object($querySQL, $params = [], $class = "stdClass", $constructorArgs = [])
  {
    $PDOStatement = empty($params) ? $this->query($querySQL) : $this->execute($querySQL, $params);
    return $PDOStatement->fetchObject($class, $constructorArgs);
  }
  /**
   * 通过回调函数处理查询结果
   *
   * @param string $querySQL SQL 语句或模板
   * @param callable $callback 回调函数
   * @param array $params 参数绑定数组，为空时直查
   * @return array
   */
  public function map($querySQL, $callback, $params = [])
  {
    $PDOStatement = empty($params) ? $this->query($querySQL) : $this->execute($querySQL, $params);
    return $PDOStatement->fetchAll(PDO::FETCH_FUNC, $callback);
  }
}
