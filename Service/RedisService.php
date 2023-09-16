<?php

namespace kernel\Service;

class RedisService
{
  /**
   * 单例实例
   *
   * @var \Redis
   */
  protected static $singleton = null;
  /**
   * 连接池
   *
   * @var array
   */
  protected static $connects = [];
  /**
   * 初始化默认实例
   *
   * @param string $host 可以是主机，也可以是unix域套接字的路径
   * @param integer $port 端口，可选
   * @param float $timeout 超时时长，以秒为单位的值（可选，默认值为0.0，表示无限制）
   * @param string $persistent_id 请求的持久连接的标识
   * @param integer $retry_interval 重试间隔（以毫秒为单位）。
   * @param integer $read_timeout 读取超时时长，以秒为单位的值（可选，默认值为0，表示无限制）
   * @param array $context 由于PhpRedis>=5.3.0可以在连接时指定身份验证和流信息
   * @return boolean 成功时为TRUE，错误时为FALSE。
   */
  public static function init($host = "127.0.0.1", $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = [])
  {
    if (!self::$singleton) {
      self::$singleton = new \Redis();
      self::$singleton->pconnect($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context);
    }

    return self::$singleton;
  }
  /**
   * 连接Redis服务，会新增到连接池中
   *
   * @param string $host 可以是主机，也可以是unix域套接字的路径
   * @param integer $port 端口，可选
   * @param float $timeout 超时时长，以秒为单位的值（可选，默认值为0.0，表示无限制）
   * @param string $persistent_id 请求的持久连接的标识
   * @param integer $retry_interval 重试间隔（以毫秒为单位）。
   * @param integer $read_timeout 读取超时时长，以秒为单位的值（可选，默认值为0，表示无限制）
   * @param array $context 由于PhpRedis>=5.3.0可以在连接时指定身份验证和流信息
   * @return boolean 成功时为TRUE，错误时为FALSE。
   */
  public static function connect($useKey = null, $host = "127.0.0.1", $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null)
  {
    if (!$useKey) {
      $useKey = time();
    }
    $client = new \Redis();
    $client->pconnect($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context);

    self::$connects[$useKey] = $client;
    return self::$connects[$useKey];
  }
  /**
   * 获取服务
   *
   * @param string $useKey 连接池实例名称，获取连接池的实例，不传入时使用默认的实例
   * @return \Redis
   */
  public static function use($useKey = null)
  {
    if (!is_null($useKey)) {
      return self::$connects[$useKey];
    }
    return self::$singleton;
  }
}
