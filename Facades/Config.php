<?php

namespace kernel\Facades;

use kernel\Foundation\Facade\Facade;
use kernel\Foundation\Application\Applications;

/**
 * 配置门面
 *
 * 转发到**当前应用**容器里的 {@see ConfigProvider}：与 `$app->config()`、
 * `$app->make(ConfigProvider::class)` 是同一份实例。
 *
 * 门面自身不持有实例、也不决定归属——配置实例读哪个应用目录，由构造它的容器决定。
 * 跨应用读配置必须显式走对方应用的容器：`App::of("gstudio_super_app")->config()`。
 *
 * 没有任何应用登记时调用会抛错，而配置恰恰在早期阶段（异常输出、CLI 命令）常被读取。
 * 需要「没有应用就返回默认值」的场景请自行兜底：`Applications::default() === null`
 * 或 `try/catch`。
 *
 * 取用方式（调用点写法不变，只换 use）：
 * ```php
 * use kernel\Facades\Config;
 *
 * $mode = Config::get("mode", "production");
 * ```
 *
 * @method static mixed get($key = null, $default = null) 读配置项，null 返回全部
 * @method static array all() 全部配置
 * @method static bool has($key) 配置键是否存在
 * @method static ConfigProvider set($keyOrValue, $value = null) 运行时设置配置（仅内存）
 * @method static ConfigProvider forget($key) 删除配置键
 * @method static ConfigProvider push($key, $value) 向数组类型的配置项追加值
 * @method static ConfigProvider load() 重新读取五层配置文件
 * @method static bool read($filePath) 读取并合并单个配置文件
 * @method static bool loaded() 是否已读过配置文件
 * @method static ConfigProvider flush() 清空内存配置
 */
class Config extends Facade
{
  /**
   * 容器键：配置实例（应用级）
   *
   * @return string
   */
  protected static function resolve()
  {
    return Applications::default()->config();
  }
}
