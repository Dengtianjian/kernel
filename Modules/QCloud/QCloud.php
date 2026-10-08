<?php

namespace kernel\Modules\QCloud;

use kernel\Foundation\Object\BaseObject;
use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Foundation\Result;

class QCloud extends AbilityBaseObject
{
  /**
   * 密钥对中的 SecretId
   *
   * @var string
   */
  protected $secretId = null;
  /**
   * 临时的 SecretId  
   * 如果该值存在，优先使用该值
   *
   * @var string
   */
  protected $tmpSecretId = null;
  /**
   * 原始的 SecretKey
   *
   * @var string
   */
  protected $secretKey = null;
  /**
   * 临时的 SecretKey
   * 如果该值存在，优先使用该值
   *
   * @var string
   */
  protected $tmpSecretKey = null;
  /**
   * 安全令牌。使用临时SecretId、SecretKey时该值不可为空
   *
   * @var string
   */
  protected $securityToken = null;
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
   * 实例化腾讯云类
   *
   * @param string $secretId 密钥对中的 SecretId
   * @param string $secretKey 原始的 SecretKey
   * @param string $service 操作的服务名称
   * @param string $host 接口请求地址
   * @param string $securityToken 安全令牌。使用临时SecretId、SecretKey时该值不可为空
   * @param string $tmpSecretId 临时的 SecretId，优先使用该值
   * @param string $tmpSecretKey 临时的 SecretKey，优先使用该值
   */
  public function __construct($secretId, $secretKey, $service = null, $host = null, $securityToken = null, $tmpSecretId = null, $tmpSecretKey = null)
  {
    $this->secretId = $secretId;
    $this->secretKey = $secretKey;
    $this->securityToken = $securityToken;
    $this->tmpSecretId = $tmpSecretId;
    $this->tmpSecretKey = $tmpSecretKey;

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
   * 设置临时SecretId
   *
   * @param string $tmpSecretId 新的临时SecretId，如果传入null，即为使用永久的SecretId
   * @return this
   */
  function tmpSecretId($tmpSecretId = null)
  {
    $this->tmpSecretId = $tmpSecretId;

    return $this;
  }
  /**
   * 设置临时SecretKey
   *
   * @param string $tmpSecretKey 新的临时SecretKey，如果传入null，即为使用永久的SecretKey
   * @return this
   */
  function tmpSecretKey($tmpSecretKey = null)
  {
    $this->tmpSecretKey = $tmpSecretKey;

    return $this;
  }
  /**
   * 设置安全令牌
   *
   * @param string $securityToken 新的安全令牌，如果传入null，即为不使用安全令牌
   * @return this
   */
  function securityToken($securityToken = null)
  {
    $this->securityToken = $securityToken;

    return $this;
  }
  /**
   * 设置临时凭证
   *
   * @param string $tmpSecretId  临时SecretId
   * @param string $tmpSecretKey 临时SecretKey
   * @param string $securityToken 安全令牌
   * @return this
   */
  function tmpCredentials($tmpSecretId, $tmpSecretKey, $securityToken)
  {
    $this->tmpSecretId = $tmpSecretId;
    $this->tmpSecretKey = $tmpSecretKey;
    $this->securityToken = $securityToken;

    return $this;
  }
  /**
   * 取消使用临时凭证，使用会永久凭证
   *
   * @return this
   */
  function cancelTmpCredentials()
  {
    $this->tmpSecretId = null;
    $this->tmpSecretKey = null;
    $this->securityToken = null;

    return $this;
  }
  /**
   * 获取实际使用的SecretId  
   * 如果存在临时SecretId就返回临时的，否则返回默认的
   *
   * @return string
   */
  protected function getSecretId()
  {
    if ($this->tmpSecretId) {
      return $this->tmpSecretId;
    }

    return $this->secretId;
  }
  /**
   * 获取实际使用的SecretKey
   * 如果存在临时SecretKey就返回临时的，否则返回默认的
   *
   * @return string
   */
  protected function getSecretKey()
  {
    if ($this->tmpSecretKey) {
      return $this->tmpSecretKey;
    }

    return $this->secretKey;
  }
  /**
   * 生成授权信息
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
    $canonicalHeaders = implode("\n", [
      "content-type:application/json; charset=utf-8",
      "host:" . $this->host,
      "x-tc-action:" . strtolower($action),
      ""
    ]);

    $signedHeaders = implode(";", [
      "content-type",
      "host",
      "x-tc-action",
    ]);

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

    $secretDate = hash_hmac("SHA256", $date, "TC3" . $this->secretKey, true);
    $secretService = hash_hmac("SHA256", $this->service, $secretDate, true);
    $secretSigning = hash_hmac("SHA256", "tc3_request", $secretService, true);
    $signature = hash_hmac("SHA256", $stringToSign, $secretSigning);

    return implode("", [
      $this->algorithm,
      " Credential=",
      $this->secretId,
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

    $this->curl->headers([
      "Authorization" => $this->generateAuthorizaion($timestamp, $action, null, $query, "/", "POST"),
      "Content-Type" => "application/json; charset=utf-8",
      "Host" => $this->host,
      "X-TC-Action" => $action,
      "X-TC-Timestamp" => $timestamp,
      "X-TC-Version" => $version,
    ]);

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

    $this->curl->headers([
      "Authorization" => $this->generateAuthorizaion($timestamp, $action, $body, $query, "/", "POST"),
      "Content-Type" => "application/json; charset=utf-8",
      "Host" => $this->host,
      "X-TC-Action" => $action,
      "X-TC-Timestamp" => $timestamp,
      "X-TC-Version" => $version,
    ]);

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
