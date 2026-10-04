<?php

namespace kernel\Modules\DiscuzX\Foundation\Database;

/**
 * DiscuzX 数据库门面
 *
 * DiscuzX 环境下数据库连接由 Discuz 自己管理（`\DB` 即 Discuz 的数据库门面），
 * kernel 的 PDO 驱动并未注册。本类把 Discuz 的数据库操作「翻译」成 kernel 侧
 * 所期望的接口（`Table` / `DiscuzXQuery` 都以 `$this->DB::xxx()` 形式调用）：
 *
 * - 兼容 kernel `Table` 的执行接口：exec / select / selectOne / scalar / insertId
 * - 供 `DiscuzXQuery` 取数使用：fetchAll / fetchFirst / resultFirst
 *
 * 所有方法最终都落到 Discuz 的 `\DB`（fetch_all / fetch_first / result_first / query / insert_id）。
 *
 * 注意：Discuz 的 `\DB` 没有预处理语句，$bindings 会被尽力内联后执行。
 */
class DiscuzXDB extends \DB
{
  /* ==================== 兼容 kernel Table 的执行接口 ==================== */

  /**
   * 执行写 / DDL SQL
   *
   * @param string $sql
   * @return bool 与 Discuz \DB::query 一致
   */
  static function exec($sql)
  {
    return \DB::query($sql);
  }

  /**
   * 读取多行
   *
   * @param string $sql
   * @param array  $bindings 命名占位符绑定（Discuz 无预处理，会被内联替换）
   * @return array
   */
  static function select($sql, $bindings = [])
  {
    return self::fetchAll($sql, $bindings);
  }

  /**
   * 读取单行
   *
   * @param string $sql
   * @param array  $bindings
   * @return array|null
   */
  static function selectOne($sql, $bindings = [])
  {
    return self::fetchFirst($sql, $bindings);
  }

  /**
   * 读取标量
   *
   * @param string $sql
   * @param array  $bindings
   * @return mixed
   */
  static function scalar($sql, $bindings = [])
  {
    return self::resultFirst($sql, $bindings);
  }

  /**
   * 最后插入的自增 ID
   *
   * @return int
   */
  static function insertId()
  {
    return self::insert_id();
  }

  /* ==================== 取数（供 DiscuzXQuery 使用） ==================== */

  /**
   * @param string $sql
   * @param array  $bindings
   * @return array
   */
  static function fetchAll($sql, $bindings = [])
  {
    $data = self::fetch_all(self::bindSQL($sql, $bindings));
    return is_array($data) ? $data : [];
  }

  /**
   * @param string $sql
   * @param array  $bindings
   * @return array|null 无结果返回 null
   */
  static function fetchFirst($sql, $bindings = [])
  {
    $data = self::fetch_first(self::bindSQL($sql, $bindings));
    return empty($data) ? null : $data;
  }

  /**
   * @param string $sql
   * @param array  $bindings
   * @return mixed 无结果返回 null
   */
  static function resultFirst($sql, $bindings = [])
  {
    $value = self::result_first(self::bindSQL($sql, $bindings));
    return $value === false ? null : $value;
  }

  /* ==================== 内部 ==================== */

  /**
   * 把命名占位符绑定内联进 SQL（Discuz 的 DB 层没有预处理语句）
   *
   * @param string $sql
   * @param array  $bindings
   * @return string
   */
  private static function bindSQL($sql, $bindings = [])
  {
    if (empty($bindings) || strpos($sql, ":") === false) {
      return $sql;
    }
    foreach ($bindings as $key => $value) {
      $sql = str_replace($key, DiscuzXQuery::formatValue($value), $sql);
    }
    return $sql;
  }
}
