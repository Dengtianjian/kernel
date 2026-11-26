<?php

namespace kernel\Discuz\Foundation;

/**
 * 命令行下的 Discuz 表名适配
 *
 * 不加载 Discuz 核心时，内核模型仍通过 `\DB::table()` 补站点表前缀。
 * 本类由命令行引导注册为全局 `DB` 的别名，只提供迁移需要的 `table()`。
 */
class DiscuzCliDatabase
{
  /**
   * 站点表前缀（来自 config_global.php 的 tablepre）
   *
   * @var string
   */
  public static $tablepre = "pre_";

  /**
   * 补表前缀
   *
   * @param string $tableName 裸表名
   * @return string
   */
  public static function table($tableName)
  {
    return self::$tablepre . $tableName;
  }

  /**
   * 设置表前缀
   *
   * @param string $tablepre
   * @return void
   */
  public static function setTablepre($tablepre)
  {
    self::$tablepre = (string) $tablepre;
  }
}