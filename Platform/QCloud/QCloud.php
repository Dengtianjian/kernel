<?php

namespace kernel\Platform\QCloud;

use kernel\Foundation\Object\BaseObject;
use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\ReturnResult\ReturnResult;

class QCloud extends BaseObject
{
  /**
   * 密钥对中的 SecretId
   *
   * @var string
   */
  protected $SecretId = null;
  /**
   * 临时的 SecretId  
   * 如果该值存在，优先使用该值
   *
   * @var string
   */
  protected $TmpSecretId = null;
  /**
   * 原始的 SecretKey
   *
   * @var string
   */
  protected $SecretKey = null;
  /**
   * 临时的 SecretKey
   * 如果该值存在，优先使用该值
   *
   * @var string
   */
  protected $TmpSecretKey = null;
  /**
   * 安全令牌。使用临时SecretId、SecretKey时该值不可为空
   *
   * @var string
   */
  protected $SecurityToken = null;
  /**
   * 请求的主机，腾讯云的
   *
   * @var string
   */
  protected $Host = "tencentcloudapi.com";
  /**
   * 加密算法
   *
   * @var string
   */
  protected $ALgorithm = "TC3-HMAC-SHA256";
  /**
   * 操作的服务名称
   *
   * @var string
   */
  private $Service = null;
  /**
   * CURL实例
   *
   * @var Curl
   */
  private $Curl = null;
  /**
   * 实例化腾讯云类
   *
   * @param string $SecretId 密钥对中的 SecretId
   * @param string $SecretKey 原始的 SecretKey
   * @param string $Service 操作的服务名称
   * @param string $Host 接口请求地址
   * @param string $SecurityToken 安全令牌。使用临时SecretId、SecretKey时该值不可为空
   * @param string $TmpSecretId 临时的 SecretId，优先使用该值
   * @param string $TmpSecretKey 临时的 SecretKey，优先使用该值
   */
  public function __construct($SecretId, $SecretKey, $Service = null, $Host = null, $SecurityToken = null, $TmpSecretId = null, $TmpSecretKey = null)
  {
    $this->SecretId = $SecretId;
    $this->SecretKey = $SecretKey;
    $this->SecurityToken = $SecurityToken;
    $this->TmpSecretId = $TmpSecretId;
    $this->TmpSecretKey = $TmpSecretKey;

    if (!is_null($Host)) {
      $this->Host = $Host;
    }
    if (!is_null($Service)) {
      $this->Service = $Service;
      $this->Host = $Service . "." . $this->Host;
    }
    $this->Curl = new Curl();
    $this->Curl->https(false)->url(strpos($this->Host, "http") === false ? "https://" . $this->Host : $this->Host);
  }

