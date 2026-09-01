<?php

namespace kernel\Platform\QCloud\QCloudCos;

use kernel\Foundation\FileSystem\FileSystem;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Storage\AbstractOSSStroage;
use kernel\Platform\QCloud\QCloudSTS;
use Qcloud\Cos\Client as QCloudCOSClient;

/**
 * 腾讯云 COS 存储磁盘
 *
 * 继承抽象的 OSS 存储骨架（AbstractOSSStroage），实现腾讯云对象存储 COS 的
 * 上传 / 读取 / 删除 / 存在性判断 / 访问 URL 等能力。内部使用官方 SDK
 * {@see QCloudCOSClient} 操作对象，使用 {@see QCloudSTS} 申请临时凭证（UploadManager 等前端直传场景）。
 *
 * 本磁盘同时承担「文件访问签名签发」职责：{@see createAuthorization()} 借助
 * {@see QCloudCosSignture} 生成 COS 风格的签名授权参数，供 FileStorage 统一鉴权流程调用。
 *
 * @package kernel\Platform\QCloud\QCloudCos
 */
class QCloudCOSStorage extends AbstractOSSStroage
{
  /**
   * @var QCloudSTS|null STS 客户端（用于申请临时上传凭证）
   */
  protected $stsClient = null;
  /**
   * @var QCloudCOSClient|null COS 官方 SDK 客户端
   */
  protected $sdkClient = null;
  /**
   * @var string|null 访问域名（签名签发时使用）
   */
  protected $host = null;
  /**
   * 磁盘引导初始化（由 AbilityBaseObject 生命周期触发）
   *
   * 设置磁盘名（cos）、固定密钥，并初始化 STS 客户端与 COS SDK 客户端。
   * 其中 SDK 客户端以明文固定密钥构造（开发/演示用途），生产环境应改用 STS 临时凭证。
   *
   * @return static
   */
  protected function boot()
  {
    $this->name = "cos";

    $this->stsClient = new QCloudSTS($this->secretId, $this->secretKey, $this->region, $this->bucket);

    $this->sdkClient = new QCloudCOSClient([
      'region' => $this->region(),
      'scheme' => 'http',
      'credentials' => [
        'secretId'  => $this->secretId,
        'secretKey' => $this->secretKey
      ]
    ]);

    return $this;
  }
  /**
   * 获取文件信息
   *
   * 读取元信息，并补充 `disk = "cos"` 字段。
   *
   * @param string $fileName 文件名称（相对 storage 根目录的路径）
   * @return false|array{name:string,disk:string,sourceFileName:string,path:string,extension:string,size:int,width:int|null,height:int|null,filePath:string} 文件信息数组，文件不存在时返回 false
   */
  function get($fileName)
  {
    if (!$this->exists($fileName)) return $this->break(404, 404, "文件不存在");

    $fileInfo = null;
    try {
      $fileInfo = $this->sdkClient->headObject(array(
        'Bucket' => $this->bucket(),
        'Key' => $fileName,
      ));
    } catch (\Exception $e) {
      throw new Error($e->getMessage(), 500, 500, $e->getMessage());
    }
    if (!$fileInfo) return $this->break(500, 500, "获取文件信息失败", "获取 COS 文件元信息失败");

    $pathInfo = pathinfo($fileName);
    $file = [
      "name" => $pathInfo['basename'],
      "disk" => 'cos',
      "sourceFileName" => $pathInfo['basename'],
      "path" => $pathInfo['dirname'],
      "extension" => $pathInfo['extension'] ?? '',
      "size" => (int)$fileInfo['ContentLength'],
      "width" => null,
      "height" => null,

      "filePath" => $fileName
    ];

    return $file;
  }
  /**
   * 上传文件到 COS
   *
   * 流程：先以临时文件名落到本地 `cos_temp` 目录，校验临时文件落盘成功后，
   * 探测图片尺寸（若有），再经 SDK 上传至 COS；上传成功后清理临时文件并回填图片宽高，
   * 最后复用 {@see get()} 取回完整元信息。任意环节失败均清理临时文件并抛出异常 / 错误态。
   *
   * @param array $file 上传文件数组（同 PHP $_FILES 单文件结构）
   * @param string|null $saveFileName 目标文件键；为 null 时使用 $file['name']
   * @return array|false 成功返回文件信息数组（含 width/height），失败返回 break 错误态
   */
  function put($file, $saveFileName = null)
  {
    $saveFileName = $saveFileName ?: $file['name'];
    $pathInfo =  pathinfo($saveFileName);
    $tempFileName = join("", [uniqid("temp_"), ".", $pathInfo['extension']]);
    $tempFileInfo = FileSystem::upload($file, "cos_temp", $tempFileName);
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

    try {
      $this->sdkClient->upload(
        $this->bucket(),
        $saveFileName,
        fopen($tempFileInfo['filePath'], 'rb')
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
   * 删除 COS 上的文件
   *
   * 调用 SDK 删除对象
   * SDK 调用异常会向上抛出。
   *
   * @param string $fileKey 文件键
   * @return boolean|mixed 无数据模型时返回 true；有数据模型时返回模型的删除结果
   */
  function delete($fileKey)
  {
    try {
      $this->sdkClient->deleteObject([
        'Bucket' => $this->bucket(),
        'Key' => $fileKey
      ]);
    } catch (\Exception $e) {
      throw new Error($e->getMessage(), 500, 500, $e->getMessage());
    }
    return TRUE;
  }
  /**
   * 判断 COS 上文件是否存在
   *
   * 底层调用 SDK 的 doesObjectExist。注意：本方法在对象不存在时返回 false，
   * 但 SDK 调用本身抛异常（如网络/鉴权错误）时会上抛 Error，不会静默返回 false。
   *
   * @param string $fileName 文件键
   * @return boolean 存在返回 true，不存在返回 false
   * @throws Error SDK 调用异常时抛出
   */
  function exists($fileName)
  {
    try {
      return $this->sdkClient->doesObjectExist(
        $this->bucket(),
        $fileName
      );
    } catch (\Exception $e) {
      throw new Error($e->getMessage(), 500, 500, $e->getMessage());
    }
  }

  /**
   * 获取 COS 文件的访问 URL
   *
   * 根据 $withSignature 决定生成带时效签名的 URL 或公开无签名 URL：
   * - 带签名（默认）：调用 SDK `getObjectUrl`，生成的 URL 包含签名且 $expires 秒内有效；
   * - 无签名：调用 SDK `getObjectUrlWithoutSign`，生成可直接访问的公开 URL（依赖桶的公开读策略）。
   * 生成过程中 SDK 抛异常时返回 null（不向上抛），调用方需对 null 做判空处理。
   *
   * @param string $fileName 文件键（内部会 trim 去掉首尾空白）
   * @param array $urlParams 附加的 URL 参数（当前实现未参与计算，保留以对齐父类签名）
   * @param int $expires 带签名时的有效时长（秒），默认 1800
   * @param boolean $withSignature 是否生成带时效签名的 URL，默认 true
   * @return string|null 成功返回访问 URL，失败返回 null
   */
  function url($fileName, $urlParams = [], $expires = 1800, $withSignature = true)
  {
    try {
      if ($withSignature) {
        return $this->sdkClient->getObjectUrl($this->bucket(), trim($fileName), $expires);
      } else {
        return $this->sdkClient->getObjectUrlWithoutSign($this->bucket(), trim($fileName));
      }
    } catch (\Exception $e) {
      return null;
    }
  }

  /**
   * 申请 COS 临时上传凭证（STS）
   *
   * 委托 {@see QCloudSTS} 获取临时密钥，供前端/客户端直传 COS 使用。
   * 失败时解析 STS 返回的错误结构，归一化为本框架的 break 错误态。
   *
   * @param string|null $allowPrefix 允许操作的对象前缀（路径白名单），null 时使用默认
   * @param array|null $allowActions 允许的操作动作列表，null 时使用默认
   * @param int $durationSeconds 凭证有效期（秒），默认 1800
   * @return array|false 成功返回临时凭证数组，失败返回 break 错误态
   */
  public function getTempKeys($allowPrefix = null, $allowActions = null, $durationSeconds = 1800)
  {
    try {
      return $this->STSClient->getTempKeys($allowPrefix, $allowActions, intval($durationSeconds));
    } catch (\Exception $e) {
      $rawMessage = $e->getMessage();
      $response = json_decode($rawMessage, true);
      $code = "500";
      $message = $rawMessage;
      if ($response && $response['Error']) {
        $code = "500:" . $response['Error']['Code'];
        $message = $response['Error']['Message'];
      }

      return $this->break(500, $code, $message);
    }
  }
  /**
   * 生成 COS 文件访问签名授权参数
   *
   * 借助 {@see QCloudCosSignture} 生成 COS 风格的签名（含 sign-algorithm / sign-time /
   * key-time / header-list / signature / url-param-list 等），供 FileStorage 统一鉴权流程调用。
   * 文件键若不以 "/" 开头会自动补前缀。
   *
   * @param string|null $fileKey 文件键，null 时表示仅生成通用签名
   * @param int $expires 签名有效期（秒），默认 1800
   * @param string $httpMethod 参与的 HTTP 方法，默认 get
   * @param array $urlParams 参与签名的 URL 参数
   * @param array $headers 参与签名的请求头
   * @return array 签名授权参数字典
   */
  public function createAuthorization($fileKey = null, $expires = 1800, $httpMethod = "get", $urlParams = [], $headers = [])
  {
    $QCCS = new QCloudCosSignture($this->secretId, $this->secretKey, $this->region, $this->bucket, $this->host, $this->SecurityToken);

    if (strpos($fileKey, "/") !== 0) {
      $fileKey = "/" . $fileKey;
    }

    return $QCCS->createAuthorization($fileKey, $urlParams, $headers, $expires, $httpMethod);
  }
  /**
   * 转换 URL 的 query 参数
   * 因为每个平台对文件的处理参数都不一样，所以就诞生了该方法，把统一的文件处理参数转换为指定平台的处理参数
   * 例如腾讯云 COS 的图片缩放是 `imageMogr2/thumbnail/!40p`，而文件链接的是传 `s=40`
   * 就需要使用该方法把 `s=40` 转换为 `imageMogr2/thumbnail/!40p`，再去生成链接
   *
   * @param array $urlParams URL 参数
   * @param string $targetName 目标平台
   * @return array
   */
  function convertURLParams($urlParams, $targetName)
  {
    if ($targetName === "cos") {
      $keys = [];
      $imageMogr2Keys = [];
      if (array_key_exists("r", $urlParams)) {
        $imageMogr2Keys[] = 'thumbnail/!' . $urlParams['r'] . 'p/ignore-error/1';
        unset($urlParams['r']);
      }
      if (array_key_exists("q", $urlParams)) {
        $imageMogr2Keys[] = 'quality/' . $urlParams['q'] . "/minsize/1/ignore-error/1";
        unset($urlParams['q']);
      }
      if (array_key_exists("ext", $urlParams)) {
        $imageMogr2Keys[] = 'format/' . $urlParams['ext'] . "/minsize/1/ignore-error/1";
        unset($urlParams['ext']);
      }
      if (array_key_exists("rotate", $urlParams)) {
        $imageMogr2Keys[] = 'rotate/' . $urlParams['rotate'] . "/ignore-error/1";
        unset($urlParams['ext']);
      }
      if ($imageMogr2Keys) {
        $keys[] = "imageMogr2/" . join("/", $imageMogr2Keys);
        $urlParams[] = join("/", $keys);
      }
    }

    return $urlParams;
  }
}
