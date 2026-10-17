<?php

namespace kernel\Modules\QCloud;

use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Modules\QCloud\QCloudCos\QCloudCosClient;

/**
 * 腾讯云账号
 *
 * 自身即腾讯云账号（SecretId / SecretKey，以及临时凭据必须携带的 SecurityToken），
 * 同时是本模块客户端的**聚合入口**：{@see api()} / {@see faceId()} / {@see cos()} 各自返回
 * 对应业务的客户端对象（按需实例化，每次调用返回新实例）。账号上的凭据是本模块各业务对象
 * （{@see QCloudApi} 请求客户端、{@see QCloudFaceId}、QCloudCos 下的客户端与存储磁盘）
 * 取用凭据的**唯一来源**。
 *
 * 与 {@see \kernel\Modules\Wechat\WechatAccount}、{@see \kernel\Modules\WechatPay\WechatPayMerchant}
 * 对称；「聚合入口」这一角色对应 {@see \kernel\Modules\Wechat\Miniprogram\WechatMiniProgram}
 * （微信那边因有公众号 / 小程序两种账号类型而把入口单独拆成薄基类，本模块只有一种账号，
 * 故合并在本类上）。账号负责身份与身份之上的业务入口，**任何请求实现都不在本类**
 * （见 {@see QCloudApi}）；存储桶与地域也不属于账号 —— 桶/地域属于存储磁盘的配置，
 * 由调用方另行传入（见 {@see \kernel\Modules\QCloud\QCloudCos\QCloudCOSStorage} 与 {@see cos()}）。
 *
 * `name` 为账号自定义标识（如 `main` / `cos`）：它**不参与签名**，但它是本模块账号注册表的键 ——
 * {@see QCloudModule::addAccount()} 以该值登记，{@see QCloudModule::account()} 等取用入口按该值查找，
 * 因此要交给模块管理的账号必须有非空标识（腾讯云没有像微信 appId、支付商户号那样的业务自然键，故由调用方自定义）。
 *
 * 已聚合的入口：
 * - 通用请求客户端（不限定云产品）：api()
 * - 人脸核身：faceId()
 * - 对象存储：cos()
 *
 * 使用示例：
 *
 * ```php
 * $account = new QCloudAccount($secretId, $secretKey, "main");
 * $account->name();            // "main"
 * $account->secretId();        // 读取 SecretId
 * $account->secretKey();       // 读取 SecretKey
 * $account->securityToken();   // 临时凭据的会话令牌；永久凭据时为 null
 *
 * $account->faceId()->bankCard2EVerification($name, $bankCard);
 * $account->cos("ap-guangzhou", "bkt-1250000000")->upload("a/b.png", "/tmp/a.png");
 * $account->api("faceid")->post("BankCard2EVerification", "2018-03-01", [...]);
 *
 * // 或交给模块注册表统一管理（门面见 {@see QCloud}，模块见 {@see QCloudModule}）：
 * QCloud::addAccount($account);
 * QCloud::account("main")->faceId()->bankCard2EVerification($name, $bankCard);
 * ```
 */
class QCloudAccount extends AbilityBaseObject
{
  /**
   * 账号标识
   *
   * 自定义字符串（如 `main`）：不参与签名，但它是本模块账号注册表的键
   * （见 {@see QCloudModule::addAccount()}），交给模块管理的账号必须非空。
   *
   * @var string|null
   */
  protected $name = null;
  /**
   * 云 API 密钥 SecretId
   *
   * 签名里作为 Credential 段，COS 的 `q-sign-*` 系列里作为 `q-ak`。
   *
   * @var string|null
   */
  protected $secretId = null;
  /**
   * 云 API 密钥 SecretKey
   *
   * 计算 HMAC 用；**切勿输出到日志/响应**。
   *
   * @var string|null
   */
  protected $secretKey = null;
  /**
   * 临时凭据的会话令牌（SecurityToken）
   *
   * 使用临时 SecretId/SecretKey 时非空：TC3 请求需放在 `X-TC-Token` 头，
   * COS 签名会多出 `x-cos-security-token`。使用永久凭据时为 null。
   *
   * @var string|null
   */
  protected $securityToken = null;

  /**
   * 初始化腾讯云账号
   *
   * @param string|null $secretId 云 API 密钥 SecretId
   * @param string|null $secretKey 云 API 密钥 SecretKey（**不要**输出到日志/响应）
   * @param string|null $name 账号标识；不参与签名，但要交给模块注册表管理时不可为空
   * @param string|null $securityToken 临时凭据的会话令牌；永久凭据传 null
   */
  public function __construct($secretId, $secretKey, $name = null, $securityToken = null)
  {
    $this->secretId = $secretId;
    $this->secretKey = $secretKey;
    $this->name = $name;
    $this->securityToken = $securityToken;
  }

