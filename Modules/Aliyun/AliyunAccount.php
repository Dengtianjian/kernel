<?php

namespace kernel\Modules\Aliyun;

/**
 * 阿里云账号（凭证）
 *
 * 只装 accessKey 与 secret，不含任何请求逻辑——签名与请求分别见
 * {@see AliyunSignature} 与 {@see AliyunRequest}。
 *
 * 实例由 {@see AliyunProvider} 在装配期创建并登记（短名 `aliyun`），
 * 业务侧通过门面 {@see Aliyun} 或 `$app->aliyun()` 取用。
 *
 * @see AliyunProvider
 */
class AliyunAccount
{
  /**
   * 账号标识（AccessKey ID，或业务自定义的账号名）
   *
   * @var string
   */
  protected $AppId = "";
  /**
   * 密钥（AccessKey Secret）
   *
   * @var string
   */
  protected $AppSecret = "";

  /**
   * @param string $appId 账号标识
   * @param string $appSecret 密钥
   */
  function __construct($appId, $appSecret)
  {
    $this->AppId = $appId;
    $this->AppSecret = $appSecret;
  }

  /**
   * 账号标识
   *
   * @return string
   */
  public function appId()
  {
    return $this->AppId;
  }

  /**
   * 密钥
   *
   * @return string
   */
  public function appSecret()
  {
    return $this->AppSecret;
  }
}
