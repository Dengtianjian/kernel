<?php

namespace kernel\Modules\QCloud\STS;

use kernel\Foundation\Object\AbilityBaseObject;
use QCloud\COSSTS\Sts;

/**
 * 腾讯云 STS 安全凭证服务
 *
 * 把腾讯云 STS 的临时密钥申请包装成"实例方法 + 实例字段"的形式：
 * 构造时固定密钥、存储桶与地域，之后按需申请临时密钥或组装 CAM 策略。
 *
 * **请求客户端二选一**（{@see $stsClient}）：装了官方 STS SDK（`QCloud\COSSTS\Sts`，
 * 即 `vendor/qcloud_sts`）就用 SDK 实例，否则用自带的 {@see QCloudSTSClient} ——
 * 两者接口一致（`getTempKeys($config)` / `getTempKeys4Ci($config)`），返回值也都会经
 * {@see handleResponseData()} 规整，因此**对调用方无感**，只是为兼容"有/没有 SDK"两种环境。
 *
 * 与客户端是**组合**关系（不是继承）：本类负责拼装它需要的 `$config`，真正的签名计算与
 * HTTPS 请求由它完成。
 *
 * 被 {@see \kernel\Modules\QCloud\QCloudCos\QCloudCOSStorage} 与其子类
 * {@see \kernel\Modules\DiscuzX\Foundation\Storage\QCloud\DiscuzXQCloudCOSStorage} 持有（各自的
 * `$stsClient` 属性），用于 COS 直传（前端拿临时密钥直传对象存储）。
 *
 * 字段：$userId / $bucket / $region 为 public，可读；$secretId / $secretKey 为 private，
 * 只存实例内、不对外暴露（也没有 getter）。
 *
 * @link https://github.com/tencentyun/qcloud-cos-sts-sdk/tree/master/php 腾讯云 STS 官方 SDK/样例
 * @package kernel\Modules\QCloud\STS
 */
