<?php

namespace kernel\Facades;

use kernel\Foundation\Facade\Facade;
use kernel\Providers\Redis\RedisProvider;

/**
 * Redis 门面
 *
 * 转发到当前应用的 {@see RedisService} 实例：与 `$app->make(RedisProvider::class)`
 * 是同一份，连接池也随之共享。
 *
 * 使用前需在应用 Bootstrap 里登记装配：
 * ```php
 * $this->app()->provider(new \kernel\Providers\Redis\RedisProvider());
 * ```
 * 没登记就调用门面会直接抛「目标未装配」，不会悄悄造一个默认实例。
 *
 * @method static \Redis client() 默认连接（首次调用才 pconnect）
 * @method static \Redis|false connect($useKey = null, array $options = []) 建立额外连接并入池
 * @method static \Redis|null use($useKey = null) 取连接（不传取默认连接）
 * @method static bool disconnect($useKey = null) 断开连接
 */
class Redis extends Facade
{
  /**
   * 容器键：Redis 服务实例（应用级）
   *
   * @return string
   */
  protected static function accessor()
  {
    return RedisProvider::class;
  }
}
