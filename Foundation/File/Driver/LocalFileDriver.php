<?php

namespace kernel\Foundation\File\Driver;

use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileManager;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\ReturnResult\ReturnResult;

class LocalFileDriver extends AbstractFileDriver
{
  /**
   * 上传文件，并且保存在服务器
   *
   * @param File $File 文件
   * @param string $FileKey 文件名
   * @return ReturnResult{array{fileKey:string,sourceFileName:string,path:string,filePath:string,fileName:string,extension:string,fileSize:int,width:int,height:int,remote:boolean}} 文件信息
   */
  function uploadFile($File, $FileKey = null)
  {
    $PathInfo = pathinfo($FileKey);

    $FileInfo = FileManager::upload($File, $PathInfo['dirname'], $PathInfo['basename']);
    if (!$FileInfo) return $this->return->error(500, 500, "文件上传失败");

    $FileInfo['fileKey'] = self::combinedFileKey($FileInfo['path'], $FileInfo['fileName']);
    $FileInfo['fileSize'] = $FileInfo['size'];
    $FileInfo['remote'] = false;

    return $this->return->success($FileInfo);
  }
  /**
   * 删除文件
   *
   * @param string $FileKey 文件名
   * @return ReturnResult{boolean} 是否已删除，true=删除完成，false=删除失败
   */
  function deleteFile($FileKey)
  {
    return $this->return->success(FileManager::deleteFile(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey))));
  }
  /**
   * 获取文件信息
   *
   * @param string $FileKey 文件名
   * @return ReturnResult{array{fileKey:string,path:string,fileName:string,extension:string,fileSize:int,filePath:string,width:int|null,height:int|null,remote:boolean}} 文件信息
   */
  function getFileInfo($FileKey)
  {
    $FileInfo = FileManager::getFileInfo(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey)));
    if (!$FileInfo) return $this->return->error(404, 404, "文件不存在");

    $FileInfo['fileKey'] = $FileKey;
    $FileInfo['fileSize'] = $FileInfo['size'];
    $FileInfo['remote'] = false;

    return $this->return->success($FileInfo);
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
  function getFileRemoteAuth($FileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get", $toString = false)
  {
    return $this->getFileAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod, $toString);
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
  function getFilePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "files/{$FileKey}/preview";

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
    }

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
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
  function getFileRemotePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return $this->getFilePreviewURL($FileKey, $URLParams, $Expires, $WithSignature);
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
  function getFileDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new URL(F_BASE_URL);
    $AccessURL->pathName = "files/{$FileKey}/download";

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
    }

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
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
  function getFileRemoteDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    return $this->getFileDownloadURL($FileKey, $URLParams, $Expires, $WithSignature);
  }
  /**
   * 获取图片信息
   *
   * @param string $FileKey
   * @return ReturnResult{array{fileKey:string,path:string,fileName:string,extension:string,fileSize:int,filePath:string,width:int|null,height:int|null,remote:boolean}} 文件信息
   */
  function getImageInfo($FileKey)
  {
    return $this->getFileInfo($FileKey);
  }
}