class QCloudSTS extends AbilityBaseObject
{
  /**
   * 腾讯云 APPID（用户维度的账号 ID）
   *
   * 构造时从 $bucket 的尾部 `-appid` 段解析（`substr($bucket, 1 + strripos($bucket, "-"))`），
   * 因此**不是**独立传入的，改 bucket 会连带改变它；解析失败时（bucket 不含 `-`）会是空串或异常截断值。
   *
   * 参与 {@see generateResourceDescription()} 拼装的资源六段式（`uid/{appid}`）。
   *
   * @var string
   */
  public $userId = null;
  /**
   * 云 API 密钥 SecretId
   *
   * 构造时传入，仅本类内部使用（请求临时密钥时作为 `secretId` 交给客户端），无 getter。
   *
   * @var string
   */
  private $secretId = null;
  /**
   * 云 API 密钥 SecretKey
   *
   * 构造时传入，仅本类内部使用（请求临时密钥时作为 `secretKey` 交给客户端），无 getter；**切勿输出到日志/响应**。
   *
   * @var string
   */
  private $secretKey = null;
  /**
   * 存储桶名称，形如 `bucketName-appid`（如 `test-125000000`）
   *
   * 构造时传入；尾部 `-appid` 段会被解析成 {@see $userId}。
   *
   * @var string
   */
  public $bucket = null;
  /**
   * 存储桶所属地域，如 `ap-guangzhou`
   *
   * 构造时传入；既用于请求临时密钥，也参与 {@see generateResourceDescription()} 的资源六段式。
   *
   * @var string
   */
  public $region = null;
  /**
   * STS 请求客户端（**按环境二选一**）
   *
   * 构造时创建：装了官方 SDK（`QCloud\COSSTS\Sts`）就用 **SDK 实例**，否则用自带的
   * {@see QCloudSTSClient}。本类的所有实际请求都由它发出；两者接口一致
   * （`getTempKeys($config)` / `getTempKeys4Ci($config)`），故调用方无需关心是哪一个。
   * 设为 private，避免外部绕过本类直接调用。
   *
   * @var QCloudSTSClient|Sts
   */
  private $stsClient = null;
  /**
   * 创建腾讯云 STS 服务实例
   *
   * 创建请求客户端（**有 SDK 用 SDK、否则用自带的**）并保存凭据/存储桶/地域；
   * 同时从 $bucket 的 `-appid` 段解析出 {@see $userId}。
   *
   * @param string $secretId 云 API 密钥 SecretId
   * @param string $secretKey 云 API 密钥 SecretKey（**不要**输出到日志/响应）
   * @param string $region 存储桶所属地域，如 ap-guangzhou
   * @param string $bucket 存储桶名称，形如 bucketName-appid（如 test-125000000）；尾部 appid 会被解析为 $userId
   * @return void
   */
  function __construct($secretId, $secretKey, $region, $bucket)
  {
    //* 二选一：装了官方 STS SDK（vendor/qcloud_sts 的 QCloud\COSSTS\Sts）就用 SDK，
    //* 否则用自带的轻量客户端。两者 getTempKeys($config) / getTempKeys4Ci($config) 接口一致，
    //* 返回值还会再经 handleResponseData() 规整 ⇒ 切换驱动对调用方无感。
    $this->stsClient = class_exists(Sts::class) ? new Sts() : new QCloudSTSClient();

    $this->secretId = $secretId;
    $this->secretKey = $secretKey;
    $this->bucket = $bucket;
    $this->region = $region;
    $this->userId = substr($bucket, 1 + strripos($bucket, '-'));
  }
  /**
   * 把 STS 响应规整成数组
   *
   * 做一次 `json_decode(json_encode(...), true)` 往返：把 stdClass 之类的对象**深转**为关联数组，
   * 便于调用方直接按数组取用；传入 null / 非法值时 json_decode 会返回 null。
   *
   * @param mixed $responseData STS 响应（对象或数组）
   * @return array|null 关联数组；无法转换时返回 null
   */
  protected function handleResponseData($responseData)
  {
    return  json_decode(json_encode($responseData), true);
  }
  /**
   * 获取临时密钥（按前缀 + 操作集合授权，GetFederationToken）
   *
   * 把本实例的凭据与入参拼成 $config 交给 {@see QCloudSTSClient::getTempKeys()}，
   * 由其申请临时密钥（返回键名已小写化）；本方法只做拼装与响应规整。
   *
   * @param string|string[] $allowPrefix 资源前缀：全部资源用 `*`；某目录下全部资源用 `a/*`；单个文件用 `a/test.jpg`
   * @param array $allowActions 授予的 COS API 权限集合，如 `["name/cos:PutObject"]`；权限名见 https://cloud.tencent.com/document/product/436/31923
   * @param integer $durationSeconds 临时密钥最长有效期（秒），默认 1800；由服务端限制，最大 7200
   * 返回的键名已小写化，字段：
   * - credentials：临时密钥信息（含 tmpSecretId / tmpSecretKey / sessionToken）
   * - tmpSecretId / tmpSecretKey：用于计算签名
   * - sessionToken：请求 COS 时放在 Header 的 x-cos-security-token 字段
   * - startTime / expiredTime：密钥起始与失效时间（UNIX 时间戳）
   *
   * @return array 临时密钥
   * @throws \Exception 底层客户端申请失败时抛出（消息可能被包装成文本，原始类型会丢失）
   */
  function getTempKeys($allowPrefix,  $allowActions, $durationSeconds = 1800)
  {
    $config = [
      'secretId' => $this->secretId,
      'secretKey' => $this->secretKey,
      'bucket' => $this->bucket,
      'region' => $this->region,
      'durationSeconds' => $durationSeconds,
      'allowPrefix' => $allowPrefix,
      "allowActions" => $allowActions
    ];

    $tempKeys = $this->stsClient->getTempKeys($config);
    return $this->handleResponseData($tempKeys);
  }
  /**
   * 基于自定义 CAM 策略获取临时密钥
   *
   * 与 {@see getTempKeys()} 同构，区别是不用 allowPrefix/allowActions，而是直接传一条 CAM 策略语句
   * （由 {@see generatePolicyStatement()} 生成、配合 {@see generateResourceDescription()} 使用）。
   *
   * 方法体先按 CAM 策略组装 `$config`（`policy.version` + `policy.statement`），交给
   * {@see $stsClient} 的 `getTempKeys($config)` 真正申请，最后经 {@see handleResponseData()} 规整成数组。
   *
   * @link https://cloud.tencent.com/document/product/436/31923 授权策略使用指引
   * @link https://cloud.tencent.com/document/product/598/10603 策略语法
   *
   * @param array $statement CAM 策略语句（statement 数组），示例：[{"effect":"allow","action":"sts:AssumeRole","resource":"*"}]
   * @param integer $durationSeconds 临时密钥最长有效期（秒），默认 1800，最大 7200
   * @param string $version 策略语法版本，默认 2.0
   * @return array 临时密钥（`tmpSecretId` / `tmpSecretKey` / `sessionToken` / `expiredTime` 等）
   * @throws \Exception 底层客户端申请失败时抛出
   */
  function getTempKeysByPolicy($statement, $durationSeconds = 1800, $version = "2.0")
  {
    $config = [
      'secretId' => $this->secretId,
      'secretKey' => $this->secretKey,
      'bucket' => $this->bucket,
      'region' => $this->region,
      'durationSeconds' => $durationSeconds,
      "policy" => [
        "version" => $version,
        "statement" => $statement
      ]
    ];
    $tempKeys = $this->stsClient->getTempKeys($config);
    return $this->handleResponseData($tempKeys);
  }
  /**
   * 生成 CAM 资源描述（资源六段式的简化拼装）
   *
   * 输出形如 `qcs::{serviceType}:{region}:uid/{userId}:{bucket}/{resourceName}`，
   * 其中 region / userId / bucket 都取自当前实例；\`*\`（星号）代表该类型下的所有资源。
   *
   * 建议与 {@see generatePolicyStatement()} 搭配使用，避免手写资源串出错。
   *
   * @link https://cloud.tencent.com/document/product/598/10606 资源描述方式
   *
   * @param string $resourceName 具体资源：`resource_type/${resourceid}` 或 `<resource_type>/<resource_path>`（后者支持目录级前缀匹配）；`*` 表示所有资源
   * @param string $serviceType 产品简称（CAM 中简称），默认 cos；为空表示所有产品
   * @return string 形如 qcs::cos:ap-guangzhou:uid/125000000:test-125000000/a/*
   */
  function generateResourceDescription($resourceName, $serviceType = "cos")
  {
    return "qcs::{$serviceType}:{$this->region}:uid/{$this->userId}:{$this->bucket}/{$resourceName}";
  }
  /**
   * 生成一条 CAM 策略语句（statement）
   *
   * 返回键名固定为 CAM 的小写格式：`action` / `resource` / `effect` / `condition`；
   * `condition` 即使为空数组也会带上（等价于无额外约束）。本方法**只做拼装、不做任何校验**。
   *
   * 建议 resource 用 {@see generateResourceDescription()} 生成；多条语句可组成策略的 statement 数组。
   *
   * @link https://cloud.tencent.com/document/product/598/10604 语法结构
   *
   * @param array|string $action 允许或拒绝的操作（API 名或功能集）；`*` 为所有操作
   * @param array|string $resource 授权的具体资源（六段式）；`*` 为所有资源
   * @param string $effect 结果：allow（允许）或 deny（显式拒绝），默认 allow
   * @param array $condition 生效约束条件（操作符/操作键/操作值），默认空数组
   * @return array 形如 ["action" => ..., "resource" => ..., "effect" => "allow", "condition" => []]
   */
  function generatePolicyStatement($action, $resource, $effect = "allow", $condition = [])
  {
    return [
      "action" => $action,
      "resource" => $resource,
      "effect" => $effect,
      "condition" => $condition
    ];
  }
}
