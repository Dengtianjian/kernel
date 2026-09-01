<?php

namespace kernel\Foundation\FileSystem\Storage;

use kernel\Foundation\App;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Model\FilesModel;

/**
 * 文件存储门面（聚合多磁盘 + 签名鉴权 + 访问控制）
 *
 * 这是存储层对外的统一入口，本身不直接读写磁盘，而是：
 * - 聚合一组 {@see AbstractStorage} 磁盘实例（本地 / 腾讯云 COS / 阿里云 OSS 等），通过 {@see disk()}/{@see use()} 切换当前磁盘；
 * - 借助 {@see StorageSignature} 对文件访问 URL 做签名鉴权（防篡改、限时效）；
 * - 借助 FilesModel（启用数据存储后）做文件元信息落库与基于 ACL 标签（PRIVATE / PUBLIC_READ …）的访问控制。
 *
 * 鉴权相关开关：
 * - $authorizationEnabled / {@see auth()}：是否校验请求签名（verifyRequestSignature）；
 * - $accessControlEnabled / {@see accessControl()}：是否按 ACL 标签校验操作权限（checkAccessControl）；
 * - $dataSave / {@see enableDataSave()}：是否把文件元信息写入数据库（save/add/delete/exists 依赖）。
 *
 * @package kernel\Foundation\FileSystem\Storage
 */
class FileStorage extends AbilityBaseObject
{
  /**
   * 私有的，创作者与管理员具备全部权限，其他人没有权限
   */
  const PRIVATE = "private";
  /**
   * 共有读的，匿名用户具备 READ 权限，创作者与管理员具备全部权限
   */
  const PUBLIC_READ = "public-read";
  /**
   * 公有读写，创建者、管理员和匿名用户具备全部权限，通常不建议授予此权限
   */
  const PUBLIC_READ_WRITE = "public-read-write";
  /**
   * 认证用户具备 READ 权限，创作者与管理员具备全部权限
   */
  const AUTHENTICATED_READ = "authenticated-read";
  /**
   * 创建者、管理员和认证用户具备全部权限，通常不建议授予此权限
   */
  const AUTHENTICATED_READ_WRITE = "authenticated-read-write";

  /**
   * 文件存储签名实例
   *
   * @var StorageSignature
   */
  protected $signature = null;
  /**
   * 磁盘驱动表（磁盘名 => 磁盘实例）
   *
   * @var array<string,AbstractStorage>
   */
  protected $disks = [];
  /**
   * 使用中的磁盘
   *
   * @var AbstractStorage
   */
  protected $useDisk = null;
  /**
   * 默认磁盘（磁盘池为空时的兜底磁盘）
   *
   * @var AbstractStorage|null
   */
  protected $defaultDisk = null;
  /**
   * 文件数据模型（启用数据存储后使用）
   *
   * @var FilesModel|null
   */
  protected $model = null;
  /**
   * 基础地址，用于生成文件访问 URL
   *
   * @var string
   */
  protected $baseURL = null;
  /**
   * 文件路由前缀
   *
   * @var string
   */
  protected $prefix = "files";
  /**
   * 是否启用请求签名授权验证（auth）
   *
   * @var boolean
   */
  protected $authEnabled = false;
  /**
   * 是否启用文件访问控制（ac）
   *
   * @var boolean
   */
  protected $accessControlEnabled = false;
  /**
   * 访问控制当前认证 ID（ac 校验时的用户身份）
   *
   * @var mixed
   */
  protected $accessControlAuthId = null;
  /**
   * 是否启用数据存储（写入文件模型）
   *
   * @var boolean
   */
  protected $dataSave = false;
  /**
   * 是否启用 ACL 校验（checkAccessControl 依赖）
   *
   * @var boolean
   */
  protected $acEnabled = false;
  /**
   * 是否启用请求签名授权（verifyRequestSignature 依赖）
   *
   * @var boolean
   */
  protected $authorizationEnabled = false;

