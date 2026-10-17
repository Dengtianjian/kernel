<?php

namespace kernel\Modules\QCloud;

use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\Result;

/**
 * 腾讯云 API 请求客户端（TC3-HMAC-SHA256）
 *
 * 以腾讯云账号（{@see QCloudAccount}）为凭据来源，封装对腾讯云云 API
 * （`*.tencentcloudapi.com`）的 HTTP 请求：按 TC3-HMAC-SHA256 算法签名、发送
 * GET/POST 请求、把响应归一化为 {@see Result}。各云产品（人脸核身、COS 等）
 * 在本类基础上扩展具体接口。
 *
 * 与 {@see \kernel\Modules\Wechat\WechatApi} 对称：账号负责身份（{@see QCloudAccount}），
 * 本类负责请求；操作的服务名 `$service`（决定请求域名）与请求地址由子类或构造参数给出。
 * 想省掉手动装配账号，可走模块门面（见 {@see QCloud}）：`QCloud::account("main")->api("faceid")`。
 *
 * 凭据优先级：本实例上的**临时凭据**（`tmpSecretId` / `tmpSecretKey` / `tmpSecurityToken`，
 * 见 {@see tmpCredentials()}）→ 账号上的凭据。临时凭据非空时，请求会自动附带
 * `X-TC-Token` 头并一并纳入签名；{@see cancelTmpCredentials()} 可一次性撤销临时凭据，
 * 回到账号凭据。
 *
 * 使用示例：
 *
 * ```php
 * $api = new QCloudApi($account, "faceid");
 * $result = $api->post("BankCard2EVerification", "2018-03-01", ["Name" => $name, "BankCard" => $no]);
 *
 * // 临时凭据（如 STS 换取的）：只影响本实例后续请求
 * $api->tmpCredentials($tmpSecretId, $tmpSecretKey, $sessionToken);
 * $api->cancelTmpCredentials();
 * ```
 */
class QCloudApi extends AbilityBaseObject
{
  /**
   * 腾讯云账号（凭据来源）
   *
   * @var QCloudAccount|null
   */
  protected $account = null;
  /**
   * 临时的 SecretId  
   * 如果该值存在，优先使用该值
   *
   * @var string
   */
  protected $tmpSecretId = null;
  /**
   * 临时的 SecretKey
   * 如果该值存在，优先使用该值
   *
   * @var string
   */
  protected $tmpSecretKey = null;
  /**
   * 临时的安全令牌
   * 如果该值存在，优先使用该值
   *
   * @var string
   */
  protected $tmpSecurityToken = null;
  /**
   * 请求的主机，腾讯云的
   *
   * @var string
   */
  protected $host = "tencentcloudapi.com";
  /**
   * 加密算法
   *
   * @var string
   */
  protected $algorithm = "TC3-HMAC-SHA256";
  /**
   * 操作的服务名称
   *
   * @var string
   */
  private $service = null;
  /**
   * CURL实例
   *
   * @var Curl
   */
  protected $curl = null;
  /**
   * 实例化腾讯云请求客户端
   *
   * @param QCloudAccount $account 腾讯云账号，提供 SecretId / SecretKey 等凭据
   * @param string $service 操作的服务名称，如 `faceid`；非空时请求域名拼成 `{service}.{host}`
   * @param string $host 接口请求地址
   */
  public function __construct(QCloudAccount $account, $service = null, $host = null)
  {
    $this->account = $account;

    if (!is_null($host)) {
      $this->host = $host;
    }
    if (!is_null($service)) {
      $this->service = $service;
      $this->host = $service . "." . $this->host;
    }
    $this->curl = new Curl();
    $this->curl->https(false)->url(strpos($this->host, "http") === false ? "https://" . $this->host : $this->host);
  }

