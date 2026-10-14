<?php

namespace kernel\Foundation\Database\RDB\Drivers\Mysqli;

use kernel\Foundation\Exception\Error;
use PDO;

/**
 * mysqli 语句包装器 — 把 mysqli 的句柄适配成 PDOStatement 的调用面
 *
 * mysqli 没有 PDOStatement 那样的统一语句对象：直查（`mysqli::query()`）返回 `mysqli_result`，
 * 预处理（`mysqli::prepare()`）返回 `mysqli_stmt`，两者方法名互不相同。本类把这两种句柄统一包装成
 * {@see \kernel\Foundation\Database\RDB\Drivers\AbstractDriver} 需要的调用面（`bindValue` / `execute` / `fetch` / `fetchAll` /
 * `fetchColumn` / `fetchObject` / `rowCount`），因此基类与上层的 Query / DB / Model 无需感知后端差异。
 *
 * ## 两种模式
 *
 * | 构造入参 | 模式 | 说明 |
 * |---|---|---|
 * | `mysqli_stmt` | 预处理模式 | 需先 `bindValue()` 再 `execute()`；命名占位符由 {@see normalizeNamedPlaceholders()} 改写为问号并给出位置映射 |
 * | `mysqli_result` | 直查模式 | 结果集已就绪，`execute()` 为空操作 |
 *
 * ## 命名占位符
 *
 * mysqli 只认问号（`?`）占位符，而内核 Query 的 IN 条件会生成命名占位符（如 `:__bind_0`），
 * 因此预处理前必须改写；改写逻辑见 {@see normalizeNamedPlaceholders()}。
 *
 * ## 读取结果集的前置条件
 *
 * 预处理模式要拿到结果集依赖 `mysqli_stmt::get_result()`（即 mysqlnd 扩展）。该函数不可用时
 * 本类会在读取结果时抛出内核错误，而不是静默返回空结果。
 *
 * @see MysqliDriver mysqli 后端驱动
 */
class MysqliStatement
{
  /**
   * 连接句柄
   *
   * @var \mysqli
   */
  private $connection = null;
  /**
   * 预处理语句（预处理模式）
   *
   * @var \mysqli_stmt|null
   */
  private $statement = null;
  /**
   * 结果集（直查模式，或预处理执行后取得）
   *
   * @var \mysqli_result|null
   */
  private $result = null;
  /**
   * 命名占位符 => 位置列表（1 起）
   *
   * @var array
   */
  private $namedPositions = [];
  /**
   * 位置 => 待绑定值
   *
   * @var array
   */
  private $boundValues = [];
  /**
   * 是否已执行
   *
   * @var bool
   */
  private $executed = false;

  /**
   * 包装一个 mysqli 句柄
   *
   * @param \mysqli $connection 连接句柄
   * @param \mysqli_stmt|\mysqli_result $source 预处理语句或已就绪的结果集
   * @param array $namedPositions 命名占位符 => 位置列表（1 起），仅预处理模式需要
   */
  function __construct($connection, $source, $namedPositions = [])
  {
    $this->connection = $connection;
    $this->namedPositions = $namedPositions;

    if ($source instanceof \mysqli_stmt) {
      $this->statement = $source;
    } else {
      $this->result = $source;
      $this->executed = true;
    }
  }

  // ===================================================================
  // 绑定与执行
  // ===================================================================

  /**
   * 绑定一个参数
   *
   * 键为字符串时按命名占位符处理（经 {@see normalizeNamedPlaceholders()} 得到的位置逐一绑定），
   * 键为整数时按问号占位符的 1 起位置处理。mysqli 的 `bind_param` 要求引用传值，因此这里只记录
   * 值，统一到 {@see execute()} 时再绑定。
   *
   * @param string|int $param 命名占位符（如 `:__bind_0`）或问号占位符的位置（1 起）
   * @param mixed $value 绑定的值
   * @param int $type 参数类型，仅用于保持与 PDOStatement 一致的签名，mysqli 按值推断
   * @return bool
   */
  function bindValue($param, $value, $type = PDO::PARAM_STR)
  {
    if (is_string($param)) {
      if (!isset($this->namedPositions[$param])) {
        return false;
      }

      foreach ($this->namedPositions[$param] as $position) {
        $this->boundValues[$position] = $value;
      }

      return true;
    }

    $this->boundValues[(int) $param] = $value;

    return true;
  }

