<?php

namespace kernel\Foundation\Storage;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileManager;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Model\FilesModel;
use kernel\Service\StorageService;

abstract class AbstractStorage extends AbilityBaseObject
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
   * 路由URI前缀
   *
   * @var string
   */
  protected $routePrefix = "files";

  /**
   * 基础地址。用于生成浏览、下载地址时作为基础URL
   *
   * @var string
   */
  protected $baseURL = null;

  /**
   * 当前平台名称
   *
   * @var string
   */
  protected $platform = null;

  /**
   * 实例化文件驱动类
   *
   * @param string $SignatureKey 签名秘钥
   * @param string $RoutePrefix 路由前缀
   * @param string $BaseURL 基础地址
   */
  public function __construct($SignatureKey, $RoutePrefix = "files", $BaseURL = F_BASE_URL, $Platform = "local")
  {
    $this->signature = new StorageSignature($SignatureKey);
    $this->routePrefix = $RoutePrefix;
    $this->baseURL = $BaseURL;
    $this->platform = $Platform;
  }

  /**
   * 文件模型
   *
   * @var FilesModel
   */
  protected $filesModel = null;

  function enableFilesModel($model = null)
  {
    $this->filesModel = $model ?: new FilesModel();
    return $this;
  }
  function getFilesModel()
  {
    return $this->filesModel;
  }

  protected $authorizationEnabled = false;
  /**
   * 启用文件授权验证
   *
   */
  public function enableAuth()
  {
    $this->authorizationEnabled = TRUE;

    return $this;
  }

  protected $ACLEnabled = false;
  protected $ACL_currentAuthId = null;
  public function enableACL($AuthId)
  {
    $this->ACLEnabled = true;
    $this->ACL_currentAuthId = $AuthId;
    $this->enableAuth();

    return $this;
  }
  protected function getACAuthId()
  {
    if (is_callable($this->ACL_currentAuthId)) return call_user_func($this->ACL_currentAuthId);

    return $this->ACL_currentAuthId;
  }

  /**
   * 验证操作授权
   *
   * @param string $fileKey 文件名
   * @param "read"|"write" $operation 操作，只允许传入read（读）和write（写）参数
   * @return bool
   */
  public function verifyOperationAuthorization($fileKey, $operation = "read")
  {
    $fileInfo = null;
    if ($this->filesModel) {
      $fileInfo = $this->filesModel->field("ownerId", "accessControl")->item($fileKey);
      if (!$fileInfo) {
        return $this->break(404, "operationAuthorization:404", "文件不存在");
      };

      if ($this->getACAuthId() != $fileInfo['ownerId']) {
        // if ($this->verifyRequestAuth($fileKey) === FALSE) {
        //   return $this->break(403, "getFile:403003", "抱歉，您无权获取该文件信息");
        // }
        if ($this->accessAuthozationVerification($fileKey, $fileInfo['accessControl'], $fileInfo['ownerId'], $operation) === FALSE) {
          return $this->break(403, "operationAuthorization:403001", "抱歉，您无权操作/获取该文件", [
            "statusCode" => $this->errorStatusCode,
            "code" => $this->errorCode,
            "message" => $this->errorMessage,
          ]);
        }
      }
    } else if ($this->verifyRequestAuth($fileKey) === FALSE) {
      return $this->break(403, "operationAuthorization:403002", "抱歉，您无权操作/获取该文件");
    }

    return true;
  }
  /**
   * 文件授权校验
   *
   * @param string $fileKey 文件键
   * @param string $authTag 授权值 
   * @param string $OwnerId 拥有者ID
   * @param "read"|"write" $action 操作，只允许传入read（读）和write（写）参数
   * @return boolean TRUE=授权校验通过，FALSE=授权校验失败
   */
  public function accessAuthozationVerification($fileKey, $authTag, $OwnerId, $action = "read")
  {
    if (!$this->filesModel || !$this->ACLEnabled) return TRUE;
    $action = strtolower($action);

    if (!$this->getACAuthId() || $OwnerId != $this->getACAuthId()) {
      if ($authTag === self::PRIVATE) {
        return FALSE;
      } else if (in_array($authTag, [
        self::AUTHENTICATED_READ_WRITE,
        self::AUTHENTICATED_READ
      ])) {
        if ($authTag === self::AUTHENTICATED_READ && $action !== "read") {
          return FALSE;
        }
        $Verifed = $this->verifyRequestAuth($fileKey, TRUE);
        return is_numeric($Verifed) || $Verifed === FALSE ? FALSE : TRUE;
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

  abstract function getFileAuth();
  abstract function getFileSign();
  function getFileTransferAuth(
    $FileKey,
    $Expires = 600,
    $URLParams = [],
    $Headers = [],
    $HTTPMethod = "get"
  ) {
    if (!$FileKey) {
      throw new Exception("文件名不可为空", 400, 400);
    }
    return $this->signature->createAuthorization($FileKey, $URLParams, $Headers, $Expires, $HTTPMethod);
  }

  /**
   * 验证授权信息
   *
   * @param string $FileKey 文件名
   * @param array $RawURLParams 请求参数
   * @param array $RawHeaders 请求头
   * @param string $HTTPMethod 请求方式
   * @return boolean truly验证通过，返回false就是验证失败
   */
  public function verifyAuth($FileKey, $RawURLParams, $RawHeaders = [], $HTTPMethod = "get")
  {
    $URLParamKeys = ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"];
    foreach ($URLParamKeys as $key) {
      if (!array_key_exists($key, $RawURLParams)) {
        return $this->break(400, "verifyAuth:400001", "缺少参数");
      }
    }
    unset($RawURLParams['__storage_platform']);

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

      if (!in_array($key, $URLParamList)) {
        if (!in_array($key, $URLParamKeys)) {
          return $this->break(400, "verifyAuth:400010", "URL 参数缺失");
        }
      }
      if (!in_array($key, $URLParamKeys)) {
        $URLParams[$key] = $value;
      }
    }

    if ($this->signature->verifyAuthorization($Signature, $FileKey, $startTime, $endTime, $URLParams, $Headers, $HTTPMethod)) {
      return true;
    } else {
      return $this->break(403, "verifyAuth:403001", "抱歉，您没有操作该文件的权限");
    }
  }

  /**
   * 校验请求的参数授权是否通过
   *
   * @param string $FileKey 文件名
   * @return boolean true=校验通过，false=校验失败
   */
  public function verifyRequestAuth($FileKey)
  {
    if (!$this->authorizationEnabled) return TRUE;

    $Request = getApp()->request();
    $URLParams = $Request->query->some();

    $RequestHeaders = $Request->header->some();

    return $this->verifyAuth($FileKey, $URLParams, $RequestHeaders, $Request->method);
  }

  public function uploadFile($File, $fileKey = null)
  {
    if ($this->verifyRequestAuth($fileKey) !== TRUE) {
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

    $PathInfo = pathinfo($fileKey);

    $FileInfo = FileManager::upload($File, $PathInfo['dirname'], $PathInfo['basename']);
    if (!$FileInfo) {
      return $this->break(500, 500, "文件上传失败", TRUE);
    }

    $FileInfo['key'] = $fileKey;
    $FileInfo['remote'] = false;

    return $this->getFile($fileKey);
  }
  public function saveFile($file, $fileKey = null, $ownerId = null, $belongsId = null, $belongsType = null, $AC = self::AUTHENTICATED_READ)
  {
    if ($VerifyErrorCode = $this->verifyRequestAuth($fileKey) !== TRUE) {
      return $this->break(403, "saveFile:403001", "抱歉，您没有上传该文件的权限", $VerifyErrorCode);
    }
    if ($this->accessAuthozationVerification($fileKey, $AC, $ownerId, "write") === FALSE) {
      return $this->break(403, "saveFile:403002", "抱歉，您没有上传该文件的权限");
    }

    $PathInfo = pathinfo($fileKey);

    $FileInfo = FileManager::upload($file, $PathInfo['dirname'], $PathInfo['basename']);
    if (!$FileInfo) {
      return $this->break(500, "saveFile:500001", "文件上传失败");
    }

    $FileInfo['key'] = $fileKey;
    $FileInfo['remote'] = false;

    if ($this->filesModel) {
      if ($this->filesModel->existItem($fileKey)) {
        $this->filesModel->remove(true, $fileKey);
      }
      $this->filesModel->add($fileKey, $FileInfo['sourceFileName'], $FileInfo['name'], $FileInfo['path'], $FileInfo['size'], $FileInfo['extension'], $ownerId, $AC, false, $belongsId, $belongsType, $FileInfo['width'], $FileInfo['height']);
    }

    return $this->getFile($fileKey);
  }
  /**
   * 添加文件记录
   *
   * @param string $FileKey 文件键
   * @param string $SourceFileName 原文件名
   * @param string $SaveFileName 现文件名
   * @param string $FilePath 文件保存路径
   * @param int $FileSize 文件大小
   * @param string $Extension 文件扩展名
   * @param string $OwnerId 拥有者ID
   * @param string $ACL 访问权限控制
   * @param boolean $Remote 是否是远程存储
   * @param string $BelongsId 关联数据ID
   * @param string $BelongsType 关联数据类型
   * @param int $Width 媒体文件宽度
   * @param int $Height 媒体文件高度
   * @return int|boolean
   */
  public function addFile($FileKey, $SourceFileName = null, $SaveFileName = null, $FilePath = null, $FileSize = 0, $Extension = null, $OwnerId = null, $ACL = self::AUTHENTICATED_READ, $Remote = false, $BelongsId = null, $BelongsType = null, $Width = null, $Height = null)
  {
    if ($this->filesModel) {
      if ($this->filesModel->existItem($FileKey)) {
        $this->filesModel->remove($FileKey);
      }

      return $this->filesModel->add($FileKey, $SourceFileName, $SaveFileName, $FilePath, $FileSize, $Extension, $OwnerId, $ACL, $Remote, $BelongsId, $BelongsType, $Width, $Height, $this->platform);
    }
    return FALSE;
  }

  abstract function deleteFile($fileKey);
  abstract function fileExist($fileKey);
  abstract function getFile($fileKey);
  abstract function getFilePreviewURL($fileKey);
  function getFileTransferPreviewURL($fileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new URL($this->baseURL);
    $AccessURL->pathName = "{$this->routePrefix}/{$fileKey}/preview";

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileTransferAuth($fileKey, $Expires, $URLParams, []));
      if (array_key_exists("auth", $URLParams)) {
        unset($URLParams['auth']);
      }
    }

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  abstract function getFileDownloadURL($fileKey);
  function getFileTransferDownloadURL($fileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new URL($this->baseURL);
    $AccessURL->pathName = "{$this->routePrefix}/{$fileKey}/download";

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileTransferAuth($fileKey, $Expires, $URLParams, []));
      if (array_key_exists("auth", $URLParams)) {
        unset($URLParams['auth']);
      }
    }

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }

  /**
   * 转换 URL 的 query 参数  
   * 因为每个平台对文件的处理参数都不一样，所以就诞生了该方法，把统一的文件处理参数转换为指定平台的处理参数  
   * 例如腾讯云 COS 的图片缩放是 `imageMogr2/thumbnail/!40p`，而文件链接的是传 `s=40`  
   * 就需要使用该方法吧 `s=40` 转换为 `imageMogr2/thumbnail/!40p`，再去生成链接
   *
   * @param array $URLParams URL 参数
   * @param string $targetPlatform 目标平台
   * @return array
   */
  function convertURLParams($URLParams, $targetPlatform)
  {
    if ($targetPlatform === "cos") {
      $keys = [];
      $imageMogr2Keys = [];
      if (array_key_exists("r", $URLParams)) {
        $imageMogr2Keys[] = 'thumbnail/!' . $URLParams['r'] . 'p/ignore-error/1';
        unset($URLParams['r']);
      }
      if (array_key_exists("q", $URLParams)) {
        $imageMogr2Keys[] = 'quality/' . $URLParams['q'] . "/minsize/1/ignore-error/1";
        unset($URLParams['q']);
      }
      if (array_key_exists("ext", $URLParams)) {
        $imageMogr2Keys[] = 'format/' . $URLParams['ext'] . "/minsize/1/ignore-error/1";
        unset($URLParams['ext']);
      }
      if (array_key_exists("rotate", $URLParams)) {
        $imageMogr2Keys[] = 'rotate/' . $URLParams['rotate'] . "/ignore-error/1";
        unset($URLParams['ext']);
      }
      if ($imageMogr2Keys) {
        $keys[] = "imageMogr2/" . join("/", $imageMogr2Keys);
        $URLParams[] = join("/", $keys);
      }
    }

    return $URLParams;
  }
}
