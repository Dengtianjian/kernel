<?php

namespace kernel\Service;

use kernel\Controller\Main\Files as FilesNamespace;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\Driver\AbstractFileDriver;
use kernel\Foundation\Router;
use kernel\Foundation\Service;

class FileService extends Service
{
  /**
   * 驱动实例
   * @var AbstractFileDriver
   */
  static protected $dirver = null;
  static function useService(AbstractFileDriver $Driver = null, $RoutePrefix = "files")
  {
    if (is_null($Driver)) {
      throw new Exception("缺失文件驱动参数");
    }

    self::$dirver = $Driver;

    $FileNamePattern = "[\w/]+?\.\w+";

    Router::get("$RoutePrefix/{fileKey:$FileNamePattern}", FilesNamespace\GetFileController::class, [], [
      $Driver
    ]);
    Router::get("$RoutePrefix/{fileKey:$FileNamePattern}/preview", FilesNamespace\PreviewFileController::class, [], [
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
   * @return string 访问URL
   */
  static function getFilePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return self::$dirver->getFilePreviewURL($FileKey, $URLParams, $Expires, $WithSignature);
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
   * @return string 下载URL
   */
  static function getFileDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return self::$dirver->getFileDownloadURL($FileKey, $URLParams, $Expires, $WithSignature);
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
}