  /**
   * 执行语句
   *
   * 直查模式下结果集已就绪，本方法为空操作并返回 true。
   *
   * @param array|null $params 一次性绑定的参数，键为命名占位符或问号位置
   * @return bool
   */
  function execute($params = null)
  {
    if (is_array($params)) {
      foreach ($params as $key => $value) {
        $this->bindValue(is_int($key) ? $key + 1 : $key, $value);
      }
    }

    if (!$this->statement) {
      $this->executed = true;

      return true;
    }

    if ($this->boundValues) {
      ksort($this->boundValues);

      $types = "";
      $values = [];
      foreach ($this->boundValues as $value) {
        $types .= $this->getBindType($value);
        $values[] = $value;
      }

      $bindArgs = array_merge([$types], $values);
      $bindReferences = [];
      foreach ($bindArgs as $index => $argument) {
        $bindReferences[$index] = &$bindArgs[$index];
      }

      call_user_func_array([$this->statement, "bind_param"], $bindReferences);
    }

    try {
      $result = $this->statement->execute();
    } catch (\mysqli_sql_exception $exception) {
      // 与 mysqli_report 关闭时的行为对齐：统一以 false 返回，交由 AbstractDriver::execute() 包装
      return false;
    }

    if ($result) {
      $this->executed = true;
    }

    return $result;
  }

  // ===================================================================
  // 结果读取
  // ===================================================================

  /**
   * 读取一行
   *
   * @param int $mode 获取模式，支持 {@see PDO::FETCH_ASSOC} / {@see PDO::FETCH_NUM} /
   *   {@see PDO::FETCH_BOTH} / {@see PDO::FETCH_OBJ}，其余按 FETCH_ASSOC 处理
   * @param int|null $cursorOrientation 游标方向，mysqli 无对应物，忽略
   * @param int $cursorOffset 游标偏移，mysqli 无对应物，忽略
   * @return array|object|false 无更多数据时返回 false
   */
  function fetch($mode = PDO::FETCH_ASSOC, $cursorOrientation = null, $cursorOffset = 0)
  {
    $result = $this->getResult();
    if (!$result) {
      return false;
    }

    switch ($mode) {
      case PDO::FETCH_NUM:
        $row = $result->fetch_row();
        break;
      case PDO::FETCH_BOTH:
        $row = $result->fetch_array();
        break;
      case PDO::FETCH_OBJ:
        $row = $result->fetch_object();
        break;
      default:
        $row = $result->fetch_assoc();
        break;
    }

    return $row === null ? false : $row;
  }

  /**
   * 读取全部行
   *
   * @param int $mode 获取模式；传入 {@see PDO::FETCH_FUNC} 时第二参须为回调，
   *   回调按各列值依次展开调用，返回值为该行的处理结果
   * @param callable|null $callback FETCH_FUNC 模式下的行处理回调
   * @return array
   */
  function fetchAll($mode = PDO::FETCH_ASSOC, $callback = null)
  {
    $rows = [];

    if ($mode === PDO::FETCH_FUNC) {
      while (($row = $this->fetch(PDO::FETCH_NUM)) !== false) {
        $rows[] = call_user_func_array($callback, $row);
      }

      return $rows;
    }

    while (($row = $this->fetch($mode)) !== false) {
      $rows[] = $row;
    }

    return $rows;
  }

  /**
   * 读取当前行指定列的值
   *
   * @param int $column 列索引，默认 0
   * @return mixed 无更多数据或列不存在时返回 false
   */
  function fetchColumn($column = 0)
  {
    $row = $this->fetch(PDO::FETCH_NUM);
    if ($row === false) {
      return false;
    }

    $column = (int) $column;

    return array_key_exists($column, $row) ? $row[$column] : false;
  }

  /**
   * 读取一行并映射为对象
   *
   * @param string $class 目标类名，默认 stdClass
   * @param array $constructorArgs 构造函数参数
   * @return object|false 无更多数据时返回 false
   */
  function fetchObject($class = "stdClass", $constructorArgs = [])
  {
    $result = $this->getResult();
    if (!$result) {
      return false;
    }

    $object = $result->fetch_object($class, $constructorArgs);

    return $object === null ? false : $object;
  }

