<?php

namespace kernel\Platform\QCloud\QCloudCos;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileManager;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\Storage\AbstractOSSStroage;
use kernel\Foundation\Storage\StorageFileInfoData;
use kernel\Platform\QCloud\QCloudSTS;
use kernel\Service\StorageService;
use Qcloud\Cos\Client as QCloudCOSClient;

class QCloudCOSStorage extends AbstractOSSStroage
{
  /**
   * SDK实例
   *
   * @var QCloudCOSClient
   */
  protected $SDKClient = null;
  /**
   * OSS安全实例
   *
   * @var QCloudSTS
   */
  protected $STSClient = null;
  protected $host = null;

  /**
   * 实例化抽象 COS 存储
   *
   * @param string $secretId 密钥 ID
   * @param string $secretKey 密钥
   * @param string $region 存储桶所在的地区
   * @param string $bucket 存储桶名称
   * @param string $SignatureKey 生成签名的密钥，框架用于生成链接、上传授权等签名的密钥值
   * @param string $RoutePrefix 路由前缀，默认 files
   * @param string $BaseURL 基础URL 地址
   */
  public function __construct(
    $secretId,
    $secretKey,
    $region,
    $bucket,
    $SignatureKey = "ruyi_storage",
    $RoutePrefix = "files",
    $BaseURL = F_BASE_URL
  ) {
    $this->host = "{$bucket}.cos.{$region}.myqcloud.com";

    parent::__construct($secretId, $secretKey, $region, $bucket, $SignatureKey, $RoutePrefix, $BaseURL, "cos");
  }
  protected function loadSDK()
  {
    $this->STSClient = new QCloudSTS($this->secretId, $this->secretKey, $this->region, $this->bucket);

    $this->SDKClient = new QCloudCOSClient([
      'region' => $this->region(),
      'scheme' => 'http',
      'credentials' => [
        'secretId'  => $this->secretId,
        'secretKey' => $this->secretKey
      ]
    ]);

    return $this;
  }
  function uploadFile($file, $fileKey = null, $options = [])
  {
    if (!$this->verifyRequestAuth($fileKey)) {
      return $this->return();
    }

    $accessTag = self::AUTHENTICATED_READ;
    $ownerId = $this->getACAuthId();

    if ($this->filesModel) {
      $FileData = $this->filesModel->item($fileKey);
      if (!$FileData) {
        return $this->break(404, 404, "文件不存在");
      }
      $accessTag = $FileData['accessControl'];
      $ownerId = $FileData['ownerId'];
    }

    if ($this->accessAuthozationVerification($fileKey, $accessTag, $ownerId, "write") === FALSE) {
      return $this->break(403, "uploadFile:403002", "抱歉，您没有上传该文件的权限");
    }

    $FileKeyPathInfo = pathinfo($fileKey);
    $TempFileInfo = FileManager::upload($file, "RemoteTemp", $FileKeyPathInfo['basename']);

    $FileInfo = [
      "key" => $fileKey,
      "sourceFileName" => $TempFileInfo['sourceFileName'],
      "path" => $FileKeyPathInfo['dirname'],
      "filePath" => $TempFileInfo['dirname'],
      "name" => $FileKeyPathInfo['basename'],
      "extension" => $FileKeyPathInfo['extension'],
      "size" => $TempFileInfo['size'],
      "width" => $TempFileInfo['width'],
      "height" => $TempFileInfo['height'],
      "remote" => true,
      "url" => $this->getFilePreviewURL($fileKey),
      "previewURL" => $this->getFilePreviewURL($fileKey),
      "downloadURL" => $this->getFileDownloadURL($fileKey),
      "transferPreviewURL" => $this->getFileTransferPreviewURL($fileKey),
      "transferDownloadURL" => $this->getFileTransferDownloadURL($fileKey),
      "accessControl" => $accessTag,
      "ownerId" => $ownerId
    ];

    try {
      $this->SDKClient->upload(
        $this->bucket(),
        $fileKey,
        fopen($TempFileInfo['filePath'], 'rb')
      );
      if (file_exists($TempFileInfo['filePath'])) {
        unlink($TempFileInfo['filePath']);
      }

      if ($this->filesModel) {
        $this->filesModel->save([
          "platform" => $this->platform,
          "remote" => 1,
          "size" => $TempFileInfo['size'],
          "width" => $TempFileInfo['width'],
          "height" => $TempFileInfo['height']
        ], $fileKey);
      }

      return new StorageFileInfoData($FileInfo);
    } catch (\Exception $e) {
      throw new Exception($e->getMessage(), 500, 500, $e->getMessage());
    }
  }
  function deleteFile($fileKey)
  {
    if (!$this->verifyOperationAuthorization($fileKey, "write")) return $this->forwardBreak();

    try {
      $this->SDKClient->deleteObject([
        'Bucket' => $this->bucket(),
        'Key' => $fileKey
      ]);
    } catch (\Exception $e) {
      throw new Exception($e->getMessage(), 500, 500, $e->getMessage());
    }

    if ($this->filesModel) {
      return $this->filesModel->remove(true, $fileKey);
    }

    return TRUE;
  }
  function getFile($fileKey)
  {
    $fileInfo = null;
    if ($this->filesModel) {
      $fileInfo = $this->filesModel->item($fileKey);
      if ($this->getACAuthId() != $fileInfo['ownerId']) {
        // if ($this->verifyRequestAuth($fileKey) === FALSE) {
        //   return $this->break(403, "getFile:403003", "抱歉，您无权获取该文件信息");
        // }
        if ($this->accessAuthozationVerification($fileKey, $fileInfo['accessControl'], $fileInfo['ownerId']) === FALSE) {
          return $this->break(403, "getFile:403002", "抱歉，您无权获取该文件信息", [
            "statusCode" => $this->errorStatusCode,
            "code" => $this->errorCode,
            "message" => $this->errorMessage,
          ]);
        }
      }
    } else {
      if ($this->verifyRequestAuth($fileKey) === FALSE) {
        return $this->break(403, "getFile:403001", "抱歉，您无权获取该文件信息");
      }

      $fileInfo = FileManager::getFileInfo($this->getFilePreviewURL($fileKey));
      $fileInfo['remote'] = false;
    }
    if (!$fileInfo) {
      return $this->break(404, "getFile:404", "文件不存在");
    };

    $fileInfo['key'] = $fileKey;

    $fileInfo['url'] = StorageService::getPlatform($fileInfo['platform'])->getFilePreviewURL($fileKey);
    $fileInfo['previewURL'] =  StorageService::getPlatform($fileInfo['platform'])->getFilePreviewURL($fileKey);
    $fileInfo['downloadURL'] = StorageService::getPlatform($fileInfo['platform'])->getFileDownloadURL($fileKey);
    $fileInfo['transferPreviewURL'] = $this->getFileTransferPreviewURL($fileKey);
    $fileInfo['transferDownloadURL'] = $this->getFileTransferDownloadURL($fileKey);

    return new StorageFileInfoData($fileInfo);
  }
  function getFileAuth($AllowPrefix = null, $AllowActions = null, $DurationSeconds = 1800)
  {
    $QCSTS = new QCloudSTS($this->secretId, $this->secretKey, $this->region, $this->bucket);

    return $QCSTS->getTempKeys($AllowPrefix, $AllowActions, intval($DurationSeconds));
  }
  function getFileSign($fileKey = null, $Expires = 1800, $HTTPMethod = "get", $URLParams = [], $Headers = [])
  {
    $QCCS = new QCloudCosSignture($this->secretId, $this->secretKey, $this->region, $this->bucket, $this->host, $this->SecurityToken);

    if (strpos($fileKey, "/") !== 0) {
      $fileKey = "/" . $fileKey;
    }

    return $QCCS->createAuthorization($fileKey, $URLParams, $Headers, $Expires, $HTTPMethod);
  }
  /**
   * 获取带有授权参数的对象访问URL
   *
   * @param string $fileKey 对象名称，/开头
   * @param string $HTTPMethod 调用的服务所使用的请求方法
   * @param array $URLParams  请求的URL参数
   * @param array $Headers  请求头部
   * @param int $Expires 签名有效期，多少秒
   * @param boolean $Download 链接打开是下载文件
   * @return string https协议的对象访问URL
   */
  function getObjectAuthUrl($fileKey, $HTTPMethod = "get", $URLParams = [], $Headers = [], $Expires = 1800, $Download = false)
  {
    $fileKey = trim($fileKey);

    if ($Download) {
      $URLParams['response-content-disposition'] = 'attachment';
    }

    $Authorization = $this->getFileSign($fileKey, $Expires, $HTTPMethod, $URLParams, $Headers);

    if (strpos($fileKey, "/") !== 0) {
      $fileKey = "/" . $fileKey;
    }

    return "https://{$this->host}{$fileKey}?" . URL::buildQuery($Authorization, false);
  }

  function getFilePreviewURL($fileKey, $URLParams = [], $Expires = 1800)
  {
    return $this->getObjectAuthUrl($fileKey, "get", $URLParams, [], $Expires, false);
  }
  function getFileDownloadURL($fileKey, $URLParams = [], $Expires = 1800)
  {
    return $this->getObjectAuthUrl($fileKey, "get", $URLParams, [], $Expires, true);
  }
  function fileExist($fileKey)
  {
    if (!$this->verifyOperationAuthorization($fileKey, "read")) return $this->forwardBreak();

    try {
      return $this->SDKClient->doesObjectExist(
        $this->bucket(),
        $fileKey
      );
    } catch (\Exception $e) {
      throw new Exception($e->getMessage(), 500, 500, $e->getMessage());
    }
  }
}