  /**
   * 读写一体：获取或设置腾讯云账号
   *
   * 依据调用时是否传入参数区分读写：
   * - 传入参数：设置账号，并返回当前实例以支持链式调用；
   * - 不传参数：返回当前已设置的账号。
   *
   * ```php
   * $api->account($account);  // 设置，返回 $api
   * $api->account();          // 读取，返回 QCloudAccount
   * ```
   *
   * @param QCloudAccount|null $data 待设置的腾讯云账号
   * @return static|QCloudAccount|null 设置时返回当前实例；读取时返回账号，未设置时为 null
   */
  function account($data = null)
  {
    if (func_num_args()) {
      $this->account = $data;
      return $this;
    }

    return $this->account;
  }
  /**
   * 设置临时SecretId
   *
   * @param string $tmpSecretId 新的临时SecretId，如果传入null，即为使用账号上的SecretId
   * @return QCloudApi
   */
  function tmpSecretId($tmpSecretId = null)
  {
    $this->tmpSecretId = $tmpSecretId;

    return $this;
  }
  /**
   * 设置临时SecretKey
   *
   * @param string $tmpSecretKey 新的临时SecretKey，如果传入null，即为使用账号上的SecretKey
   * @return QCloudApi
   */
  function tmpSecretKey($tmpSecretKey = null)
  {
    $this->tmpSecretKey = $tmpSecretKey;

    return $this;
  }
  /**
   * 设置临时安全令牌
   *
   * @param string $tmpSecurityToken 新的临时安全令牌，如果传入null，即为使用账号上的安全令牌
   * @return QCloudApi
   */
  function tmpSecurityToken($tmpSecurityToken = null)
  {
    $this->tmpSecurityToken = $tmpSecurityToken;

    return $this;
  }
  /**
   * 设置临时凭证
   *
   * 三个值都非空时，后续请求一律用临时凭证签名，并自动附带 X-TC-Token 头。
   *
   * @param string $tmpSecretId  临时SecretId
   * @param string $tmpSecretKey 临时SecretKey
   * @param string $securityToken 安全令牌
   * @return QCloudApi
   */
  function tmpCredentials($tmpSecretId, $tmpSecretKey, $securityToken)
  {
    $this->tmpSecretId = $tmpSecretId;
    $this->tmpSecretKey = $tmpSecretKey;
    $this->tmpSecurityToken = $securityToken;

    return $this;
  }
  /**
   * 取消使用临时凭证，使用账号上的凭证
   *
   * @return QCloudApi
   */
  function cancelTmpCredentials()
  {
    $this->tmpSecretId = null;
    $this->tmpSecretKey = null;
    $this->tmpSecurityToken = null;

    return $this;
  }
  /**
   * 获取实际使用的SecretId  
   * 如果存在临时SecretId就返回临时的，否则返回账号上的
   *
   * @return string
   */
  protected function getSecretId()
  {
    if ($this->tmpSecretId) {
      return $this->tmpSecretId;
    }

    return $this->account ? $this->account->secretId() : null;
  }
  /**
   * 获取实际使用的SecretKey
   * 如果存在临时SecretKey就返回临时的，否则返回账号上的
   *
   * @return string
   */
  protected function getSecretKey()
  {
    if ($this->tmpSecretKey) {
      return $this->tmpSecretKey;
    }

    return $this->account ? $this->account->secretKey() : null;
  }
  /**
   * 获取实际使用的安全令牌
   * 如果存在临时安全令牌就返回临时的，否则返回账号上的
   *
   * @return string
   */
  protected function getSecurityToken()
  {
    if ($this->tmpSecurityToken) {
      return $this->tmpSecurityToken;
    }

    return $this->account ? $this->account->securityToken() : null;
  }
  /**
   * 生成授权信息
   *
   * 凭据取自 {@see getSecretId()} / {@see getSecretKey()}（临时凭据优先）；
   * 使用临时凭据时，安全令牌会以 `x-tc-token` 参与签名，并在请求头中送出。
   *
   * @param int $timestamp 用于生成签名时间戳：秒级
   * @param string $action 操作的接口名称
   * @param array $body 请求体
   * @param array $query 查询信息
   * @param string $canonicalURI URI参数
   * @param string $httpRequestMethod 请求方法
   * @return string 授权信息
   */
  protected function generateAuthorizaion($timestamp, $action, $body = [], $query = [], $canonicalURI = "/", $httpRequestMethod = "POST")
  {
    $securityToken = $this->getSecurityToken();

    $canonicalHeaderLines = [
      "content-type:application/json; charset=utf-8",
      "host:" . $this->host,
      "x-tc-action:" . strtolower($action),
    ];
    $signedHeaderNames = [
      "content-type",
      "host",
      "x-tc-action",
    ];
    //* 临时凭据必须随请求送出 X-TC-Token，并一并纳入签名
    if ($securityToken) {
      $canonicalHeaderLines[] = "x-tc-token:" . $securityToken;
      $signedHeaderNames[] = "x-tc-token";
    }
    $canonicalHeaders = implode("\n", $canonicalHeaderLines) . "\n";

    $signedHeaders = implode(";", $signedHeaderNames);

    $payload = "";
    if ($body) {
      $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    $queryString = http_build_query($query);

    $hashedRequestPayload = hash("SHA256", $payload);
    $canonicalRequest = implode("\n", [
      $httpRequestMethod,
      $canonicalURI,
      $queryString,
      $canonicalHeaders,
      $signedHeaders,
      $hashedRequestPayload
    ]);

    $date = gmdate("Y-m-d", $timestamp);
    $credentialScope = $date . "/" . $this->service . "/tc3_request";
    $hashedCanonicalRequest = hash("SHA256", $canonicalRequest);
    $stringToSign = $this->algorithm . "\n"
      . $timestamp . "\n"
      . $credentialScope . "\n"
      . $hashedCanonicalRequest;

    $secretDate = hash_hmac("SHA256", $date, "TC3" . $this->getSecretKey(), true);
    $secretService = hash_hmac("SHA256", $this->service, $secretDate, true);
    $secretSigning = hash_hmac("SHA256", "tc3_request", $secretService, true);
    $signature = hash_hmac("SHA256", $stringToSign, $secretSigning);

    return implode("", [
      $this->algorithm,
      " Credential=",
      $this->getSecretId(),
      "/",
      $credentialScope,
      ", SignedHeaders=",
      $signedHeaders,
      ", Signature=",
      $signature
    ]);
  }
  /**
   * 发送GET请求
   *
   * @param string $action 操作的名称
   * @param string $version 服务版本
   * @param array $query 查询信息
   * @return Result
   */
  public function get($action, $version, $query = [])
  {
    $timestamp = time();

    $headers = [
      "Authorization" => $this->generateAuthorizaion($timestamp, $action, [], $query, "/", "POST"),
      "Content-Type" => "application/json; charset=utf-8",
      "Host" => $this->host,
      "X-TC-Action" => $action,
      "X-TC-Timestamp" => $timestamp,
      "X-TC-Version" => $version,
    ];
    //* 临时凭据需要附带会话令牌头，且必须与签名里的 x-tc-token 一致
    $securityToken = $this->getSecurityToken();
    if ($securityToken) {
      $headers["X-TC-Token"] = $securityToken;
    }
    $this->curl->headers($headers);

    $response = $this->curl->get($query);

    $r = new Result(true);
    if ($response->errorNo()) {
      return $r->error(false, 500, $response->errorNo(), "服务器错误", $response->error());
    }
    $responseData = $response->getData()['Response'];
    if (isset($responseData['Error'])) {
      return $r->error(500, $responseData['Error']['Code'], "服务器错误", $responseData);
    }
    if ($responseData['Result'] < 0) {
      return $r->error(400, "400-" . $responseData['Result'], $responseData['Description'], $responseData);
    }

    return $r->success($responseData);
  }
  /**
   * 发送POST请求
   *
   * @param string $action 操作的名称
   * @param string $version 服务版本
   * @param array $body 请求体
   * @param array $query 查询信息
   * @return Result
   */
  public function post($action, $version, $body = [], $query = [])
  {
    $timestamp = time();

    $headers = [
      "Authorization" => $this->generateAuthorizaion($timestamp, $action, $body, $query, "/", "POST"),
      "Content-Type" => "application/json; charset=utf-8",
      "Host" => $this->host,
      "X-TC-Action" => $action,
      "X-TC-Timestamp" => $timestamp,
      "X-TC-Version" => $version,
    ];
    //* 临时凭据需要附带会话令牌头，且必须与签名里的 x-tc-token 一致
    $securityToken = $this->getSecurityToken();
    if ($securityToken) {
      $headers["X-TC-Token"] = $securityToken;
    }
    $this->curl->headers($headers);

    $response = $this->curl->post($body);
    $r = new Result(true);
    if ($response->errorNo()) {
      return $r->error(false, 500, $response->errorNo(), "服务器错误", $response->error());
    }
    $responseData = $response->getData()['Response'];
    if (isset($responseData['Error'])) {
      return $r->error(500, $responseData['Error']['Code'], "服务器错误", $responseData);
    }
    if ($responseData['Result'] < 0) {
      return $r->error(400, "400-" . $responseData['Result'], $responseData['Description'], $responseData);
    }

    return $r->success($responseData);
  }
}
