<?php

namespace kernel\Service;

use kernel\Controller\Main\Files as FilesNamespace;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\Driver\AbstractFileDriver;
use kernel\Foundation\File\Driver\AbstractFileStorageDriver;
use kernel\Foundation\File\Driver\FileStorageDriver;
use kernel\Foundation\Router;
use kernel\Foundation\Service;

class FileService extends Service
{
  /**
   * 文件路由前缀
   *
   * @var string
   */
  static protected $routePrefix = "files";

  /**
   * 当前请求使用驱动实例
   * @var AbstractFileDriver|AbstractFileStorageDriver
   */
  static protected $useDriver = null;

  /**
   * 当前请求使用的驱动名称
   *
   * @var string
   */
  static protected $useDriverName = null;

  /**
   * 文件驱动实例
   *
   * @var array<AbstractFileDriver|AbstractFileStorageDriver>
   */
  static protected $driverInstances = [];
  /**
   * 文件名匹配正则表达式
   *
   * @var string
   */
  static protected $FileNameMatchPattern = "[\w\/\u4e00-\u9fa5]+?\.\w+";

  static function useService($useDrivers = null, $RoutePrefix = "files")
  {
    $FileNamePattern = self::$FileNameMatchPattern;
    self::$routePrefix = $RoutePrefix;

    $App = getApp();
    $URI = $App->request()->URI;
    $useDriverName = array_key_first($useDrivers);

    if (is_null($useDrivers)) {
      $useDrivers = [
        "local" => new FileStorageDriver("local_storage", false)
      ];
    }
    self::$driverInstances = $useDrivers;
    $driverNames = count($useDrivers) === 1 ? array_key_first($useDrivers) : "(" . join("|", array_keys($useDrivers)) . ")";

    $URIMatchParttern = "/" . $RoutePrefix . "\/" . $driverNames . "\/" . $FileNamePattern . "/";
    $IsFileRequest = preg_match($URIMatchParttern, $URI, $Matchs);

    if ($IsFileRequest) {
      if (count($Matchs) < 2 || !in_array($Matchs[1], array_keys($useDrivers))) {
        throw new Exception("文件访问失败", 400, 400, "请求 URI 缺少参数");
      }
      if (!array_key_exists($Matchs[1], $useDrivers)) {
        throw new Exception("文件不存在", 404, 404, " 不可用的文件驱动");
      }
      $useDriverName = $Matchs[1];
    }

    self::useDriver($useDriverName);

    self::registerRoute("get", FilesNamespace\GetFileController::class);
    self::registerRoute("delete", FilesNamespace\DeleteFileController::class);
    self::registerRoute("post", FilesNamespace\UploadFileController::class);

    self::registerRoute("get", FilesNamespace\AuthPreviewFileController::class, "preview/auth");
    self::registerRoute("get", FilesNamespace\PrewiewFileController::class, "preview");
    self::registerRoute("get", FilesNamespace\DownloadFileController::class, "download");
  }
  /**
   * 生成访问授权信息
   *
   * @param string $FileKey 文件名
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param string $HTTPMethod 请求方式
   * @return string 授权信息
   */
  static function getFileAuth($FileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return self::$useDriver->getFileAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod, true);
  }
  /**
   * 生成远程存储授权信息
   *
   * @param string $FileKey 文件名
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param string $HTTPMethod 请求方式
   * @param boolean $toString 字符串形式返回参数，如果传入false，将会返回参数数组
   * @return string|array 授权信息
   */
  static function getFileRemoteAuth($FileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get", $toString = false)
  {
    return self::$useDriver->getFileRemoteAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod, $toString);
  }
  /**
   * 获取访问链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param int $Expires 签名有效期
   * @param bool $WithSignature 带有签名
   * @param bool $WithAccessControl 带有授权控制的
   * @return string 访问URL
   */
  static function getFilePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE, $WithAccessControl = TRUE)
  {
    return self::$useDriver->getFilePreviewURL($FileKey, $URLParams, $Expires, $WithSignature, $WithAccessControl);
  }
  /**
   * 获取远程浏览链接
   * @deprecated
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param int $Expires 签名有效期
   * @param bool $WithSignature 带有签名
   * @return string 访问URL
   */
  static function getFileRemotePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return self::$useDriver->getFileRemotePreviewURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
  /**
   * 获取下载链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param int $Expires 签名有效期
   * @param bool $WithSignature 带有签名
   * @param bool $WithAccessControl 带有授权控制的
   * @return string 下载URL
   */
  static function getFileDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE, $WithAccessControl = TRUE)
  {
    return self::$useDriver->getFileDownloadURL($FileKey, $URLParams, $Expires, $WithSignature, $WithAccessControl);
  }
  /**
   * 获取远程下载链接
   * @deprecated
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param int $Expires 签名有效期
   * @param bool $WithSignature 带有签名
   * @return string 下载URL
   */
  static function getFileRemoteDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return self::$useDriver->getFileRemoteDownloadURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
  /**
   * 设置文件所属
   *
   * @param string $FileKey 文件名
   * @param string $BelongsId 所属ID
   * @param string $BelongsType 所属ID数据类型
   * @return int
   */
  static function setFileBelongs($FileKey, $BelongsId, $BelongsType)
  {
    return self::$useDriver->setFileBelongs(
      $FileKey,
      $BelongsId,
      $BelongsType
    );
  }
  /**
   * 设置多个文件所属
   *
   * @param array $FileKeys 文件名数组
   * @param string $BelongsId 所属ID
   * @param string $BelongsType 所属ID数据类型
   * @return int
   */
  static function setFilesBelongs($FileKeys, $BelongsId, $BelongsType)
  {
    foreach ($FileKeys as $FileKey) {
      self::$useDriver->setFileBelongs(
        $FileKey,
        $BelongsId,
        $BelongsType
      );
    }

    return true;
  }
  /**
   * 删除相关类型&ID的文件
   *
   * @param string $BelongsId 所属ID
   * @param string $BelongsType 所属ID数据类型
   * @return int
   */
  static function deleteBelongsFiles($BelongsId, $BelongsType)
  {
    return self::$useDriver->deleteBelongsFile($BelongsId, $BelongsType);
  }
  /**
   * 设置文件访问控制权限
   *
   * @param string $FileKey 文件名
   * @param string $AccessControlTag 文件控制权限标签
   * @return int
   */
  static function setFileAccessControl($FileKey, $AccessControlTag)
  {
    return self::$useDriver->setAccessControl($FileKey, $AccessControlTag);
  }
  /**
   * 设置文件访问控制权限为为 私有的
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setAccessControlToPrivate($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$useDriver::PRIVATE);
  }
  /**
   * 设置文件访问控制权限为为 授权读
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setAccessControlToAuthenticatedRead($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$useDriver::AUTHENTICATED_READ);
  }
  /**
   * 设置文件访问控制权限为 授权读写
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setAccessControlToAuthenticatedReadWrite($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$useDriver::AUTHENTICATED_READ_WRITE);
  }
  /**
   * 设置文件访问控制权限为 共有读写
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setAccessControlToPublicReadWrite($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$useDriver::PUBLIC_READ_WRITE);
  }
  /**
   * 设置文件访问控制权限为 共有读
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setAccessControlToPublicRead($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$useDriver::PUBLIC_READ);
  }

  /**
   * 获取当前使用的文件驱动实例
   *
   * @return AbstractFileDriver|AbstractFileStorageDriver
   */
  static function getDriver()
  {
    return self::$useDriver;
  }
  /**
   * 设置驱动
   *
   * @param string $name 驱动名称
   * @param AbstractFileDriver|AbstractFileStorageDriver $driver 驱动类
   */
  static function setDriver($name, $driverInstance)
  {
    self::$driverInstances[$name] = $driverInstance;

    return self::class;
  }

  /**
   * 使用驱动
   *
   * @param string $name 在实例化列表中的文件驱动名称，没有的话就调用 setDriver 添加实例化的驱动
   */
  static function useDriver($name)
  {
    if (!array_key_exists($name, self::$driverInstances)) {
      throw new Exception("服务器错误：文件存储驱动实例不存在", 500, 500, [
        $name,
        array_keys(self::$driverInstances),
        "实例化的驱动列表中不存在要使用的文件驱动"
      ]);
    }

    self::$useDriverName = $name;
    self::$useDriver = self::$driverInstances[$name];

    return self::class;
  }
  /**
   * 使用本地文件驱动
   *
   */
  static function useLocalDriver()
  {
    return self::useDriver("local");
  }
  /**
   * 使用阿里云 OSS 文件驱动
   *
   */
  static function useOSSDriver()
  {
    return self::useDriver("oss");
  }
  /**
   * 使用腾讯云 COS 文件驱动
   *
   */
  static function useCOSDriver()
  {
    return self::useDriver("cos");
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
    $matchParttern = "{fileKey:" . self::$FileNameMatchPattern . "}";
    $matchParttern = str_replace("\/", "/", $matchParttern);
    $driverNames = array_keys(self::$driverInstances);
    $driverNames = count($driverNames) > 1 ? "(" . join("|", $driverNames) . ")" : $driverNames;
    $RouteURIs = [self::$routePrefix, "{name:{$driverNames}}"];

    if ($WithFileKey) $RouteURIs[] = $matchParttern;
    if ($URI) $RouteURIs[] = $URI;

    Router::register("common", $Method, join("/", $RouteURIs), $Controller);

    return self::class;
  }
}