  /**
   * 读写一体：获取或设置账号标识
   *
   * 依据调用时是否传入参数区分读写：
   * - 传入参数：设置标识，并返回当前实例以支持链式调用；
   * - 不传参数：返回当前标识。
   *
   * ```php
   * $account->name("cos");   // 设置，返回 $account
   * $account->name();        // 读取，返回 "cos"
   * ```
   *
   * @param string|null $data 待设置的账号标识
   * @return static|string|null 设置时返回当前实例；读取时返回标识，未设置时为 null
   */
  public function name($data = null)
  {
    if (func_num_args()) {
      $this->name = $data;
      return $this;
    }

    return $this->name;
  }
  /**
   * 读写一体：获取或设置 SecretId
   *
   * 依据调用时是否传入参数区分读写：
   * - 传入参数：设置 SecretId，并返回当前实例以支持链式调用；
   * - 不传参数：返回当前已设置的 SecretId。
   *
   * @param string|null $data 待设置的 SecretId
   * @return static|string|null 设置时返回当前实例；读取时返回 SecretId，未设置时为 null
   */
  public function secretId($data = null)
  {
    if (func_num_args()) {
      $this->secretId = $data;
      return $this;
    }

    return $this->secretId;
  }
  /**
   * 读写一体：获取或设置 SecretKey
   *
   * 依据调用时是否传入参数区分读写：
   * - 传入参数：设置 SecretKey，并返回当前实例以支持链式调用；
   * - 不传参数：返回当前已设置的 SecretKey。
   *
   * @param string|null $data 待设置的 SecretKey
   * @return static|string|null 设置时返回当前实例；读取时返回 SecretKey，未设置时为 null
   */
  public function secretKey($data = null)
  {
    if (func_num_args()) {
      $this->secretKey = $data;
      return $this;
    }

    return $this->secretKey;
  }
  /**
   * 读写一体：获取或设置临时凭据的会话令牌
   *
   * 依据调用时是否传入参数区分读写：
   * - 传入参数：设置令牌，并返回当前实例以支持链式调用；传 null 表示回到永久凭据；
   * - 不传参数：返回当前令牌。
   *
   * @param string|null $data 待设置的 SecurityToken
   * @return static|string|null 设置时返回当前实例；读取时返回令牌，未设置时为 null
   */
  public function securityToken($data = null)
  {
    if (func_num_args()) {
      $this->securityToken = $data;
      return $this;
    }

    return $this->securityToken;
  }

  /**
   * 请求客户端（通用，不限定云产品）
   *
   * 每次调用返回新的客户端实例，客户端自己持有本账号；需要临时凭据时在返回的实例上
   * 自行调用 {@see QCloudApi::tmpCredentials()}。
   *
   * ```php
   * $account->api("faceid")->post("BankCard2EVerification", "2018-03-01", [...]);
   * ```
   *
   * @param string|null $service 操作的服务名称（决定请求域名，如 faceid）；null 时由调用方自行指定 host
   * @return QCloudApi
   */
  public function api($service = null)
  {
    return new QCloudApi($this, $service);
  }
  /**
   * 人脸核身客户端
   *
   * @return QCloudFaceId
   */
  public function faceId()
  {
    return new QCloudFaceId($this);
  }
  /**
   * 对象存储（COS）客户端
   *
   * 存储桶与地域**不属于账号**（账号只管凭据），故由调用方传入；
   * 参数顺序与 {@see QCloudCosClient} 保持一致：**region 在前、bucket 在后**。
   *
   * 注意：存储磁盘（{@see \kernel\Modules\QCloud\QCloudCos\QCloudCOSStorage}）不走本方法 ——
   * 它构造时自己接收账号与桶/地域，并由 {@see QCloudCosClient} 直接建客户端。
   *
   * @param string $region 存储桶所在地区，如 ap-guangzhou
   * @param string $bucket 存储桶名称，形如 bucketName-appid
   * @param string|null $host 桶域名；不传按 {@see QCloudCosClient::HOST_PATTERN} 拼
   * @param string|null $driver 强制驱动（{@see QCloudCosClient::DRIVER_SDK} / {@see QCloudCosClient::DRIVER_HTTP}）；不传自动检测
   * @return QCloudCosClient
   */
  public function cos($region, $bucket, $host = null, $driver = null)
  {
    return new QCloudCosClient($this, $region, $bucket, $host, $driver);
  }
}