  /**
   * 受影响行数
   *
   * 预处理模式取 `affected_rows`（写操作受影响的行数），直查模式取结果集行数。
   *
   * @return int
   */
  function rowCount()
  {
    if ($this->result) {
      return $this->result->num_rows;
    }
    if ($this->statement) {
      return (int) $this->statement->affected_rows;
    }

    return 0;
  }

  // ===================================================================
  // 占位符改写
  // ===================================================================

  /**
   * 把命名占位符改写成 mysqli 支持的问号占位符
   *
   * 按「引用状态机」扫描：单引号 / 双引号 / 反引号包裹的片段一律原样透传（并处理 `\` 转义与引号
   * 双写），因此 `'10:30:00'` 这类值里的冒号不会被误判成占位符；不在引用内的 `:` 仅当后随字母或
   * 下划线时才视为命名占位符，故 `::` 也不会被误改写。
   *
   * 同名占位符出现多次时，改写后的每个位置都会记录，绑定一次即覆盖全部位置。
   *
   * @param string $sql 原始 SQL
   * @return array `[改写后的 SQL, 命名占位符 => 位置列表（1 起）]`
   */
  static function normalizeNamedPlaceholders($sql)
  {
    $normalizedSQL = "";
    $namedPositions = [];
    $position = 0;
    $length = strlen($sql);
    $quote = null;

    for ($index = 0; $index < $length; $index++) {
      $char = $sql[$index];

      if ($quote !== null) {
        $normalizedSQL .= $char;

        if ($char === "\\") {
          if ($index + 1 < $length) {
            $normalizedSQL .= $sql[++$index];
          }
        } else if ($char === $quote) {
          if ($index + 1 < $length && $sql[$index + 1] === $quote) {
            $normalizedSQL .= $sql[++$index];
          } else {
            $quote = null;
          }
        }

        continue;
      }

      if ($char === "'" || $char === '"' || $char === "`") {
        $quote = $char;
        $normalizedSQL .= $char;
        continue;
      }

      if ($char === ":" && $index + 1 < $length && self::isPlaceholderStart($sql[$index + 1])) {
        $name = ":";
        $cursor = $index + 1;
        while ($cursor < $length && self::isPlaceholderChar($sql[$cursor])) {
          $name .= $sql[$cursor];
          $cursor++;
        }

        $position++;
        $namedPositions[$name][] = $position;
        $normalizedSQL .= "?";
        $index = $cursor - 1;
        continue;
      }

      $normalizedSQL .= $char;
    }

    return [$normalizedSQL, $namedPositions];
  }

  // ===================================================================
  // 内部
  // ===================================================================

  /**
   * 取得结果集，必要时先执行语句
   *
   * @throws Error 预处理语句不支持 `get_result()`（缺少 mysqlnd 扩展）时
   * @return \mysqli_result|null 语句不产生结果集时返回 null
   */
  private function getResult()
  {
    if ($this->result) {
      return $this->result;
    }
    if (!$this->statement) {
      return null;
    }
    if (!$this->executed) {
      $this->execute();
    }
    if (!$this->statement->field_count) {
      return null;
    }
    if (!method_exists($this->statement, "get_result")) {
      throw new Error(
        "mysqli 预处理语句读取结果集需要 mysqlnd 扩展支持",
        500,
        "DatabaseError:500:MysqliGetResultUnavailable"
      );
    }

    $result = $this->statement->get_result();
    if ($result === false) {
      return null;
    }

    $this->result = $result;

    return $result;
  }

  /**
   * 获取值对应的 mysqli 绑定类型字符
   *
   * @param mixed $value 绑定值
   * @return string `i`（整型/布尔）/ `d`（浮点）/ `s`（字符串，含 null）
   */
  private function getBindType($value)
  {
    if (is_int($value) || is_bool($value)) {
      return "i";
    }
    if (is_float($value)) {
      return "d";
    }

    return "s";
  }

  /**
   * 判断字符能否作为命名占位符的首字符
   *
   * @param string $char 单个字符
   * @return bool
   */
  private static function isPlaceholderStart($char)
  {
    return ctype_alpha($char) || $char === "_";
  }

  /**
   * 判断字符能否作为命名占位符的后续字符
   *
   * @param string $char 单个字符
   * @return bool
   */
  private static function isPlaceholderChar($char)
  {
    return ctype_alnum($char) || $char === "_";
  }
}
