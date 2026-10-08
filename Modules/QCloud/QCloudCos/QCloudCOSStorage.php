<?php

namespace kernel\Modules\QCloud\QCloudCos;

use kernel\Foundation\FileSystem\FileSystem;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\FileSystem\Storage\Drivers\AbstractObjectStorage;
use kernel\Foundation\FileSystem\Storage\StorageFile;
use kernel\Modules\QCloud\STS\QCloudSTS;

/**
 * 腾讯云 COS 存储磁盘
 *
 * 继承抽象的对象存储骨架（AbstractObjectStorage），实现腾讯云对象存储 COS 的
 * 上传 / 读取 / 删除 / 存在性判断 / 访问 URL 等能力。对象操作统一走 {@see QCloudCosClient}
 * （它内部再决定**用官方 SDK 还是自己发签名请求**，以适配不同环境），
 * 并用 {@see QCloudSTS} 申请临时凭证（前端直传场景）。
 *
 * 本磁盘同时承担「文件访问签名签发」职责：{@see createAuthorization()} 借助
 * {@see QCloudCosSignture} 生成 COS 风格的签名授权参数，供 FileStorage 统一鉴权流程调用。
 *
 * @package kernel\Modules\QCloud\QCloudCos
 */
class QCloudCOSStorage extends AbstractObjectStorage
{
  /**
   * @var QCloudSTS|null STS 客户端（用于申请临时上传凭证）
   */
  protected $stsClient = null;
  /**
   * @var QCloudCosClient|null COS 客户端（内部封装 SDK / 裸请求两种驱动）
   */
  protected $client = null;
  /**
   * @var string|null 访问域名（签名签发时使用）
   */
  protected $host = null;
  /**
   * 磁盘引导初始化（由 AbilityBaseObject 生命周期触发）
   *
   * 设置磁盘名（cos）、固定密钥，并初始化 STS 客户端与 COS 客户端。
   * 其中 COS 客户端以明文固定密钥构造（开发/演示用途），生产环境应改用 STS 临时凭证。
   *
   * @return static
   */
  protected function boot()
  {
    $this->name = "cos";

    $this->stsClient = new QCloudSTS($this->secretId, $this->secretKey, $this->region, $this->bucket);

    //* 参数顺序：secretId, secretKey, region, bucket（与 QCloudCosSignture 保持一致）；
    //* 驱动（官方 SDK / 裸签名请求）由客户端自己检测，这里不需要关心。
    $this->client = new QCloudCosClient($this->secretId, $this->secretKey, $this->region, $this->bucket, null, QCloudCosClient::DRIVER_HTTP);

    return $this;
  }