  /**
   * 构造文件存储门面
   *
   * 初始化签名实例（以当前应用 ID 为作用域）、注入磁盘池与可选的文件模型，
   * 并将第一块磁盘设为默认使用磁盘，基础访问地址取应用基础 URL。
   *
   * @param array<string,AbstractStorage> $disks 磁盘驱动表（磁盘名 => 磁盘实例）
   * @param FilesModel|null $model 文件数据模型，传 null 表示暂不启用数据存储
   */
  public function __construct($disks, $model = null)
  {
    $this->signature = new StorageSignature(App::id());
    $this->model = $model;
    $this->disks = $disks;

    if ($disks) {
      $this->useDisk = array_values($disks)[0];
    }

    $this->baseURL = URL::baseURL();
  }

  /**
   * 生成一个带唯一前缀的文件键（Key）
   *
   * 形如 `66f3a1b2c3d4.jpg`，用于作为文件在存储中的唯一标识。
   *
   * @param string $extension 文件扩展名（不含点）
   * @return string
   */
  static function generateFileKey($extension)
  {
    return join([uniqid(), ".", $extension]);
  }

  /**
   * 启用文件数据存储（写入数据库）
   *
   * 启用后 save/add/delete/exists 等方法会读写文件模型。
   * 若不传 $model 则使用默认的 {@see FilesModel}。
   *
   * @param FilesModel|null $model 自定义文件模型，null 时使用 FilesModel
   * @return static
   */
  public function enableDataSave($model = null)
  {
    $this->model = $model ?: new FilesModel();

    return $this;
  }
  /**
   * 读取或设置文件数据模型
   *
   * 读写一体：传 $model 时注入模型并返回 $this（链式）；不传时返回当前模型。
   *
   * @param FilesModel|null $model 要注入的文件模型
   * @return static|FilesModel|null
   */
  public function model($model = null)
  {
    if ($model) {
      $this->model = $model;
      return $this;
    }

    return $this->model;
  }

  /**
   * 获取全部磁盘驱动
   *
   * @return array<string,AbstractStorage>
   */
  public function disks()
  {
    return $this->disks;
  }
  /**
   * 获取指定磁盘或当前使用磁盘
   *
   * 传 $name 时返回该名称对应的磁盘；不传时返回当前使用磁盘，
   * 若当前无使用磁盘则回退到默认磁盘。
   *
   * @param string|null $name 磁盘名
   * @return AbstractStorage|null
   */
  public function disk($name = null)
  {
    if ($name) return $this->disks[$name];

    return $this->useDisk ?: $this->defaultDisk;
  }
  /**
   * 切换当前使用的磁盘
   *
   * @param string $name 磁盘名（必须为已注册磁盘）
   * @return AbstractStorage 被切换到的磁盘实例
   */
  public function use($name = null)
  {
    $this->useDisk = $this->disks[$name];
    return $this->useDisk;
  }

  /**
   * 读取或开启请求签名鉴权
   *
   * 读写一体：传 $val 时设置开关并返回 $this（链式）；不传时返回当前开关值。
   * 开启后 put/save 等方法会校验请求签名（verifyRequestSignature）。
   *
   * @param boolean|null $val 是否启用
   * @return static|boolean
   */
  public function auth($val = null)
  {
    if (!is_null($val)) {
      $this->authEnabled = $val;
      return $this;
    }

    return $this->authEnabled;
  }

  /**
   * 读取或开启基于 ACL 标签的访问控制
   *
   * 读写一体：传 $val 时设置开关并返回 $this（链式）；不传时返回当前开关值。
   * 开启后上传/操作会按文件 ACL 标签（PRIVATE / PUBLIC_READ …）校验权限。
   *
   * @param boolean|null $val 是否启用
   * @return static|boolean
   */
  public function accessControl($val = null)
  {
    if (!is_null($val)) {
      $this->accessControlEnabled = $val;
      return $this;
    }

    return $this->accessControlEnabled;
  }
  /**
   * 读取或设置访问控制当前认证身份
   *
   * 读写一体：传 $val 时设置当前认证 ID（如当前用户 ID）并返回 $this（链式）；
   * 不传时返回当前认证 ID。该身份用于 checkAccessControl 判定归属与权限。
   *
   * @param mixed|null $val 认证 ID（通常为用户 ID）
   * @return static|mixed|null
   */
  public function accessControlAuthId($val = null)
  {
    if (!is_null($val)) {
      $this->accessControlAuthId = $val;
      return $this;
    }

    return $this->accessControlAuthId;
  }

