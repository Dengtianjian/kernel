<?php

namespace kernel\Foundation\Storage;

abstract class AbstractOSSStroage extends AbstractStorage
{
  protected $platform = null;
  protected $client = null;
  protected $stsClient = null;
  protected $SDKClient = null;
  protected $bucket = null;
  protected $region = null;

  protected $secretId = null;
  protected $secretKey = null;

  /**
   * 实例化抽象 OSS 存储
   *
   * @param string $secretId 密钥 ID
   * @param string $secretKey 密钥
   * @param string $region 存储桶所在的地区
   * @param string $bucket 存储桶名称
   * @param string $SignatureKey 生成签名的密钥，框架用于生成链接、上传授权等签名的密钥值
   * @param string $RoutePrefix 路由前缀，默认 files
   * @param string $BaseURL 基础URL 地址
   * @param string $Platform 平台名称
   */
  public function __construct($secretId, $secretKey, $region, $bucket, $SignatureKey = "ruyi_storage", $RoutePrefix = "files", $BaseURL = F_BASE_URL, $Platform = "local")
  {
    $this->secretId = $secretId;
    $this->secretKey = $secretKey;
    $this->region = $region;
    $this->bucket = $bucket;

    $this->loadSDK();

    parent::__construct($SignatureKey, $RoutePrefix, $BaseURL, $Platform);
  }

  protected function loadSDK()
  {

    return $this;
  }

  public function bucket($name = null)
  {
    if (func_num_args()) {
      $this->bucket = $name;
      return $this;
    };

    return $this->bucket;
  }
  public function region($name = null)
  {
    if (func_num_args()) {
      $this->region = $name;

      $this->loadSDK();

      return $this;
    };

    return $this->region;
  }
}
