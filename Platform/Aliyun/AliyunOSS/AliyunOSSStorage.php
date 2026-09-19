<?php

namespace kernel\Platform\Aliyun\AliyunOSS;

use AlibabaCloud\SDK\Sts\V20150401\Models\AssumeRoleRequest;
use AlibabaCloud\SDK\Sts\V20150401\Sts;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Darabonba\OpenApi\Models\Config;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileManager;
use kernel\Foundation\Storage\AbstractOSSStroage;
use kernel\Foundation\Storage\StorageFileInfoData;
use kernel\Service\StorageService;
use OSS\OssClient;
use OSS\Core\OssException;

class AliyunOSSStorage extends AbstractOSSStroage
{
  /**
   * 获取的角色ARN
   *
   * @var string
   */
  protected $roleArn = null;
  /**
   * 填写自定义权限策略，用于进一步限制STS临时访问凭证的权限。如果不指定Policy，则返回的STS临时访问凭证默认拥有指定角色的所有权限。  临时访问凭证最后获得的权限是步骤4设置的角色权限和该Policy设置权限的交集。
   *
   * @var array
   */
  protected $policy = null;

  /**
   * SDK实例
   *
   * @var OssClient
   */
  protected $SDKClient = null;

  public function __construct(
    $secretId,
    $secretKey,
    $region,
    $bucket,
    $roleArn = null,
    $policy = null,
    $SignatureKey = "ruyi_storage",
    $RoutePrefix = "files",
    $BaseURL = F_BASE_URL
  ) {
    $this->roleArn = $roleArn;
    $this->policy = $policy;

    parent::__construct($secretId, $secretKey, $region, $bucket, $SignatureKey, $RoutePrefix, $BaseURL, "oss");
  }

  protected function loadSDK()
  {
    $endPoint = "oss-{$this->region}.aliyuncs.com";
    $STSConfig = new Config([
      'accessKeyId' => $this->secretId,
      'accessKeySecret' => $this->secretKey,
      "endpoint" => "sts.{$this->region}.aliyuncs.com"
    ]);

    $OSSProvider = new AliyunOSSCredentialsProvider($this->secretId, $this->secretKey);
    $this->SDKClient = new OssClient([
      "provider" => $OSSProvider,
      "endpoint" => $endPoint
    ]);
    $this->stsClient = new Sts($STSConfig);

    return $this;
  }

  function uploadFile($file, $fileKey = null)
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
      "url" => $this->getFileSignURL($fileKey),
      "previewURL" => $this->getFileSignURL($fileKey),
      "downloadURL" => $this->getFileSignURL($fileKey),
      "transferPreviewURL" => $this->getFileTransferPreviewURL($fileKey),
      "transferDownloadURL" => $this->getFileTransferDownloadURL($fileKey),
      "accessControl" => $accessTag,
      "ownerId" => $ownerId
    ];

    try {
      $this->SDKClient->uploadFile($this->bucket, $fileKey, $TempFileInfo['filePath']);

      if (file_exists($TempFileInfo['filePath'])) {
        unlink($TempFileInfo['filePath']);
      }

      return new StorageFileInfoData($FileInfo);
    } catch (OssException $e) {
      throw new Exception($e->getMessage(), 500, 500, $e->getMessage());
    }
  }
  function deleteFile($fileKey)
  {
    $FileInfo = $this->getFile($fileKey);
    if (!$FileInfo) return $this->return();

    try {
      $DeletedResult = $this->SDKClient->deleteObject($this->bucket, $fileKey);
    } catch (OssException $e) {
      throw new Exception("服务器错误", 500, 500, $e);
    }

    if ($DeletedResult && $this->filesModel) {
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
          return $this->break(403, "getFile:403002", "抱歉，您无权获取该文件信息");
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
  function getFileAuth($FileKey = null, $Expires = 600,  $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return $this->getSTSToken($Expires);
  }
  function getFileSign($FileKey = null, $Expires = 600,  $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return $this->getSTSToken($Expires);
  }

  protected function getFileSignURL($ObjectKey, $Expires = 60, $Options = [])
  {
    return $this->SDKClient->signUrl($this->bucket, $ObjectKey, $Expires, $this->SDKClient::OSS_HTTP_GET, $Options);
  }

  function getFilePreviewURL($fileKey, $URLParams = [], $Expires = 60)
  {
    return $this->getFileSignURL($fileKey, $Expires);
  }
  function getFileDownloadURL($fileKey, $URLParams = [], $Expires = 60)
  {
    return $this->getFileSignURL($fileKey, $Expires);
  }
  function fileExist($fileKey)
  {
    if ($this->verifyRequestAuth($fileKey) === FALSE) {
      return $this->break(403, "getFile:403001", "抱歉，您无权获取该文件信息");
    }

    return $this->SDKClient->doesObjectExist($this->bucket, $fileKey);
  }

  /**
   * 获取 STS Token
   *
   * @param integer $durationSeconds 用于设置临时访问凭证有效时间单位为秒，最小值为900，最大值以当前角色设定的最大会话时间为准
   * @param string $roleSessionName 用于自定义角色会话名称，用来区分不同的令牌
   * @return array
   */
  function getSTSToken($durationSeconds = 3000, $roleSessionName = "oss_session")
  {
    $Config = [
      "roleArn" => $this->roleArn,
      "roleSessionName" => $roleSessionName,
      "durationSeconds" => $durationSeconds
    ];
    if ($this->policy) {
      $Config['policy'] = $this->policy;
    }
    $AssumeRoleRequest = new AssumeRoleRequest($Config);

    $Runtime = new RuntimeOptions([]);
    $Runtime->ignoreSSL = true;
    $Result = $this->stsClient->assumeRoleWithOptions($AssumeRoleRequest, $Runtime);

    return [
      "AccessKeyId" => $Result->body->credentials->accessKeyId,
      "AccessKeySecret" => $Result->body->credentials->accessKeySecret,
      "Expiration" => $Result->body->credentials->expiration,
      "SecurityToken" => $Result->body->credentials->securityToken,
      "Bucket" => $this->bucket,
      "Region" => $this->region
    ];
  }
}
