<?php

namespace kernel\Crons;

use kernel\Foundation\Crontab\Cron;
use kernel\Providers\Auth\AuthProvider;
use kernel\Foundation\Application\Applications;

/**
 * 清理过期登录凭证的定时任务
 *
 * 内核提供基础任务，业务应用可在自身 Crons/ 目录下放一个极简子类即可注册
 * （例如 `gstudio_super_app\Crons\ClearAuthTokensCron`）。
 *
 * 默认每天 03:00 执行，清理 logins 表中已过期的记录。
 */
class ClearExpiredTokensCron extends Cron
{
  /**
   * 执行计划：每天凌晨 3 点
   *
   * @return array{type:string,hour:int,minute:int}
   */
  protected function plan(): array
  {
    return $this->daily(3);
  }

  /**
   * 任务逻辑：清理过期 token
   *
   * 走当前应用的认证服务：表名、app_id 归属都由应用决定，任务本身不猜。
   *
   * @return void
   */
  public function handle()
  {
    Applications::default()->make(AuthProvider::class)->deleteExpiredTokens();
  }
}
