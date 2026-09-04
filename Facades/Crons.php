<?php

namespace kernel\Facades;

use kernel\Foundation\Facade;
use kernel\Foundation\Crontab\Crons as CronsManager;
use kernel\Foundation\FileSystem\Path;

/**
 * 定时任务门面
 *
 * 继承 Facade，因覆写 resolve() 自动判定为单例门面，单例实例为 Crontab\Crons 管理器。
 *
 * 业务应用可直接通过本门面登记/执行定时器（如 Crons::registerClass(...)）；
 * 未显式登记时由 resolve() 兜底自动 new 一个管理器并扫描当前应用 Crons/
 * 目录登记定时器。
 *
 * @method static \kernel\Foundation\Crontab\Crons register(\kernel\Foundation\Crontab\Cron $cron) 登记一个任务实例，返回自身
 * @method static \kernel\Foundation\Crontab\Crons registerClass(string $class) 按类名登记（自动实例化，非 Cron 子类跳过），返回自身
 * @method static \kernel\Foundation\Crontab\Crons discover(string $directory, string $namespace) 扫描目录下的全部 .php 类文件并按命名空间登记，返回自身
 * @method static int runDue() 执行所有到期任务，返回执行数量
 * @method static bool run(string $class) 强制执行指定类名的任务（忽略到期判断）
 * @method static \kernel\Foundation\Crontab\Cron[] all() 返回已登记的任务实例列表
 * @method static string[] notFound() 返回扫描时未找到的类名
 */
class Crons extends Facade
{
  /**
   * 兜底实例：自动 new 一个管理器并扫描当前应用 Crons/ 目录登记定时器
   *
   * @return object
   */
  protected static function resolve(): object
  {
    $manager = new CronsManager();
    $app = \getApp();
    if ($app) {
      $manager->discover(Path::root() . "/Crons", $app->id() . "\\Crons");
    }
    return $manager;
  }
}
