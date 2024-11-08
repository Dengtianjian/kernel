<?php

namespace kernel\Service;

use kernel\Foundation\Service;
use kernel\Foundation\Storage\AbstractStorage;
use kernel\Foundation\Storage\LocalStorage;
use kernel\Foundation\Exception\Exception;
use kernel\Controller\Main\Files as FilesNamespace;
use kernel\Foundation\Router;
use kernel\Platform\Aliyun\AliyunOSS\AliyunOSSStorage;

class StorageService extends Service
{
  /**
   * 文件路由前缀
   *
   * @var string
   */
  static protected $routePrefix = "files";
  /**
   * 使用的平台
   *
   * @var AbstractStorage|LocalStorage|AliyunOSSStorage
   */
  static protected $usePlatform = null;
  /**
   * 使用的平台名称
   *
   * @var string
   */
  static protected $usePlatformName = null;
  /**
   * 平台实例
   *
   * @var array<string,AbstractStorage|LocalStorage|AliyunOSSStorage>
   */
  static protected $platformInstances = null;
  /**
   * 文件名匹配正则表达式
   *
   * @var string
   */
  static protected $fileNameMatchPattern = "[\w\/]+?\.\w+";

  /**
   * 通过文件路径、文件名称组合成一个文件键名
   *
   * @param string $filePath 文件路径
   * @param string $fileName 文件名称
   * @param boolean $encode 对文件名进行编码
   * @return string 文件键名
   */
  static function combinedFileKey($filePath, $fileName, $encode = false)
  {
    $filePath = str_replace("\\", "/", $filePath);
    $fileName = str_replace("\\", "/", $fileName);

    $fileKey = implode("/", [
      $filePath,
      $fileName
    ]);
    if (substr($fileKey, 0, 1) === "/") {
      $fileKey = substr($fileKey, 1);
    }

    if ($encode) {
      $fileKey = rawurlencode($fileKey);
    }

    return $fileKey;
  }

  static function useService($usePlatforms = null, $routePrefix = "files")
  {
    $FileNamePattern = self::$fileNameMatchPattern;
    self::$routePrefix = $routePrefix;

    $App = getApp();
    $URI = $App->request()->URI;
    $usePlatformName = array_key_first($usePlatforms);

    if (is_null($usePlatforms)) {
      $usePlatforms = [
        "local" => new LocalStorage("local_storage", $routePrefix)
      ];
    }
    self::$platformInstances = $usePlatforms;
    $driverNames = count($usePlatforms) === 1 ? array_key_first($usePlatforms) : "(" . join("|", array_keys($usePlatforms)) . ")";

    $URIMatchParttern = "/{$routePrefix}\/{$driverNames}\/{$FileNamePattern}/";
    $IsFileRequest = preg_match($URIMatchParttern, $URI, $Matchs);

    if ($IsFileRequest) {
      if (count($Matchs) < 2 || !in_array($Matchs[1], array_keys($usePlatforms))) {
        throw new Exception("文件访问失败", 400, 400, "请求 URI 缺少参数");
      }
      if (!array_key_exists($Matchs[1], $usePlatforms)) {
        throw new Exception("文件不存在", 404, 404, " 不可用的文件类");
      }
      $usePlatformName = $Matchs[1];
    }

    self::switchPlatform($usePlatformName);

    self::registerRoute("get", FilesNamespace\GetFileController::class);
    self::registerRoute("delete", FilesNamespace\DeleteFileController::class);
    self::registerRoute("post", FilesNamespace\UploadFileController::class);
    self::registerRoute("patch", FilesNamespace\UpdateFileController::class);

    self::registerRoute("get", FilesNamespace\PrewiewFileController::class, "preview");
    self::registerRoute("get", FilesNamespace\DownloadFileController::class, "download");
  }
  static function getPlatform($name = null)
  {
    return $name ? self::$platformInstances[$name] : self::$usePlatform;
  }
  static function getPlatformName()
  {
    return self::$usePlatformName;
  }
  static function setPlatform($name, $platformInstance)
  {
    self::$platformInstances[$name] = $platformInstance;

    return self::class;
  }
  static function switchPlatform($name)
  {
    if (!array_key_exists($name, self::$platformInstances)) {
      throw new Exception("服务器错误：文件存储实例不存在", 500, 500, [
        $name,
        array_keys(self::$platformInstances),
        "实例化的文件存储列表中不存在要使用的文件实例"
      ]);
    }

    self::$usePlatformName = $name;
    self::$usePlatform = self::$platformInstances[$name];

    return self::class;
  }
  static function hasPlatform($name)
  {
    return array_key_exists($name, self::$platformInstances);
  }
  static function switchToLocal()
  {
    return self::switchPlatform("local");
  }
  static function switchToCOS()
  {
    return self::switchPlatform("cos");
  }
  static function switchToOSS()
  {
    return self::switchPlatform("oss");
  }

