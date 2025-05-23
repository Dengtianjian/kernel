<?php

namespace kernel\Platform\DiscuzX\Foundation\Storage\QCloud;

use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\Storage\StorageSignature;
use kernel\Platform\DiscuzX\Foundation\Storage\QCloud\QCloudSTS\DiscuzXQCloudSTS;
use kernel\Platform\QCloud\QCloudCos\QCloudCOSStorage;

class DiscuzXQCloudCOSStorage extends QCloudCOSStorage
{
  protected $pluginId = null;

  /**
   * COS SDK实例
   *
   * @var DiscuzXQCloudCOS
   */
  protected $SDKClient = null;

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
    $bucket,
    $SignatureKey = "ruyi_storage",
    $RoutePrefix = "files",
    $BaseURL = F_BASE_URL,
    $PluginId = F_APP_ID
  ) {
    $this->pluginId = $PluginId;

    parent::__construct($secretId, $secretKey, $region, $bucket, $SignatureKey, $RoutePrefix, $BaseURL);
  }

  protected function loadSDK()
  {
    $this->STSClient = new DiscuzXQCloudSTS($this->secretId, $this->secretKey, $this->region, $this->bucket);

    $this->SDKClient = new DiscuzXQCloudCOS($this->secretId, $this->secretKey, $this->region, $this->bucket);

    return $this;
  }
  public function getFilePreviewURL($fileKey, $URLParams = [], $Expires = 1800)
  {
    unset($URLParams['id'], $URLParams['uri']);
    return $this->getObjectAuthUrl($fileKey, "get", $URLParams, [], $Expires, false);
  }
  public function getFileDownloadURL($fileKey, $URLParams = [], $Expires = 1800)
  {
    unset($URLParams['id'], $URLParams['uri']);
    return $this->getObjectAuthUrl($fileKey, "get", $URLParams, [], $Expires, false);
  }
  public function getFileTransferPreviewURL($fileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new URL($this->baseURL);
    $AccessURL->pathName = "plugin.php";
    $AccessURL->queryParam("{$this->routePrefix}/{$fileKey}/preview", "uri");
    $AccessURL->queryParam($this->pluginId, "id");

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileTransferAuth($fileKey, $Expires, $URLParams, []));
      if (array_key_exists("auth", $URLParams)) {
        unset($URLParams['auth']);
      }
    }
    $URLParams = array_map(function ($item) {
      return urldecode(rawurldecode($item));
    }, $URLParams);

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  public function getFileTransferDownloadURL($fileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new URL($this->baseURL);
    $AccessURL->pathName = "plugin.php";
    $AccessURL->queryParam("{$this->routePrefix}/{$fileKey}/download", "uri");
    $AccessURL->queryParam($this->pluginId, "id");

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileTransferAuth($fileKey, $Expires, $URLParams, []));
      if (array_key_exists("auth", $URLParams)) {
        unset($URLParams['auth']);
      }
    }
    $URLParams = array_map(function ($item) {
      return urldecode(rawurldecode($item));
    }, $URLParams);

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  public function verifyAuth($FileKey, $RawURLParams, $RawHeaders = [], $HTTPMethod = "get")
  {
    $URLParamKeys = ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"];
    $DiscuzXPluginParamKeys = ["id", "uri"];

    foreach ($URLParamKeys as $key) {
      if (!array_key_exists($key, $RawURLParams)) {
        return $this->break(400, "verifyAuth:400001", "缺少参数");
      }
    }

    $SignAlgorithm = $RawURLParams['sign-algorithm'];
    $SignTime = urldecode($RawURLParams['sign-time']);
    $KeyTime = urldecode($RawURLParams['key-time']);
    $HeaderList = $RawURLParams['header-list'] ? explode(";", urldecode($RawURLParams['header-list'])) : [];
    $URLParamList = $RawURLParams['url-param-list'] ? explode(";", rawurldecode(urldecode($RawURLParams['url-param-list']))) : [];
    if ($URLParamList) {
      $URLParamList = array_map(function ($item) {
        return rawurldecode($item);
      }, $URLParamList);
    }
    $Signature = $RawURLParams['signature'];

    if ($SignAlgorithm !== StorageSignature::getSignAlgorithm()) return $this->break(400, "verifyAuth:400002", "参数错误");
    if (strpos($SignTime, ";") === false || strpos($KeyTime, ";") === false) return $this->break(400, "verifyAuth:400003", "参数错误");
    if ($SignTime !== $KeyTime) return $this->break(400, "verifyAuth:400004", "参数错误");;
    list($startTime, $endTime) = explode(";", $SignTime);
    list($keyStartTime, $keyEndTime) = explode(";", $KeyTime);
    $startTime = intval($startTime);
    $endTime = intval($endTime);
    $keyStartTime = intval($keyStartTime);
    $keyEndTime = intval($keyEndTime);
    if ($endTime < $startTime) return $this->break(400, "verifyAuth:400005", "验证信息已过期");
    if ($endTime < time()) return $this->break(400, "verifyAuth:400006", "验证信息已过期");
    if ($keyEndTime < $keyStartTime) return $this->break(400, "verifyAuth:400007", "验证信息已过期");
    if ($keyEndTime < time()) return $this->break(400, "verifyAuth:400008", "验证信息已过期");

    $Headers = [];
    if ($HeaderList) {
      foreach ($RawHeaders as $key => $value) {
        $key = rawurldecode(urldecode($key));
        $value = rawurldecode(urldecode($value));
        if (!array_key_exists($key, $HeaderList)) {
          return $this->break(400, "verifyAuth:400009", "头部参数缺失");
        }
        $Headers[$key] = $value;
      }
    }

    $URLParams = [];
    foreach ($RawURLParams as $key => $value) {
      $key = rawurldecode(urldecode($key));
      $value = rawurldecode(urldecode($value));

      if (!$value) {
        $key = strtolower($key);
      }

      if (!in_array($key, $DiscuzXPluginParamKeys)) {
        if (!in_array($key, $URLParamList)) {
          if (!in_array($key, $URLParamKeys)) {
            return $this->break(400, "verifyAuth:400010", "URL 参数缺失");
          }
        }
      }

      if (!in_array($key, $URLParamKeys) && !in_array($key, $DiscuzXPluginParamKeys)) {
        $URLParams[$key] = $value;
      }
    }

    if ($this->signature->verifyAuthorization($Signature, $FileKey, $startTime, $endTime, $URLParams, $Headers, $HTTPMethod)) {
      return true;
    } else {
      return $this->break(403, "verifyAuth:403001", "抱歉，您没有操作该文件的权限");
    }
  }
  public function fileExist($fileKey)
  {
    // if (!$this->verifyOperationAuthorization($fileKey, "write")) return $this->forwardBreak();

    return $this->SDKClient->doesObjectExist($fileKey);
  }
  public function deleteFile($fileKey)
  {
    if (!$this->verifyOperationAuthorization($fileKey, "write")) return $this->forwardBreak();

    $result = $this->SDKClient->deleteObject($fileKey);
    if (!$result) return $this->SDKClient->return();

    if ($this->filesModel) {
      $this->filesModel->remove(true, $fileKey);
    }

    return $result;
  }
}
