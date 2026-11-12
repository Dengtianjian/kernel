<?php

namespace kernel\Facades;

use kernel\Foundation\Facade\Facade;
use kernel\Foundation\Application\Applications;

/**
 * 日志门面
 *
 * 转发到当前应用的 {@see LogProvider} 实例：与 `$app->make(LogProvider::class)`、
 * `$app->log()` 是同一份，级别过滤与目录归属都跟着那个应用走。
 *
 * 日志目录按归属应用的 `Data/Logs` 计算，因此 DiscuzX 下落到 `gstudio_xxx/Data/Logs`。
 * 使用前无需装配——{@see \kernel\Foundation\App} 构造时已登记该服务；
 * 其它应用容器里同样可用，只是写到各自目录。
 *
 * @method static string|null level($level = null) 设置或读取最小记录级别
 * @method static bool record($content, $level = "info") 按指定级别记录
 * @method static bool info($message, $context = []) 记录 info
 * @method static bool warning($message, $context = []) 记录 warning
 * @method static bool error($message, $context = []) 记录 error
 * @method static bool debug($message, $context = []) 记录 debug
 * @method static array read($day = null, $month = null, $year = null) 读取日志
 * @method static array page($page = 1, $pageSize = 20, $day = null, $month = null, $year = null) 分页读取
 * @method static int cleanup($days = 30) 清理指定天数前的日志
 */
class Log extends Facade
{
  /**
   * 容器键：日志服务实例（应用级）
   *
   * @return string
   */
  protected static function resolve()
  {
    return Applications::default()->log();
  }
}