  public function get($fileKey)
  {
    $result = $this->useDisk->get($fileKey);
    if ($this->useDisk->error) return $this->useDisk->return();
    if (!$result) return $this->break(500, 500, "获取文件信息失败");

    return $result;
  }

  /**
   * 获取文件（保留方法，由具体磁盘实现覆盖）
   *
   * 在此基类中为空实现，实际逻辑由 {@see AbstractStorage} 的具体子类（本地 / 云端）提供。
   */
  /**
   * 上传文件到当前磁盘
   *
   * 将上传的文件写入当前使用磁盘，并返回包含元信息的 {@see StorageFile} 对象。
   * 启用签名鉴权 / 访问控制时，会先校验上传权限。
   *
   * @param array $file 上传文件数组（同 PHP $_FILES 单文件结构，含 name/type/size/tmp_name 等）
   * @param string|null $saveFileName 指定保存的文件键；为 null 时使用 $file['name']
   * @return StorageFile|false 成功返回 StorageFile（已写入磁盘），失败通过 break/return 返回错误态
   */
  public function put($file, $saveFileName = null)
  {
    $key = $saveFileName ?: $file['name'];

    if ($this->authEnabled) {
      if ($this->verifyRequestSignature($key) !== true) {
        return $this->return();
      }
    }
    if ($this->accessControlEnabled) {
      $accessControl = self::AUTHENTICATED_READ;
      $ownerId = $this->accessControlAuthId;

      if ($this->checkAccessControl($key, $accessControl, $ownerId, "write") === false) {
        return $this->break(403, "uploadFile:403002", "抱歉，您没有上传该文件的权限");
      }
    }

    $pathInfo = pathinfo($key);

    $fileInfo = $this->useDisk->put($file, $key);
    if (!$fileInfo || $this->useDisk->error) {
      return $this->break(500, "putFileFailed:500", "文件上传失败", $this->useDisk->errorDetails);
    }

    $data = [
      "key" => $key,
      "disk" => $this->useDisk->name(),
      "ref" => null,
      "type" => null,
      "mime_type" => $file['type'],
      "owner_id" => null,
      "source_file_name" => $file['name'],
      "name" => $pathInfo['basename'],
      "size" => $file['size'],
      "path" => $pathInfo['dirname'],
      "filePath" => $fileInfo['filePath'],
      "width" => $fileInfo['width'],
      "height" =>  $fileInfo['height'],
      "extension" => $pathInfo['extension'],
      "access_control" => self::PUBLIC_READ
    ];

    return new StorageFile($data);
  }
  /**
   * 保存文件并记录元信息到数据库
   *
   * 需先通过 {@see enableDataSave()} 启用数据存储。方法会：
   * 1. 解析保存路径或文件键（含路径则自动生成唯一文件键）；
   * 2. 校验签名 / 访问控制（若启用）；
   * 3. 写入当前磁盘（复用 put）；
   * 4. 将文件元信息写入数据库（同 key 先删后插，保证幂等）。
   *
   * @param array $file 上传文件数组
   * @param string|null $fileKeyOrSavePath 目标保存路径（不含扩展名，自动生成键）或完整文件键（含扩展名）
   * @param mixed|null $ownerId 文件归属者 ID
   * @param mixed|null $ref 业务关联引用（自定义）
   * @param mixed|null $type 业务类型标识（自定义）
   * @param string $accessControl 文件 ACL 标签，默认 AUTHENTICATED_READ
   * @return StorageFile|false 成功返回 StorageFile，失败返回错误态
   */
  public function save($file, $fileKeyOrSavePath = null, $ownerId = null, $ref = null, $type = null, $accessControl = self::AUTHENTICATED_READ)
  {
    if (!$this->model) {
      throw new Error("未启用文件数据存储功能，无法调用 save 方法", 500, 500);
    }

    $savePath = null;
    $fileKey = null;
    $verifiedFileName = $file['name'];

    if (strpos($fileKeyOrSavePath, ".") === false) {
      $pathInfo = pathinfo($file['name']);

      $verifiedFileName = $file['name'];
      $savePath = $fileKeyOrSavePath;
      $fileKey = FileHelper::combinedFilePath($savePath, self::generateFileKey($pathInfo['extension']));
    } else {
      $fileKey = $verifiedFileName = $fileKeyOrSavePath;
    }

    if ($this->authEnabled) {
      if ($verifyErrorCode = $this->verifyRequestSignature($verifiedFileName) !== TRUE) {
        return $this->break(403, "saveFile:403001", "抱歉，您没有上传该文件的权限", $verifyErrorCode);
      }
    }
    if ($this->accessControlEnabled) {
      if ($this->checkAccessControl($verifiedFileName, $accessControl, $ownerId, "write") === FALSE) {
        return $this->break(403, "saveFile:403002", "抱歉，您没有上传该文件的权限");
      }
    }

    $fileInfo = $this->put($file, $fileKey);
    if ($this->error) return $this->return();

    $fileInfo->ref = $ref;
    $fileInfo->type = $type;
    $fileInfo->owner_id = $ownerId;
    $fileInfo->owner_id = $accessControl;

    if ($this->model->where("key", $fileKey)->exists()) {
      $this->model->where("key", $fileKey)->forceDelete();
    }

    $this->model->key = $fileKey;
    $this->model->source_file_name = $fileInfo->source_file_name;
    $this->model->disk = $fileInfo->disk;
    $this->model->name = $fileInfo->name;
    $this->model->path = !$fileInfo->path || $fileInfo->path === "." ? null : $fileInfo->path;
    $this->model->size = $fileInfo->size;
    $this->model->extension = $fileInfo->extension;
    $this->model->owner_id = $ownerId;
    $this->model->access_control = $accessControl;
    $this->model->ref = $fileInfo->ref;
    $this->model->type = $fileInfo->type;
    $this->model->width = $fileInfo->width;
    $this->model->height = $fileInfo->height;
    $this->model->mime_type = $fileInfo->mime_type;

    $this->model->save();
    $fileInfo->id = $this->model->insertId();

    return $fileInfo;
  }
  /**
   * 仅登记文件元信息（不实际上传文件内容）
   *
   * 需先启用数据存储。适用于文件内容已在外部落盘、仅需把元信息写库的场景。
   * 同 key 记录已存在时先物理删除再插入，保证幂等。
   *
   * @param string $key 文件键（唯一标识）
   * @param string|null $sourceFileName 原始文件名
   * @param string|null $saveFileName 保存文件名
   * @param string|null $path 文件目录路径
   * @param int|null $size 文件大小（字节）
   * @param string|null $extension 扩展名
   * @param string|null $mimeType MIME 类型
   * @param mixed|null $ownerId 归属者 ID
   * @param string $accessControl ACL 标签，默认 AUTHENTICATED_READ
   * @param string $disk 所属磁盘名，默认 local
   * @param mixed|null $ref 业务关联引用
   * @param mixed|null $type 业务类型
   * @param int|null $width 图片宽度
   * @param int|null $height 图片高度
   * @return int|false 成功返回插入记录 ID，失败返回 false
   */
  public function add($key, $sourceFileName = null, $saveFileName = null, $path = null, $size = null, $extension = null, $mimeType = null, $ownerId = null, $accessControl = self::AUTHENTICATED_READ, $disk = "local", $ref = null, $type = null, $width = null, $height = null)
  {
    if (!$this->model) {
      throw new Error("未启用文件数据存储功能，无法调用 save 方法", 500, 500);
    }

    if ($this->model->where("key", $key)->exists()) {
      $this->model->where("key", $key)->forceDelete();
    }

    $this->model->key = $key;
    $this->model->source_file_name = $sourceFileName;
    $this->model->disk = $disk;
    $this->model->name = $saveFileName;
    $this->model->path = !$path || $path === "." ? null : $path;
    $this->model->size = $size;
    $this->model->extension = $extension;
    $this->model->owner_id = $ownerId;
    $this->model->access_control = $accessControl;
    $this->model->ref = $ref;
    $this->model->type = $type;
    $this->model->width = $width;
    $this->model->height = $height;
    $this->model->mime_type = $mimeType;

    return $this->model->save()->insertId();
  }
  /**
   * 删除文件
   *
   * 若启用数据存储，先删除数据库记录，再删除当前磁盘上的实际文件。
   *
   * @param string $key 文件键
   * @return boolean 删除是否成功
   */
  public function delete($key)
  {
    if ($this->model) {
      $this->model->where("key", $key)->forceDelete();
    }

    $this->useDisk->delete($key);
    if ($this->useDisk->error) return false;

    return true;
  }
  /**
   * 判断文件是否存在
   *
   * 若启用数据存储，先查数据库；数据库不存在则直接返回 false。
   * 数据库存在（或禁用数据存储）时，再向当前磁盘确认实际文件是否存在。
   *
   * @param string $key 文件键
   * @return boolean
   */
  public function exists($key)
  {
    if ($this->model) {
      $databaseExists = $this->model->where("key", $key)->exists();
      if (!$databaseExists) return false;
    }

    return $this->useDisk->exists($key);
  }

