<?php

namespace kernel\Modules\Redis;

use kernel\Foundation\Application\Applications;
use kernel\Foundation\Facade\Facade;

/**
 * Redis 门面
 *
 * 静态转发到**当前应用**容器里的 Redis 模块（短名 `redis`）：
 * 与 `$app->redis()`、`$app->make(RedisModule::class)` 是同一份实例，
 * 连接池也随之共享——否则每次取用的是不同实例、连接建不共享。
 *
 * 底层由 {@see RedisProvider} 在装配期登记：
 * ```php
 * // 应用 Bootstrap
 * $app->make(RedisProvider::class);   // 或 $app->provider(new RedisProvider())
 * ```
 * 没装配就直接调用会抛出「无法解析 redis」，不会悄悄造一个默认实例。
 *
 * @see RedisModule 具体实现：默认连接 + 具名连接池
 * @see RedisProvider 装配：登记实例（类名 + 短名 redis）
 *
 * @method static \Redis client() 默认连接（首次调用才 pconnect）
 * @method static \Redis|false connect(string|null $useKey = null, array $options = []) 建立额外连接并入池
 * @method static \Redis|null use(string|null $useKey = null) 取连接（不传取默认连接）
 * @method static bool disconnect(string|null $useKey = null) 断开连接
 *
 * @package kernel\Modules\Redis
 */
class Redis extends Facade
{
  /**
   * 兜底实例：当前应用容器里的 Redis 模块
   *
   * 走 `Applications::default()` 而不是 `setUpApp()`：门面在请求期被调用，
   * 那时装配上下文早已出栈；默认应用就是这个进程正在服务的应用。
   *
   * @return RedisModule
   */
  protected static function resolve()
  {
    return Applications::default()->redis();
  }
}
