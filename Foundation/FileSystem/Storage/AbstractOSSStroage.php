<?php

namespace kernel\Foundation\FileSystem\Storage;

/**
 * 对象存储（OSS 类）磁盘抽象基类
 *
 * 为所有对象存储型磁盘（腾讯云 COS、阿里云 OSS 等）提供统一骨架：
 * - 公共属性：客户端实例、桶名、地域、密钥；
 * - 构造时注入密钥/地域/桶，并委托父类标记为 "remote" 磁盘、随后触发 {@see boot()} 完成客户端初始化；
 * - 提供 {@see bucket()}/{@see region()} 两个读写一体的访问器，且 region 变更会重新触发 boot() 以重建客户端。
 *
 * 具体的上传/读取/删除/URL/鉴权等能力由各子类（QCloudCOSStorage、AliyunOSSStorage …）实现。
 *
 * @package kernel\Foundation\FileSystem\Storage
 */
abstract class AbstractOSSStroage extends AbstractStorage
{
  /**
   * @var mixed|null 存储服务客户端实例（由子类 boot() 初始化，如 OSS/V2 SDK 客户端）
   */
  protected $client = null;
  /**
   * @var string|null 存储桶名称
   */
  protected $bucket = null;
  /**
   * @var string|null 存储所在地域
   */
  protected $region = null;

  /**
   * @var string|null 访问密钥 ID（如 AccessKeyId / SecretId）
   */
  protected $secretId = null;
  /**
   * @var string|null 访问密钥 Secret（如 AccessKeySecret / SecretKey）
   */
  protected $secretKey = null;

  /**
   * 构造对象存储磁盘
   *
   * 注入密钥/地域/桶，调用父类构造标记为远程（"remote"）磁盘，并触发 {@see boot()} 完成客户端初始化。
   *
   * @param string $secretId 访问密钥 ID
   * @param string $secretKey 访问密钥 Secret
   * @param string $region 存储地域
   * @param string $bucket 存储桶名称
   */
  public function __construct($secretId, $secretKey, $region, $bucket)
  {
    $this->secretId = $secretId;
    $this->secretKey = $secretKey;
    $this->region = $region;
    $this->bucket = $bucket;

    parent::__construct("remote");

    $this->boot();
  }

  /**
   * 客户端/磁盘初始化钩子
   *
   * 构造与 region 变更时均会调用。基类默认空实现，供子类初始化各自的 SDK 客户端
   * （如 QCloudCOSStorage 初始化 COS 客户端、AliyunOSSStorage 初始化 OSS 客户端）。
   *
   * @return static
   */
  protected function boot()
  {

    return $this;
  }

  /**
   * 读取或设置存储桶名称
   *
   * 读写一体：传参时设置桶名并返回 $this（链式）；不传时返回当前桶名。
   *
   * @param string|null $name 桶名称
   * @return static|string|null
   */
  public function bucket($name = null)
  {
    if (func_num_args()) {
      $this->bucket = $name;
      return $this;
    };

    return $this->bucket;
  }
  /**
   * 读取或设置存储地域
   *
   * 读写一体：传参时设置地域并**重新触发 {@see boot()}**（以便子类重建对应地域的客户端），返回 $this（链式）；
   * 不传时返回当前地域。
   *
   * @param string|null $name 地域标识
   * @return static|string|null
   */
  public function region($name = null)
  {
    if (func_num_args()) {
      $this->region = $name;

      $this->boot();

      return $this;
    };

    return $this->region;
  }
}