  /**
   * 生成文件访问 URL
   *
   * 以 {baseURL}/{prefix}/{fileKey} 为基准，按需追加签名授权参数（由 {@see createAuthParams()} 生成）。
   *
   * @param string $fileKey 文件键
   * @param array $urlParams 附加的 URL query 参数
   * @param int $expires 签名有效期（秒），默认 1800
   * @param boolean $withSignature 是否附带签名授权参数，默认 true
   * @return string 完整文件访问 URL
   */
  public function url($fileKey, $urlParams = [], $expires = 1800, $withSignature = true)
  {
    $accessURL = new URL($this->baseURL);
    $accessURL->pathName = "{$this->prefix}/{$fileKey}";

    if ($withSignature) {
      $urlParams = array_merge($urlParams, $this->createAuthParams($fileKey, $expires, $urlParams, []));
      if (array_key_exists("auth", $urlParams)) {
        unset($urlParams['auth']);
      }
    }

    $accessURL->queryParam($urlParams);

    return $accessURL->toString();
  }

  /**
   * 生成文件签名的授权参数（URL query / header 形式）
   *
   * 委托 {@see StorageSignature::createAuthorization()} 生成签名，用于给私有文件 URL 附加上时效与防篡改的鉴权参数。
   *
   * @param string $key 文件键
   * @param int $expires 签名有效期（秒），默认 600
   * @param array $urlParams 参与签名的 URL 参数
   * @param array $headers 参与签名的请求头
   * @param string $httpMethod 参与的 HTTP 方法，默认 get
   * @return array 签名授权参数字典
   * @throws Error 当 $key 为空时抛出（400）
   */
  function createAuthParams(
    $key,
    $expires = 600,
    $urlParams = [],
    $headers = [],
    $httpMethod = "get"
  ) {
    if (!$key) {
      throw new Error("文件名不可为空", 400, 400);
    }
    return $this->signature->createAuthorization($key, $urlParams, $headers, $expires, $httpMethod);
  }
  /**
   * 校验文件访问签名（核心签名算法）
   *
   * 解析请求中的签名参数（sign-algorithm / sign-time / key-time / header-list / signature / url-param-list），
   * 校验算法、时效（sign-time 与 key-time 须一致且在有效期内），再委托 {@see StorageSignature::verifyAuthorization()} 比对签名。
   * 任意校验失败返回 break 错误态；成功返回 true。
   *
   * @param string $fileKey 文件键
   * @param array $rawURLParams 原始 URL query 参数（含签名参数）
   * @param array $rawHeaders 原始请求头（参与签名的部分）
   * @param string $httpMethod 请求方法
   * @return boolean|mixed true 表示通过；否则返回 break 错误态（含错误码）
   */
  public function verifySignature($fileKey, $rawURLParams, $rawHeaders = [], $httpMethod = "get")
  {
    $urlParamKeys = ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"];
    foreach ($urlParamKeys as $key) {
      if (!array_key_exists($key, $rawURLParams)) {
        return $this->break(400, "verifyAuth:400001", "缺少参数");
      }
    }
    unset($rawURLParams['__storage_name']);

    $signAlgorithm = $rawURLParams['sign-algorithm'];
    $signTime = urldecode($rawURLParams['sign-time']);
    $keyTime = urldecode($rawURLParams['key-time']);
    $headerList = $rawURLParams['header-list'] ? explode(";", urldecode($rawURLParams['header-list'])) : [];
    $urlParamList = $rawURLParams['url-param-list'] ? explode(";", rawurldecode(urldecode($rawURLParams['url-param-list']))) : [];
    if ($urlParamList) {
      $urlParamList = array_map(function ($item) {
        return rawurldecode($item);
      }, $urlParamList);
    }
    $signature = $rawURLParams['signature'];

    if ($signAlgorithm !== StorageSignature::getSignAlgorithm()) return $this->break(400, "verifyAuth:400002", "参数错误");
    if (strpos($signTime, ";") === false || strpos($keyTime, ";") === false) return $this->break(400, "verifyAuth:400003", "参数错误");
    if ($signTime !== $keyTime) return $this->break(400, "verifyAuth:400004", "参数错误");
    list($startTime, $endTime) = explode(";", $signTime);
    list($keyStartTime, $keyEndTime) = explode(";", $keyTime);
    $startTime = intval($startTime);
    $endTime = intval($endTime);
    $keyStartTime = intval($keyStartTime);
    $keyEndTime = intval($keyEndTime);
    if ($endTime < $startTime) return $this->break(400, "verifyAuth:400005", "验证信息已过期");
    if ($endTime < time()) return $this->break(400, "verifyAuth:400006", "验证信息已过期");
    if ($keyEndTime < $keyStartTime) return $this->break(400, "verifyAuth:400007", "验证信息已过期");
    if ($keyEndTime < time()) return $this->break(400, "verifyAuth:400008", "验证信息已过期");

    $headers = [];
    if ($headerList) {
      foreach ($rawHeaders as $key => $value) {
        $key = rawurldecode(urldecode($key));
        $value = rawurldecode(urldecode($value));
        if (!array_key_exists($key, $headerList)) {
          return $this->break(400, "verifyAuth:400009", "头部参数缺失");
        }
        $headers[$key] = $value;
      }
    }

    $urlParams = [];
    foreach ($rawURLParams as $key => $value) {
      $key = rawurldecode(urldecode($key));
      $value = rawurldecode(urldecode($value));

      if (!$value) {
        $key = strtolower($key);
      }

      if (!in_array($key, $urlParamList)) {
        if (!in_array($key, $urlParamKeys)) {
          return $this->break(400, "verifyAuth:400010", "URL 参数缺失");
        }
      }
      if (!in_array($key, $urlParamKeys)) {
        $urlParams[$key] = $value;
      }
    }

    if ($this->signature->verifyAuthorization($signature, $fileKey, $startTime, $endTime, $urlParams, $headers, $httpMethod)) {
      return true;
    } else {
      return $this->break(403, "verifyAuth:403001", "抱歉，您没有操作该文件的权限");
    }
  }
  /**
   * 从当前 HTTP 请求中提取参数并校验文件签名
   *
   * 当未启用签名鉴权（$authorizationEnabled）时直接放行（返回 true）。
   * 否则从当前请求的 query / header / method 提取参数，调用 {@see verifySignature()} 校验。
   * $silent 为 true 时，把非布尔、非数字错误码归一为 $this->errorStatusCode（供 checkAccessControl 判定），而非直接返回错误态。
   *
   * @param string $key 文件键
   * @param boolean $silent 是否静默模式（仅返回状态码而非错误态），默认 false
   * @return boolean|mixed true 表示通过；否则为错误态或错误状态码
   */
  public function verifyRequestSignature($key, $silent = false)
  {
    if (!$this->authorizationEnabled) return TRUE;

    $request = getApp()->request();
    $urlParams = $request->query->some();

    $requestHeaders = $request->header->some();

    $result = $this->verifySignature($key, $urlParams, $requestHeaders, $request->method());
    if ($silent && $result !== true && !is_numeric($result)) {
      return $this->errorStatusCode;
    }
    return $result;
  }

