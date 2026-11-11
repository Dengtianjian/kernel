<?php

namespace kernel\Providers\Redis;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * Redis 服务（应用级）
 *
 * 由 {@see \kernel\Providers\Redis\RedisProvider} 绑定为应用单例，经容器取用
 * （`$app->make(RedisProvider::class)` 或门面 {@see \kernel\Facades\Redis}）。
 *
 * 连接参数取自本应用的 `redis` 配置节，**首次取连接时才真正 pconnect**：
 * 装配阶段登记本服务不会碰网络，没用到 Redis 的应用也不会为它付出连接成本。
 *
 * 连接池按 key 分：`use()` 取默认连接，`use("cache")` 取具名连接。
 *
 * @package kernel\Providers\Redis
 */
class RedisProvider extends AbilityBaseObject
{
  /**
   * 构造即装配
   *
   * 登记自己（按基类名），短名由 Bootstrap 登记时决定。
   *
   * @return void
   */
  public function __construct()
  {
    $this->app()->provider($this, self::class);
  }

  /**
   * 默认连接（懒建）
   *
   * @var \Redis|null
   */
  protected $client = null;
  /**
   * 具名连接池
   *
   * @var array<string, \Redis>
   */
  protected $connects = [];

  /**
   * 默认连接：按本应用的 `redis` 配置节建立，建立后复用
   *
   * @return \Redis
   * @throws Error 扩展未安装、配置缺失或连接失败时抛出
   */
  public function client()
  {
    if ($this->client === null) {
      $options = $this->options();
      $this->client = $this->create($options);
      if ($this->client === false) {
        throw new Error("Redis 连接失败", 500, "500:RedisConnectFailed", $options);
      }
    }

    return $this->client;
  }

  /**
   * 建立一条额外的连接并放进连接池
   *
   * 参数在 `redis` 配置节之上覆盖，只写差异项即可：
   * `$redis->connect("cache", ["database" => 2])`。
   *
   * @param string|null $useKey 连接池里的名字；不传按顺序编号
   * @param array $options 覆盖配置项的连接参数
   * @return \Redis|false 成功返回连接，失败返回 false
   * @throws Error 扩展未安装时抛出
   */
  public function connect($useKey = null, array $options = [])
  {
    $client = $this->create(array_merge($this->options(), $options));
    if ($client === false) {
      return false;
    }

    $this->connects[$useKey === null ? (string)count($this->connects) : $useKey] = $client;

    return $client;
  }

  /**
   * 取连接
   *
   * @param string|null $useKey 连接池名；不传返回默认连接
   * @return \Redis|null 具名连接不存在时返回 null
   * @throws Error 扩展未安装或默认连接失败时抛出
   */
  public function use($useKey = null)
  {
    if ($useKey === null) {
      return $this->client();
    }

    return $this->connects[$useKey] ?? null;
  }

  /**
   * 断开连接
   *
   * @param string|null $useKey 连接池名；不传断开默认连接
   * @return bool 目标连接不存在时返回 false
   */
  public function disconnect($useKey = null)
  {
    if ($useKey === null) {
      if ($this->client === null) {
        return false;
      }
      @$this->client->close();
      $this->client = null;

      return true;
    }

    if (!isset($this->connects[$useKey])) {
      return false;
    }

    @$this->connects[$useKey]->close();
    unset($this->connects[$useKey]);

    return true;
  }

  /**
   * 本应用的 redis 连接配置
   *
   * @return array
   */
  protected function options()
  {
    return (array)$this->app()->config()->get("redis", []);
  }

  /**
   * 按参数建立一条连接
   *
   * @param array $options host / port / database / timeout / persistentId /
   *                       retryInterval / readTimeout / context / password
   * @return \Redis|false 连接失败返回 false
   * @throws Error 扩展未安装时抛出
   */
  protected function create(array $options)
  {
    if (!class_exists("\Redis")) {
      throw new Error("未安装 redis 扩展", 500, "500:RedisExtensionMissing");
    }

    $client = new \Redis();
    $connected = $client->pconnect(
      $options["host"] ?? "127.0.0.1",
      (int)($options["port"] ?? 6379),
      (float)($options["timeout"] ?? 0),
      $options["persistentId"] ?? null,
      (int)($options["retryInterval"] ?? 0),
      (float)($options["readTimeout"] ?? 0),
      $options["context"] ?? null
    );
    if (!$connected) {
      return false;
    }

    $password = $options["password"] ?? null;
    if ($password !== null && $password !== "" && !$client->auth($password)) {
      return false;
    }

    $client->select((int)($options["database"] ?? 0));

    return $client;
  }
}
