<?php

namespace kernel\Platform\DiscuzX\Foundation\Storage\QCloud;

use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Storage\StorageSignature;
use kernel\Platform\DiscuzX\Foundation\Storage\QCloud\QCloudSTS\DiscuzXQCloudSTS;
use kernel\Platform\QCloud\QCloud;
use kernel\Platform\QCloud\QCloudCos\QCloudCosSignture;
use kernel\Platform\QCloud\QCloudCos\QCloudCOSStorage;

/**
 * 适用于DiscuzX的 腾讯云 COS 存储类  
 * 因为 DiscuzX 不好安装 腾讯云 COS SDK，所以用这个替代，主要是发送请求到腾讯云作处理
 */
class DiscuzXQCloudCOS extends QCloud
{
  protected $secretId = null;
  protected $secretKey = null;
  protected $region = null;
  protected $bucket = null;

  /**
   * 签名实例
   *
   * @var QCloudCosSignture
   */
  protected $signatureInstance = null;

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
   * @param string $PluginId 插件 ID
   */
  public function __construct(
    $secretId,
    $secretKey,
    $region,
    $bucket
  ) {
    $host = "http://" . join(".", [
      $bucket,
      "cos",
      $region,
      "myqcloud.com"
    ]);

    parent::__construct($secretId, $secretKey, null, $host);

    $this->region = $region;
    $this->bucket = $bucket;

    $this->signatureInstance = new QCloudCosSignture($secretId, $secretKey, $region, $bucket);
  }
  public function getObjectSign($fileKey = null, $Expires = 1800, $HTTPMethod = "get", $URLParams = [], $Headers = [])
  {
    if (strpos($fileKey, "/") !== 0) {
      $fileKey = "/" . $fileKey;
    }

    return $this->signatureInstance->createAuthorization($fileKey, $URLParams, $Headers, $Expires, $HTTPMethod);
  }
  public function doesObjectExist($objectName)
  {
    $this->Curl->url($this->Host . "/{$objectName}", $this->getObjectSign($objectName, 300, "head"));

    $this->Curl->https(false);
    $this->Curl->timeout(5);

    $this->Curl->head();

    $response = $this->Curl->getData();
    $responseHeader = $this->Curl->responseHeaders();

    if ($this->Curl->errorNo()) return $this->break(500, 500, $this->Curl->error(), $this->Curl->error());

    return $this->Curl->statusCode() === 200;
  }
  public function deleteObject($objectName)
  {
    $this->Curl->url($this->Host . "/{$objectName}", $this->getObjectSign($objectName, 300, "delete"));

    $this->Curl->https(false);

    $this->Curl->delete();

    $response = $this->Curl->getData();
    $responseHeader = $this->Curl->responseHeaders();

    if ($this->Curl->errorNo()) return $this->break(500, 500, $this->Curl->error(), $this->Curl->error());
    if ($this->Curl->statusCode() !== 204) {
      return $this->break($this->Curl->statusCode(), $this->Curl->statusCode() . ":" . $response['Code'], $response['Message'], $response);
    }

    return true;
  }
}
