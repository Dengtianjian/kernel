<?php

namespace kernel\Modules\Redis;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * Redis 装配
 *
 * 构造即装配：把 {@see RedisModule} 登记成应用单例——连接池与具名连接必须整个请求
 * 共用一份，否则每次取到的是不同实例、连接也建不共享。
 *
 * 登记方式：`$app->make(RedisProvider::class)` 或 `$app->provider(new RedisProvider())`，
 * **必须传实例**——容器对「传类名」是延迟构造，本类不被构造则 Redis 不会被登记。
 *
 * @see RedisModule
 * @see Redis 门面
 */
class RedisProvider extends AbilityBaseObject
{
  /**
   * 构造即装配
   *
   * @return void
   */
  public function __construct()
  {
    $redis = new RedisModule();

    //* 类名 + 短名两条路都指向同一实例：
    //* `$app->make(RedisModule::class)` / `$app->redis()` / 门面 `Redis::xxx()`
    $this->app()->provider($redis, RedisModule::class);
    $this->app()->provider($redis, "redis");
  }
}
