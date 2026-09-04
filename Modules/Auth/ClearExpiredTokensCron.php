<?php

namespace kernel\Modules\Auth;

use kernel\Foundation\Crontab\Cron;
use kernel\Modules\Auth\Auth;

/**
 * 清理过期登录凭证的定时任务
 *
 * 由 Auth 模块提供，业务 app 可在自身 Crons/ 目录下放置一个继承本类的
 * 极简子类即可注册（例如 isdtj\Crons\ClearAuthTokensCron）。
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
   * @return void
   */
  public function handle(): void
  {
    Auth::deleteExpiredTokens();
  }
}