  /**
   * 注册文件路由
   *
   * @param string $Method 请求方法
   * @param string|Controller|array $Controller 控制器，如果传入的是数组，第一个参数是被实例化的控制器，第二个参数指定执行该控制器的方法名称
   * @param string $URI URI地址
   * @param boolean $WithFileKey 注册的路由 URI 中是否加上文件键
   */
  static function registerRoute($Method, $Controller, $URI = null, $WithFileKey = true)
  {
    $matchParttern = "{fileKey:" . self::$fileNameMatchPattern . "}";
    $matchParttern = str_replace("\/", "/", $matchParttern);
    $platformNames = array_keys(self::$platformInstances);
    $platformNames = count($platformNames) > 1 ? "(" . join("|", $platformNames) . ")" : $platformNames;
    $RouteURIs = [self::$routePrefix/*, "{name:{$platformNames}}"*/];

    if ($WithFileKey) $RouteURIs[] = $matchParttern;
    if ($URI) $RouteURIs[] = $URI;

    Router::register("common", $Method, join("/", $RouteURIs), $Controller);

    return self::class;
  }

  static function setFilesBelongs($FileKeys, $BelongsId, $BelongsType)
  {
    return self::$usePlatform->getFilesModel()->save([
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
    ], $FileKeys);
  }
  static function deleteBelongsFiles($BelongsId, $BelongsType)
  {
    return self::$usePlatform->getFilesModel()->where([
      "belongsId" => $BelongsId,
      "belongsType" => $BelongsType,
    ])->delete(true);
  }
  /**
   * 设置文件访问控制权限
   *
   * @param string $FileKey 文件名
   * @param string $AccessControlTag 文件控制权限标签
   * @return int
   */
  static function setFileAccessControl($FileKeys, $AccessControlTag)
  {
    return self::$usePlatform->getFilesModel()->save([
      "accessControl" => $AccessControlTag
    ], $FileKeys);
  }
  /**
   * 设置文件访问控制权限为为 私有的
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setFileAccessControlToPrivate($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$usePlatform::PRIVATE);
  }
  /**
   * 设置文件访问控制权限为为 授权读
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setFileAccessControlToAuthenticatedRead($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$usePlatform::AUTHENTICATED_READ);
  }
  /**
   * 设置文件访问控制权限为 授权读写
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setFileAccessControlToAuthenticatedReadWrite($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$usePlatform::AUTHENTICATED_READ_WRITE);
  }
  /**
   * 设置文件访问控制权限为 共有读写
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setFileAccessControlToPublicReadWrite($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$usePlatform::PUBLIC_READ_WRITE);
  }
  /**
   * 设置文件访问控制权限为 共有读
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setFileAccessControlToPublicRead($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$usePlatform::PUBLIC_READ);
  }

  static function getFileAuth($FileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return self::$usePlatform->getFileAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod);
  }
  static function getFileSign()
  {
    return call_user_func_array([self::$usePlatform, "getFileSign"], func_get_args());
  }
  static function getFileTransferAuth($FileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return self::$usePlatform->getFileTransferAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod);
  }

  static function getFilePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return self::$usePlatform->getFilePreviewURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
  static function getFileTransferPreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return self::$usePlatform->getFileTransferPreviewURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
  static function getFileDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return self::$usePlatform->getFileDownloadURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
  static function getFileTransferDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return self::$usePlatform->getFileTransferDownloadURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
}
