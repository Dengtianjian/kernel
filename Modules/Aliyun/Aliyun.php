<?php

namespace kernel\Modules\Aliyun;

use kernel\Foundation\Application\Applications;
use kernel\Foundation\Facade\Facade;

/**
 * 阿里云门面
 *
 * 静态转发到**当前应用**容器里的阿里云账号（短名 `aliyun`）：
 * 与 `$app->aliyun()`、`$app->make(AliyunAccount::class)` 是同一份实例。
 *
 * 底层由 {@see AliyunProvider} 在装配期登记：
 * ```php
 * // 应用 Bootstrap
 * $app->make(AliyunProvider::class);   // 或 $app->provider(new AliyunProvider())
 * ```
 * 没配置密钥（provider 的 `account()` 返回 null）时不会登记，
 * 此时取用会抛「无法解析 aliyun」——与「密钥未配置」的表现一致，不会静默用空密钥。
 *
 * 签名与请求不放门面：它们是纯工具，直接 `new AliyunSignature($account, ...)` 即可。
 *
 * @see AliyunAccount 账号凭证
 * @see AliyunProvider 装配
 *
 * @method static string appId() 账号标识（AccessKey ID）
 * @method static string appSecret() 密钥（AccessKey Secret）
 *
 * @package kernel\Modules\Aliyun
 */
class Aliyun extends Facade
{
  /**
   * 兜底实例：当前应用容器里的阿里云账号
   *
   * 走 `Applications::default()` 而不是 `setUpApp()`：门面在请求期被调用，
   * 那时装配上下文早已出栈；默认应用就是这个进程正在服务的应用。
   *
   * @return AliyunAccount
   */
  protected static function resolve()
  {
    return Applications::default()->aliyun();
  }
}