  /**
   * 设置临时SecretId
   *
   * @param string $tmpSecretId 新的临时SecretId，如果传入null，即为使用永久的SecretId
   * @return this
   */
  function tmpSecretId($tmpSecretId = null)
  {
    $this->TmpSecretId = $tmpSecretId;

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
    $this->TmpSecretKey = $tmpSecretKey;

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
    $this->SecurityToken = $securityToken;

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
    $this->TmpSecretId = $tmpSecretId;
    $this->TmpSecretKey = $tmpSecretKey;
    $this->SecurityToken = $securityToken;

    return $this;
  }
  /**
   * 取消使用临时凭证，使用会永久凭证
   *
   * @return this
   */
  function cancelTmpCredentials()
  {
    $this->TmpSecretId = null;
    $this->TmpSecretKey = null;
    $this->SecurityToken = null;

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
    if ($this->TmpSecretId) {
      return $this->TmpSecretId;
    }

    return $this->SecretId;
  }
  /**
   * 获取实际使用的SecretKey
   * 如果存在临时SecretKey就返回临时的，否则返回默认的
   *
   * @return string
   */
  protected function getSecretKey()
  {
    if ($this->TmpSecretKey) {
      return $this->TmpSecretKey;
    }

    return $this->SecretKey;
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
    $CanonicalHeaders = implode("\n", [
      "content-type:application/json; charset=utf-8",
      "host:" . $this->Host,
      "x-tc-action:" . strtolower($action),
      ""
    ]);

    $SignedHeaders = implode(";", [
      "content-type",
      "host",
      "x-tc-action",
    ]);

    $payload = "";
    if ($body) {
      $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    $QueryString = http_build_query($query);

    $HashedRequestPayload = hash("SHA256", $payload);
    $CanonicalRequest = implode("\n", [
      $httpRequestMethod,
      $canonicalURI,
      $QueryString,
      $CanonicalHeaders,
      $SignedHeaders,
      $HashedRequestPayload
    ]);

    $Date = gmdate("Y-m-d", $timestamp);
    $CredentialScope = $Date . "/" . $this->Service . "/tc3_request";
    $HashedCanonicalRequest = hash("SHA256", $CanonicalRequest);
    $StringToSign = $this->ALgorithm . "\n"
      . $timestamp . "\n"
      . $CredentialScope . "\n"
      . $HashedCanonicalRequest;

    $SecretDate = hash_hmac("SHA256", $Date, "TC3" . $this->SecretKey, true);
    $SecretService = hash_hmac("SHA256", $this->Service, $SecretDate, true);
    $SecretSigning = hash_hmac("SHA256", "tc3_request", $SecretService, true);
    $Signature = hash_hmac("SHA256", $StringToSign, $SecretSigning);

    return implode("", [
      $this->ALgorithm,
      " Credential=",
      $this->SecretId,
      "/",
      $CredentialScope,
      ", SignedHeaders=",
      $SignedHeaders,
      ", Signature=",
      $Signature
    ]);
  }
  /**
   * 发送GET请求
   *
   * @param string $action 操作的名称
   * @param string $version 服务版本
   * @param array $query 查询信息
   * @return ReturnResult
   */
  public function get($action, $version, $query = [])
  {
    $timestamp = time();

    $this->Curl->headers([
      "Authorization" => $this->generateAuthorizaion($timestamp, $action, null, $query, "/", "POST"),
      "Content-Type" => "application/json; charset=utf-8",
      "Host" => $this->Host,
      "X-TC-Action" => $action,
      "X-TC-Timestamp" => $timestamp,
      "X-TC-Version" => $version,
    ]);

    $Response = $this->Curl->get($query);

    $R = new ReturnResult(true);
    if ($Response->errorNo()) {
      return $R->error(false, 500, $Response->errorNo(), "服务器错误", $Response->error());
    }
    $ResponseData = $Response->getData()['Response'];
    if (isset($ResponseData['Error'])) {
      return $R->error(500, $ResponseData['Error']['Code'], "服务器错误", $ResponseData);
    }
    if ($ResponseData['Result'] < 0) {
      return $R->error(400, "400-" . $ResponseData['Result'], $ResponseData['Description'], $ResponseData);
    }

    return $R->success($ResponseData);
  }
  /**
   * 发送POST请求
   *
   * @param string $action 操作的名称
   * @param string $version 服务版本
   * @param array $body 请求体
   * @param array $query 查询信息
   * @return ReturnResult
   */
  public function post($action, $version, $body = [], $query = [])
  {
    $timestamp = time();

    $this->Curl->headers([
      "Authorization" => $this->generateAuthorizaion($timestamp, $action, $body, $query, "/", "POST"),
      "Content-Type" => "application/json; charset=utf-8",
      "Host" => $this->Host,
      "X-TC-Action" => $action,
      "X-TC-Timestamp" => $timestamp,
      "X-TC-Version" => $version,
    ]);

    $Response = $this->Curl->post($body);
    $R = new ReturnResult(true);
    if ($Response->errorNo()) {
      return $R->error(false, 500, $Response->errorNo(), "服务器错误", $Response->error());
    }
    $ResponseData = $Response->getData()['Response'];
    if (isset($ResponseData['Error'])) {
      return $R->error(500, $ResponseData['Error']['Code'], "服务器错误", $ResponseData);
    }
    if ($ResponseData['Result'] < 0) {
      return $R->error(400, "400-" . $ResponseData['Result'], $ResponseData['Description'], $ResponseData);
    }

    return $R->success($ResponseData);
  }
}