  /**
   * 读取或设置存储服务客户端实例
   *
   * 读写一体：传参时设置客户端并返回 `$this`（链式）；不传时返回当前客户端
   * （未设置时为 null）。客户端由子类在自己的 {@see boot()} 里创建。
   *
   * ```php
   * $storage->client($myClient);    // 注入
   * $client = $storage->client();   // 读取
   * ```
   *
   * ⚠️ 读分支必须访问**属性** `$this->client`；若写成 `$this->client()`，PHP 会把它当成
   * **递归调用本方法**（本类同时有同名属性 `$client` 与同名方法 `client()`），从而无限递归。
   *
   * 💡 现状提示：子类 `QCloudCOSStorage` 目前用的是它**自己的** `$cosClient` 属性，
   * 并未使用本属性/本方法；如需统一客户端入口，可改为走这里。
   *
   * @param mixed|null $client 客户端实例；不传（`func_num_args()` 为 0）则读取
   * @return QCloudCosClient|QCloudCOSStorage|null 设置时返回 `$this`；读取时返回当前客户端
   */
  public function client($client = null)
  {
    return parent::client($client);
  }
  /**
   * 获取文件信息
   *
   * 读取元信息，并补充 `disk = "cos"` 字段。
   *
   * @param string $fileName 文件名称（相对 storage 根目录的路径）
   * @return false|StorageFile 文件信息对象，文件不存在时返回 false
   */
  function get($fileName)
  {
    if (!$this->exists($fileName)) return $this->break(404, 404, "文件不存在");

    //* HEAD Object：成功返回响应头数组，失败（或不存在）返回 false
    $headers = $this->client->metadata($fileName);
    if (!$headers) {
      return $this->break(500, 500, "获取文件信息失败", $this->client->lastError() ?: "获取 COS 文件元信息失败");
    }

    //* 把响应头回填成对象元信息（大小取 Content-Length，头名大小写不敏感）
    $object = $this->client->object($fileName)->fill($headers);

    $pathInfo = pathinfo($fileName);
    $file = [
      "name" => $pathInfo['basename'],
      "disk" => 'cos',
      "sourceFileName" => $pathInfo['basename'],
      "path" => $pathInfo['dirname'],
      "extension" => $pathInfo['extension'] ?? '',
      "size" => (int) $object->size(),
      "width" => null,
      "height" => null,

      "filePath" => $fileName
    ];

    return new StorageFile($file);
  }
  /**
   * 上传文件到 COS
   *
   * 流程：先以临时文件名落到本地 `cos_temp` 目录，校验临时文件落盘成功后，
   * 探测图片尺寸（若有），再经 COS 客户端上传（按文件大小自动选简单/分块）；
   * 上传成功后清理临时文件并回填图片宽高，
   * 最后复用 {@see get()} 取回完整元信息。任意环节失败均清理临时文件并抛出异常 / 错误态。
   *
   * @param array $file 上传文件数组（同 PHP $_FILES 单文件结构）
   * @param string|null $saveFileName 目标文件键；为 null 时使用 $file['name']
   * @return StorageFile|false 成功返回文件信息对象（含 width/height），失败返回 break 错误态
   */
  function put($file, $saveFileName = null)
  {
    $saveFileName = $saveFileName ?: $file['name'];
    $pathInfo =  pathinfo($saveFileName);
    $tempFileName = join("", [uniqid("temp_"), ".", $pathInfo['extension']]);
    $tempFileInfo = FileSystem::upload($file, Path::join(Path::storage(), "cos_temp"), $tempFileName);
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
      //* PUT Object**必带 Content-Type**，这里按扩展名推断后一并交给客户端
      $uploaded = $this->client->upload(
        $saveFileName,
        $tempFileInfo['filePath'],
        ["Content-Type" => FileHelper::getMimeType($tempFileInfo['filePath'])]
      );
      if (!$uploaded) {
        if (FileSystem::exists($tempFileInfo['filePath'])) {
          FileSystem::deleteFile($tempFileInfo['filePath']);
        }

        return $this->break(500, 500, "上传文件失败", $this->client->lastError() ?: "COS 上传失败");
      }

      if (FileSystem::exists($tempFileInfo['filePath'])) {
        FileSystem::deleteFile($tempFileInfo['filePath']);
      }

      $fileInfo = $this->get($saveFileName);
      if (!$fileInfo) return $this->break(500, 500, "获取上传的文件信息失败");
      $fileInfo->width = $width;
      $fileInfo->height = $height;

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
   * 调用 COS 客户端删除对象；**失败时向上抛 Error**。
   *
   * @param string $fileKey 文件键
   * @return boolean|mixed 无数据模型时返回 true；有数据模型时返回模型的删除结果
   * @throws Error 删除失败（细节见客户端 {@see QCloudCosClient::lastError()}）
   */
  function delete($fileKey)
  {
    if (!$this->client->delete($fileKey)) {
      $message = $this->client->lastError() ?: "删除 COS 文件失败";

      throw new Error($message, 500, 500, $message);
    }

    return true;
  }
  /**
   * 判断 COS 上文件是否存在
   *
   * 底层调客户端的 {@see QCloudCosClient::exists()}。**只有 200 才算存在**，
   * 所以「对象不存在（404）」与「没权限（403）」都会返回 false；而
   * 「未建立连接（状态码 0）」或「服务端 5xx」视为**调用失败**，按原契约上抛 Error。
   *
   * @param string $fileName 文件键
   * @return boolean 存在返回 true，不存在返回 false
   * @throws Error 网络/服务端错误时抛出
   */
  function exists($fileName)
  {
    $exists = $this->client->exists($fileName);

    if (!$exists) {
      $last = $this->client->lastResult();
      $status = $last ? (int) $last["status"] : 0;
      if ($status === 0 || $status >= 500) {
        $message = $this->client->lastError() ?: "判断 COS 文件是否存在失败";

        throw new Error($message, 500, 500, $message);
      }
    }

    return $exists;
  }

  /**
   * 获取 COS 文件的访问 URL
   *
   * 根据 $withSignature 决定生成带时效签名的 URL 或公开无签名 URL：
   * - 带签名（默认）：{@see QCloudCosClient::presignedUrl()} —— 拼上 `q-*` 签名参数，$expires 秒内有效；
   * - 无签名：{@see QCloudCosClient::objectUrl()} —— 直接拼域名，可公开访问（依赖桶的公开读策略）。
   *
   * 两者都是**纯计算**（不发请求），域名形如 `https://{bucket}.cos.{region}.myqcloud.com`。
   *
   * @param string $fileName 文件键（内部会 trim 去掉首尾空白）
   * @param array $urlParams 附加的 URL 参数（当前实现未参与计算，保留以对齐父类签名）
   * @param int $expires 带签名时的有效时长（秒），默认 1800
   * @param boolean $withSignature 是否生成带时效签名的 URL，默认 true
   * @return string 访问 URL
   */
  function url($fileName, $urlParams = [], $expires = 1800, $withSignature = true)
  {
    if ($withSignature) {
      return $this->client->presignedUrl(trim($fileName), intval($expires), "get", $urlParams);
    }

    return $this->client->objectUrl(trim($fileName));
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
      return $this->stsClient->getTempKeys($allowPrefix, $allowActions, intval($durationSeconds));
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
   * @return string 签名授权参数字典
   */
  public function createAuthorization($fileKey = null, $expires = 1800, $httpMethod = "get", $urlParams = [], $headers = [])
  {
    $cosSignature = new QCloudCosSignture($this->secretId, $this->secretKey, $this->host, $this->securityToken);

    if (strpos($fileKey, "/") !== 0) {
      $fileKey = "/" . $fileKey;
    }

    return $cosSignature->createAuthorization($fileKey, $urlParams, $headers, $expires, $httpMethod);
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
