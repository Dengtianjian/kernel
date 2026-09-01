<?php

namespace kernel\Platform\Aliyun\AliyunOSS;

use AlibabaCloud\Dara\Models\RuntimeOptions;
use kernel\Foundation\FileSystem\FileSystem;

use kernel\Foundation\FileSystem\Storage\AbstractOSSStroage;
use OSS\OssClient;
use AlibabaCloud\Oss\V2 as Oss;
use AlibabaCloud\Oss\V2\Credentials\Credentials;
use AlibabaCloud\SDK\Sts\V20150401\Models\AssumeRoleRequest;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Path;
use OSS\Credentials\StaticCredentialsProvider;
use AlibabaCloud\SDK\Sts\V20150401\Sts;
use Darabonba\OpenApi\Models\Config;

/**
 * 阿里云 OSS 存储磁盘
 *
 * 继承抽象的 OSS 存储骨架（AbstractOSSStroage），实现阿里云对象存储 OSS 的
 * 上传 / 读取 / 删除 / 存在性判断 / 访问 URL 等能力。内部同时使用 OSS V2 SDK
 * （{@see Oss\Client}，主操作）与 V1 SDK（{@see OssClient}，兼容接口），
 * 并借助 STS（{@see Sts}）申请临时访问凭证（{@see getSTSToken()}）。
 *
 * @package kernel\Platform\Aliyun\AliyunOSS
 */
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
   * @var Oss\Client
   */
  protected $sdkClient = null;
  /**
   * v1 SDK 实例 
   *
   * @var OssClient
   */
  protected $sdkV1Client = null;
  /**
   * @var Sts|null STS 客户端（用于申请临时访问凭证）
   */
  protected $stsClient = null;

  /**
   * 构造阿里云 OSS 存储磁盘
   *
   * 设置磁盘名（oss）并记录 STS 角色 ARN 与自定义权限策略，再调用父类构造注入密钥/地域/桶。
   *
   * @param string $accessKeyId 阿里云 AccessKeyId
   * @param string $accessKeySecret 阿里云 AccessKeySecret
   * @param string $region OSS 所在地域
   * @param string $bucket 存储桶名称
   * @param string|null $roleArn STS 角色 ARN（申请临时凭证时使用），null 表示不启用 STS
   * @param array|null $policy 自定义权限策略，进一步收敛 STS 临时凭证权限；null 表示使用角色默认权限
   */
  public function __construct(
    $accessKeyId,
    $accessKeySecret,
    $region,
    $bucket,
    $roleArn = null,
    $policy = null
  ) {
    $this->roleArn = $roleArn;
    $this->policy = $policy;

    parent::__construct($accessKeyId, $accessKeySecret, $region, $bucket);

    $this->name = "oss";
  }

  /**
   * 磁盘引导初始化（由 AbilityBaseObject 生命周期触发）
   *
   * 初始化三套客户端：
   * - $sdkClient：阿里云 OSS V2 SDK 客户端（动态凭证提供者，使用固定密钥）；
   * - $sdkV1Client：OSS V1 SDK 客户端（StaticCredentialsProvider + V4 签名），用于兼容部分接口；
   * - $stsClient：STS 客户端（用于 getSTSToken 申请临时凭证）。
   * 其中固定密钥为开发/演示用途，生产环境推荐使用 STS 临时凭证。
   *
   * @return static
   */
  protected function boot()
  {
    $provider = new Oss\Credentials\CredentialsProviderFunc(function () {
      return new Credentials(
        accessKeyId: $this->secretId,
        accessKeySecret: $this->secretKey
      );
    });

    // 加载默认配置并获取 OSS 配置对象
    $cfg = Oss\Config::loadDefault();

    // 设置凭证提供者为动态生成的凭证提供者
    $cfg->setCredentialsProvider($provider);

    $cfg->setRegion($this->region());

    $this->sdkClient = new Oss\Client($cfg);

    //* v1 OSS 客户端实例化
    $accessKeyId = $this->secretId;
    $accessKeySecret = $this->secretKey;
    $provider = new StaticCredentialsProvider($accessKeyId, $accessKeySecret);
    $endpoint = "http://oss-{$this->region}.aliyuncs.com";
    $config = array(
      "provider" => $provider,
      "endpoint" => $endpoint,
      "signatureVersion" => OssClient::OSS_SIGNATURE_VERSION_V4,
      "region" => $this->region
    );
    $this->sdkV1Client = new OssClient($config);

    $STSConfig = new Config([
      'accessKeyId' => $this->secretId,
      'accessKeySecret' => $this->secretKey,
      "endpoint" => "sts.{$this->region}.aliyuncs.com"
    ]);

    $this->stsClient = new Sts($STSConfig);

    return $this;
  }

  /**
   * 获取文件元信息
   *
   * 先确认文件存在（不存在返回 404 错误态），再经 SDK headObject 获取对象元信息，
   * 并组装为统一的文件信息数组（含 name/disk/sourceFileName/path/extension/size/width/height/filePath）。
   * 注意：width/height 在此统一置为 null（OSS 不随 head 返回图片尺寸，需由调用方另行探测）。
   *
   * @param string $fileName 文件键
   * @return array|false 成功返回文件信息数组，失败返回 break 错误态
   */
  function get($fileName)
  {
    if (!$this->exists($fileName)) return $this->break(404, 404, "文件不存在");

    $fileInfo = null;
    try {
      $request = new Oss\Models\HeadObjectRequest($this->bucket(), $fileName);
      $fileInfo = $this->sdkClient->headObject($request);
    } catch (\Exception $e) {
      debug($e);
      throw new Error($e->getMessage(), 500, 500, $e->getMessage());
    }
    if (!$fileInfo) return $this->break(500, 500, "获取文件信息失败", "获取 COS 文件元信息失败");

    $pathInfo = pathinfo($fileName);
    $file = [
      "name" => $pathInfo['basename'],
      "disk" => 'oss',
      "sourceFileName" => $pathInfo['basename'],
      "path" => $pathInfo['dirname'],
      "extension" => $pathInfo['extension'] ?? '',
      "size" => (int)$fileInfo->contentLength,
      "width" => null,
      "height" => null,

      "filePath" => $fileName
    ];

    return $file;
  }
  /**
   * 上传文件到 OSS
   *
   * 流程：先以临时文件名落到本地 `cos_temp` 目录，校验临时文件落盘成功后探测图片尺寸，
   * 再经 V2 SDK 分片上传至 OSS；上传成功后复用 {@see get()} 取回元信息并回填宽高，最后返回。
   * 任意环节失败均清理临时文件并抛出异常 / 错误态。
   *
   * @param array $file 上传文件数组（同 PHP $_FILES 单文件结构）
   * @param string|null $saveFileName 目标文件键；为 null 时使用 $file['name']
   * @return array|false 成功返回含宽高的文件信息数组，失败返回 break 错误态
   */
  function put($file, $saveFileName = null)
  {
    $saveFileName = $saveFileName ?: $file['name'];
    $pathInfo =  pathinfo($saveFileName);
    $tempFileName = join("", [uniqid("temp_"), ".", $pathInfo['extension']]);
    $tempFileInfo = FileSystem::upload($file, "oss_temp", $tempFileName);
    if (!$tempFileInfo || !FileSystem::exists($tempFileInfo['filePath'])) {
      return $this->break(500, 500, "上传文件失败", "临时文件存储失败");
    }

    $width = 0;
    $height = 0;
    if (FileHelper::isImage($tempFileInfo['filePath'])) {
      $imageInfo = \getimagesize($tempFileInfo['filePath']);
      $width = $imageInfo[0];
      $height = $imageInfo[1];
    }

    $uploader = $this->sdkClient->newUploader();

    try {
      $uploader->uploadFile(
        request: new Oss\Models\PutObjectRequest($this->bucket(), key: $saveFileName), // 创建PutObjectRequest对象，指定Bucket和对象名称
        filepath: $tempFileInfo['filePath'], // 指定要上传的本地文件路径
        args: [ // 可选参数，用于自定义分片上传行为
          'part_size' => 1024 * 1024, // 自定义分片大小
          'parallel_num' => 1, // 并发上传的分片数量
        ]
      );
      if (FileSystem::exists($tempFileInfo['filePath'])) {
        FileSystem::deleteFile($tempFileInfo['filePath']);
      }

      $fileInfo = $this->get($saveFileName);
      if (!$fileInfo) return $this->break(500, 500, "获取上传的文件信息失败");
      $fileInfo['width'] = $width;
      $fileInfo['height'] = $height;

      return $fileInfo;
    } catch (\Exception $e) {
      if (FileSystem::exists($tempFileInfo['filePath'])) {
        FileSystem::deleteFile($tempFileInfo['filePath']);
      }
      throw new Error($e->getMessage(), 500, 500, $e->getMessage());
    }
  }
  /**
   * 删除 OSS 上的文件
   *
   * 调用 SDK 删除对象。注意：本方法不校验 SDK 删除是否真正成功，无论结果一律返回 true，
   * 调用方无法据此判断文件是否已被删除；SDK 调用本身抛异常时会上抛 Error。
   *
   * @param string $fileName 文件键
   * @return boolean 恒为 true（不反映真实删除结果）
   * @throws Error SDK 调用异常时抛出
   */
  function delete($fileName)
  {
    try {
      $request = new Oss\Models\DeleteObjectRequest($this->bucket(), $fileName);
      $this->sdkClient->deleteObject($request);
    } catch (\Exception $e) {
      throw new Error($e->getMessage(), 500, 500, $e->getMessage());
    }

    return true;
  }
  /**
   * 判断 OSS 上文件是否存在
   *
   * 底层调用 SDK 的 isObjectExist。注意：本方法在对象不存在时返回 false，
   * 但 SDK 调用本身抛异常（如网络/鉴权错误）时会上抛 Error，不会静默返回 false。
   *
   * @param string $fileName 文件键
   * @return boolean 存在返回 true，不存在返回 false
   * @throws Error SDK 调用异常时抛出
   */
  function exists($fileName)
  {
    try {
      return $this->sdkClient->isObjectExist($this->bucket(), $fileName);
    } catch (\Exception $e) {
      throw new Error($e->getMessage(), 500, 500, $e->getMessage());
    }
  }
  /**
   * 获取文件访问 URL（预签名）
   *
   * 通过 SDK presign 生成预签名访问 URL。注意：当前实现**忽略了** $urlParams / $expires / $withSignature 三参数，
   * 仅生成默认预签名 URL（pre-signed URL 本身即带时效签名），不对这三者做定制。
   * 若需区分"带/不带签名"或自定义有效期，需后续改造本方法。
   *
   * @param string $fileName 文件键
   * @param array $urlParams 预留参数（当前未参与计算）
   * @param int $expires 预留参数（当前未参与计算）
   * @param boolean $withSignature 预留参数（当前未参与计算）
   * @return string 预签名访问 URL
   */
  function url($fileName, $urlParams = [], $expires = 1800, $withSignature = true)
  {
    $request = new Oss\Models\GetObjectRequest(bucket: $this->bucket, key: $fileName);
    return $this->sdkClient->presign($request)->url;
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
    $config = [
      "roleArn" => $this->roleArn,
      "roleSessionName" => $roleSessionName,
      "durationSeconds" => $durationSeconds
    ];
    if ($this->policy) {
      $config['policy'] = $this->policy;
    }
    $assumeRoleRequest = new AssumeRoleRequest($config);

    $runtime = new RuntimeOptions([]);
    $runtime->ignoreSSL = true;
    $result = $this->stsClient->assumeRoleWithOptions($assumeRoleRequest, $runtime);

    return [
      "AccessKeyId" => $result->body->credentials->accessKeyId,
      "AccessKeySecret" => $result->body->credentials->accessKeySecret,
      "Expiration" => $result->body->credentials->expiration,
      "SecurityToken" => $result->body->credentials->securityToken,
      "Bucket" => $this->bucket,
      "Region" => $this->region
    ];
  }
}
