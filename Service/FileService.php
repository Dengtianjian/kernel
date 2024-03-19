<?php

namespace kernel\Service;

use kernel\Controller\Main\Files as FilesNamespace;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\Driver\AbstractFileDriver;
use kernel\Foundation\File\Driver\AbstractFileStorageDriver;
use kernel\Foundation\Router;
use kernel\Foundation\Service;

class FileService extends Service
{
  /**
   * 驱动实例
   * @var AbstractFileDriver|AbstractFileStorageDriver
   */
  static protected $dirver = null;
  /**
   * 文件名匹配正则表达式
   *
   * @var string
   */
  static protected $FileNameMatchPattern = "[\w/\u4e00-\u9fa5]+?\.\w+";
  static function useService($Driver = null, $RoutePrefix = "files")
  {
    if (is_null($Driver)) {
      throw new Exception("缺失文件驱动参数");
    }

    self::$dirver = $Driver;

    $FileNamePattern = self::$FileNameMatchPattern;

    Router::get("$RoutePrefix/{fileKey:$FileNamePattern}", FilesNamespace\GetFileController::class, [], [
      $Driver
    ]);
    Router::get("$RoutePrefix/{fileKey:$FileNamePattern}/preview/auth", FilesNamespace\AuthPreviewFileController::class, [], [
      $Driver
    ]);
    Router::get("$RoutePrefix/{fileKey:$FileNamePattern}/preview", FilesNamespace\PrewiewFileController::class, [], [
      $Driver
    ]);
    Router::get("$RoutePrefix/{fileKey:$FileNamePattern}/download", FilesNamespace\DownloadFileController::class, [], [
      $Driver
    ]);
    Router::delete("$RoutePrefix/{fileKey:$FileNamePattern}", FilesNamespace\DeleteFileController::class, [], [
      $Driver
    ]);
    Router::post("$RoutePrefix/{fileKey:$FileNamePattern}", FilesNamespace\UploadFileController::class, [], [
      $Driver
    ]);
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
    return self::$dirver->getFileAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod, true);
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
    return self::$dirver->getFileRemoteAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod, $toString);
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
    return self::$dirver->getFilePreviewURL($FileKey, $URLParams, $Expires, $WithSignature, $WithAccessControl);
  }
  /**
   * 获取远程浏览链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param int $Expires 签名有效期
   * @param bool $WithSignature 带有签名
   * @return string 访问URL
   */
  static function getFileRemotePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return self::$dirver->getFileRemotePreviewURL($FileKey, $URLParams, $Expires, $WithSignature);
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
    return self::$dirver->getFileDownloadURL($FileKey, $URLParams, $Expires, $WithSignature, $WithAccessControl);
  }
  /**
   * 获取远程下载链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param int $Expires 签名有效期
   * @param bool $WithSignature 带有签名
   * @return string 下载URL
   */
  static function getFileRemoteDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return self::$dirver->getFileRemoteDownloadURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
  /**
   * 获取图片信息
   *
   * @param string $FileKey
   * @return ReturnResult{array{fileKey:string,path:string,fileName:string,extension:string,fileSize:int,filePath:string,width:int|null,height:int|null,remote:boolean,url:string}} 文件信息
   */
  static function getImageInfo($FileKey)
  {
    return self::$dirver->getImageInfo($FileKey);
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
    return self::$dirver->setFileBelongs(
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
      self::$dirver->setFileBelongs(
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
    return self::$dirver->deleteBelongsFile($BelongsId, $BelongsType);
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
    return self::$dirver->setAccessControl($FileKey, $AccessControlTag);
  }
  /**
   * 设置文件访问控制权限为为 私有的
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setAccessControlToPrivate($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$dirver::PRIVATE);
  }
  /**
   * 设置文件访问控制权限为为 授权读
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setAccessControlToAuthenticatedRead($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$dirver::AUTHENTICATED_READ);
  }
  /**
   * 设置文件访问控制权限为 授权读写
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setAccessControlToAuthenticatedReadWrite($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$dirver::AUTHENTICATED_READ_WRITE);
  }
  /**
   * 设置文件访问控制权限为 共有读写
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setAccessControlToPublicReadWrite($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$dirver::PUBLIC_READ_WRITE);
  }
  /**
   * 设置文件访问控制权限为 共有读
   *
   * @param string $FileKey 文件名
   * @return int
   */
  static function setAccessControlToPublicRead($FileKey)
  {
    return self::setFileAccessControl($FileKey, self::$dirver::PUBLIC_READ);
  }
}
