<?php

namespace kernel\Service\OSS;

use AlibabaCloud\SDK\Sts\V20150401\Models\AssumeRoleRequest;
use OSS\OssClient;
use AlibabaCloud\SDK\Sts\V20150401\Sts;
use kernel\Foundation\Exception\Exception as ExceptionException;
use kernel\Platform\Aliyun\AliyunOSS\AliyunOSS;
use kernel\Platform\Aliyun\AliyunOSS\AliyunOSSCredentialsProvider;
use OSS\Core\OssException;
use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;

class OSSAliyunService extends AbstractOSSService
{
  /**
   * OSS客户端实例
   *
   * @var AliyunOSS
   */
  protected $OSSClient = null;

  /**
   * Oss STS 客户端实例
   *
   * @var Sts
   */
  protected $OSSSTSClient = null;
  /**
   * 阿里云OSS SDK客户端实例
   *
   * @var OssClient
   */
  protected $OSSSDKClient = null;

  public function __construct($SecretId, $SecretKey, $Region, $Bucket)
  {
    $endPoint = "oss-cn-{$Region}.aliyuncs.com";
    $STSConfig = new Config([
      'accessKeyId' => $SecretId,
      'accessKeySecret' => $SecretKey,
      "endpoint" => $endPoint
    ]);

    $this->OSSSTSClient = new Sts($STSConfig);

    $OSSProvider = new AliyunOSSCredentialsProvider($SecretId, $SecretKey);
    $endPoint = "http://{$endPoint}";
    $this->OSSSDKClient = new OssClient([
      "provider" => $OSSProvider,
      "endpoint" => $endPoint
    ]);
    $this->OSSClient = new AliyunOSS($SecretId, $SecretKey, $Region, $Bucket);

    parent::__construct("AliyunOSS", $SecretId, $SecretKey, $Region, $Bucket);
  }
  public function upload($ObjectKey, $FilePath = "files", $Options = [])
  {
    try {
      return $this->OSSSDKClient->uploadFile($this->OSSBucketName, $ObjectKey, $FilePath, $Options);
    } catch (OssException $e) {
      debug($e->getErrorMessage());
      throw new ExceptionException("服务器错误", 500, 500, $e);
    }
  }
  public function deleteFile($ObjectKey)
  {
    try {
      return $this->OSSSDKClient->deleteObject($this->OSSBucketName, $ObjectKey);
    } catch (OssException $e) {
      throw new ExceptionException("服务器错误", 500, 500, $e);
    }
  }
  /**
   * 获取文件浏览地址
   *
   * @param string $ObjectKey 文件名
   * @param integer $Expires 有效期
   * @param array $Options 选项
   * @return string
   */
  public function getFilePreviewURL($ObjectKey, $Expires = 60, $Options = [])
  {
    return $this->OSSSDKClient->signUrl($this->OSSBucketName, $ObjectKey, $Expires, $this->OSSSDKClient::OSS_HTTP_GET, $Options);
  }
  /**
   * 获取文件下载地址
   *
   * @param string $ObjectKey 文件名
   * @param integer $Expires 有效期
   * @param array $Options 选项
   * @return string
   */
  public function getFileDownloadURL($ObjectKey, $Expires = 60, $Options = [])
  {
    return $this->OSSSDKClient->signUrl($this->OSSBucketName, $ObjectKey, $Expires, $this->OSSSDKClient::OSS_HTTP_GET, $Options);
  }
  public function getFileAuth(
    $ObjectKey
  ) {}
  public function fileExist($ObjectKey)
  {
    return $this->OSSSDKClient->doesObjectExist($this->OSSBucketName, $ObjectKey);
  }

  /**
   * 获取 STS Token
   *
   * @param integer $durationSeconds 用于设置临时访问凭证有效时间单位为秒，最小值为900，最大值以当前角色设定的最大会话时间为准
   * @param string $roleArn 获取的角色ARN
   * @param string $roleSessionName 用于自定义角色会话名称，用来区分不同的令牌
   * @param array $policy 填写自定义权限策略，用于进一步限制STS临时访问凭证的权限。如果不指定Policy，则返回的STS临时访问凭证默认拥有指定角色的所有权限。  临时访问凭证最后获得的权限是步骤4设置的角色权限和该Policy设置权限的交集。
   * @return array
   */
  public function getSTSToken($durationSeconds = 3000, $roleArn = null, $roleSessionName = "oss_session", $policy = NULL)
  {
    $Config = [
      "roleArn" => $roleArn,
      "roleSessionName" => $roleSessionName,
      "durationSeconds" => $durationSeconds
    ];
    if ($policy) {
      $Config['policy'] = $policy;
    }
    $AssumeRoleRequest = new AssumeRoleRequest($Config);

    $Runtime = new RuntimeOptions([]);
    $Runtime->ignoreSSL = true;
    $Result = $this->OSSSTSClient->assumeRoleWithOptions($AssumeRoleRequest, $Runtime);

    return [
      "AccessKeyId" => $Result->body->credentials->accessKeyId,
      "AccessKeySecret" => $Result->body->credentials->accessKeySecret,
      "Expiration" => $Result->body->credentials->expiration,
      "SecurityToken" => $Result->body->credentials->securityToken
    ];
  }
}