  /**
   * 校验对某个文件执行操作（读/写）的授权
   *
   * 授权编排入口：
   * - 启用数据存储时，从库中取文件归属者与 ACL 标签；若当前认证者即归属者则放行，否则交 {@see checkAccessControl()} 按 ACL 判定；
   * - 未启用数据存储时，退化为仅校验请求签名（verifyRequestSignature）。
   *
   * @param string $fileKey 文件键
   * @param string $operation 操作类型，read 或 write，默认 read
   * @return boolean|mixed true 表示授权通过；否则返回 break 错误态
   */
  public function authorizeOperation($fileKey, $operation = "read")
  {
    $fileInfo = null;
    if ($this->dataSave) {
      $fileInfo = $this->model->field("ownerId", "accessControl")->where("key", $fileKey)->first();
      if (!$fileInfo) {
        return $this->break(404, "operationAuthorization:404", "文件不存在");
      };

      if ($this->accessControlAuthId() != $fileInfo['ownerId']) {
        // if ($this->verifyRequestSignature($fileKey) === FALSE) {
        //   return $this->break(403, "getFile:403003", "抱歉，您无权获取该文件信息");
        // }
        if ($this->checkAccessControl($fileKey, $fileInfo['accessControl'], $fileInfo['ownerId'], $operation) === FALSE) {
          return $this->break(403, "operationAuthorization:403001", "抱歉，您无权操作/获取该文件", [
            "statusCode" => $this->errorStatusCode,
            "code" => $this->errorCode,
            "message" => $this->errorMessage,
          ]);
        }
      }
    } else if ($this->verifyRequestSignature($fileKey) === FALSE) {
      return $this->break(403, "operationAuthorization:403002", "抱歉，您无权操作/获取该文件");
    }

    return true;
  }
  /**
   * 基于 ACL 标签判定操作是否被允许
   *
   * 仅当启用数据存储（$dataSave）且启用 ACL 校验（$acEnabled）时生效，否则一律放行。
   * 判定规则：
   * - 访问者与文件归属者相同 → 放行；
   * - 归属者不同且为 PRIVATE → 拒绝；
   * - AUTHENTICATED_READ / AUTHENTICATED_READ_WRITE：认证用户允许；若为只读标签且动作为写 → 拒绝；其余情况校验请求签名；
   * - PUBLIC_READ / PUBLIC_READ_WRITE：公开；若为只读标签且动作为写 → 拒绝。
   *
   * @param string $fileKey 文件键
   * @param string $authTag ACL 标签（类常量之一）
   * @param mixed $ownerId 文件归属者 ID
   * @param string $action 动作，read 或 write，默认 read
   * @return boolean true 表示允许，false 表示拒绝
   */
  public function checkAccessControl($fileKey, $authTag, $ownerId, $action = "read")
  {
    if (!$this->dataSave || !$this->acEnabled) return TRUE;
    $action = strtolower($action);

    if (!$this->accessControlAuthId() || $ownerId != $this->accessControlAuthId()) {
      if ($authTag === self::PRIVATE) {
        return FALSE;
      } else if (in_array($authTag, [
        self::AUTHENTICATED_READ_WRITE,
        self::AUTHENTICATED_READ
      ])) {
        if ($authTag === self::AUTHENTICATED_READ && $action !== "read") {
          return FALSE;
        }
        $verified = $this->verifyRequestSignature($fileKey, true);
        return is_numeric($verified) || $verified === FALSE ? FALSE : TRUE;
      } else if (in_array($authTag, [
        self::PUBLIC_READ,
        self::PUBLIC_READ_WRITE
      ])) {
        if ($authTag === self::PUBLIC_READ && $action !== "read") {
          return FALSE;
        }
      }
    }

    return TRUE;
  }
}
