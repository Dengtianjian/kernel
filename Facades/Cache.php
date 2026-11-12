<?php

namespace kernel\Facades;

use kernel\Foundation\Facade\Facade;
use kernel\Foundation\Application\Applications;

/**
 * 缓存门面
 *
 * 全部方法都转发到应用级 {@see CacheProvider}：读写缓存在它身上，
 * 动态 KEY（`key()`，静态资源版本号）也随实例生成一次。
 *
 * 门面自身不持有任何实例，`Cache::get()` 每次都向当前应用的容器取，
 * 与 `$app->cache()`、`$app->make(CacheProvider::class)` 同源。
 *
 * 内核在 {@see \kernel\Foundation\App::__construct()} 里把它登记为延迟单例（别名 `cache`），
 * 首次取用才构造；应用想换成自己的实现，重新登记同名条目即可。
 *
 * 取用方式（调用点写法不变，只换 use）：
 * ```php
 * use kernel\Facades\Cache;
 *
 * $value = Cache::get("key");
 * ```
 *
 * @method static bool has(string $id) 缓存是否存在且未过期
 * @method static mixed read(string $id) 读缓存（false=不存在，null=已过期）
 * @method static array|false meta(string $id) 读缓存元信息
 * @method static bool write(string $id, $content, $expiresIn = 30) 写缓存（数组内容合并）
 * @method static bool overwrite(string $id, $content, $expiresIn = 30) 覆盖写
 * @method static bool clear(string $id) 清空该缓存的内容（保留文件）
 * @method static bool remove(string $id) 删除缓存
 * @method static mixed remember(string $id, callable $callback, $expiresIn = 30) 读不到则生成并写入
 * @method static mixed get(string $id, $default = null) 读缓存，取不到返回默认值
 * @method static int increment(string $id, int $step = 1, $expiresIn = 30) 原子自增
 * @method static int decrement(string $id, int $step = 1, $expiresIn = 30) 原子自减
 * @method static bool flush() 清空全部缓存文件（运维）
 * @method static int gc() 清理过期缓存，返回清理数量（运维）
 */
class Cache extends Facade
{
  /**
   * 容器键：缓存实例（应用级）
   *
   * @return string
   */
  protected static function resolve()
  {
    return Applications::default()->cache();
  }

  /**
   * 取本进程的缓存动态 KEY
   *
   * KEY 随 CacheProvider 实例生成，静态资源地址用它做版本号。
   *
   * @return string 16 位十六进制字符串
   */
  public static function key(): string
  {
    return Applications::default()->cache()->key();
  }
}
