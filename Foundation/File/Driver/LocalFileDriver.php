<?php

namespace kernel\Foundation\File\Driver;

use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileInfoData;
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
   */
  function uploadFile($File, $FileKey = null)
  {
    if ($this->verifyRequestAuth($FileKey) !== TRUE) {
      return $this->break(403, 403, "抱歉，您无权上传该文件");
    }

    $PathInfo = pathinfo($FileKey);

    $FileInfo = FileManager::upload($File, $PathInfo['dirname'], $PathInfo['basename']);
    if (!$FileInfo) {
      return $this->break(500, 500, "文件上传失败", TRUE);
    }

    return $this->getFileInfo($FileKey);
  }
  /**
   * 删除文件
   *
   * @param string $FileKey 文件名
   * @return boolean 是否已删除，true=删除完成，false=删除失败
   */
  function deleteFile($FileKey)
  {
    if ($this->verifyRequestAuth($FileKey) !== TRUE) {
      return $this->break(403, 403, "抱歉，您无权删除该文件");
    }

    return FileManager::deleteFile(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey)));
  }
  /**
   * 获取文件信息
   *
   * @param string $FileKey 文件名
   * @return FileInfoData 文件信息
   */
  function getFileInfo($FileKey)
  {
    if ($this->verifyRequestAuth($FileKey) !== TRUE) {
      return $this->break(403, 403, "抱歉，您无权该文件信息");
    }

    $FileInfo = FileManager::getFileInfo(FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey)));
    if (!$FileInfo) {
      return $this->break(404, 404, "文件不存在");
    };

    $FileInfo['key'] = $FileKey;
    $FileInfo['remote'] = false;
    $FileInfo['url'] = $this->getFilePreviewURL($FileKey, [], 1800, FALSE);
    $FileInfo['previewURL'] = $this->getFilePreviewURL($FileKey);
    $FileInfo['downloadURL'] = $this->getFileDownloadURL($FileKey);

    return new FileInfoData($FileInfo);
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
    $AccessURL->pathName = "{$this->routePrefix}/{$FileKey}/preview";

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
      if (array_key_exists("auth", $URLParams)) {
        unset($URLParams['auth']);
      }
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
    $AccessURL->pathName = "{$this->routePrefix}/{$FileKey}/download";

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
      if (array_key_exists("auth", $URLParams)) {
        unset($URLParams['auth']);
      }
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
   * @param string $FileKey 文件键
   */
  function getImageInfo($FileKey)
  {
    return $this->getFileInfo($FileKey);
  }
}
