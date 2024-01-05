<?php

namespace kernel\Foundation\File;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class FileRemoteStorage extends FileStorage
{
  /**
   * 远程存储实例
   *
   * @var object
   */
  protected $RemoteStorageInstance = null;
  /**
   * 本地存储实例
   *
   * @var object
   */
  protected $FileStorageInstance = null;

  /**
   * 获取文件授权信息
   *
   * @param string $FileKey 对象名称
   * @param integer $Expires 有效期，秒级
   * @param array $URLParams URL的query参数
   * @param array $Headers 请求头
   * @param string $HTTPMethod 访问请求方法
   * @param boolean $Remote 是否远程文件
   * @return string
   */
  public function getFileAuth($FileKey, $Expires = 600, $URLParams = [], $Headers = [], $HTTPMethod = "get", $Remote = true)
  {
    if ($Remote) {
      return $this->RemoteStorageInstance->getFileAuth($FileKey, $HTTPMethod, $Expires, $URLParams, $Headers);
    } else {
      return $this->FileStorageInstance->getFileAuth($FileKey, $HTTPMethod, $Expires, $URLParams, $Headers);
    }
  }
  /**
   * 获取访问链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param integer $Expires 有效期，秒级
   * @param boolean $WithSignature URL中携带签名
   * @param boolean $Remote 远程存储文件
   * @return string 访问URL
   */
  function getFilePreviewURL($FileKey, $URLParams = [], $Headers = [], $Expires = 600, $WithSignature = true, $remote = false)
  {
    $remote = boolval(intval($remote));

    if ($remote) {
      return $this->RemoteStorageInstance->getFilePreviewURL($FileKey, $URLParams, $Expires, $WithSignature);
    } else {
      return $this->FileStorageInstance->getFilePreviewURL($FileKey, $URLParams, $Expires, $WithSignature);
    }
  }
  /**
   * 获取下载链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param integer $Expires 有效期，秒级
   * @param boolean $WithSignature URL中携带签名
   * @param boolean $remote 远程存储文件
   * @return string 下载URL地址
   */
  function getFileDownloadURL($FileKey, $URLParams = [], $Headers = [], $Expires = 600, $WithSignature = true, $remote = false)
  {
    $remote = boolval(intval($remote));

    if ($remote) {
      return $this->RemoteStorageInstance->getFileDownloadURL($FileKey, $URLParams,  $Expires, $WithSignature);
    } else {
      return $this->FileStorageInstance->getFileDownloadURL($FileKey, $URLParams, $Expires, $WithSignature);
    }
  }
  /**
   * 删除文件
   *
   * @param string $FileKey — 文件名
   * @return int
   */
  public function deleteFile($FileKey, $Signature = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult = $this->verifyAccessAuth($FileKey, $RawURLParams, $RawHeaders, $HTTPMethod);
      if ($verifyResult !== true)
        return 0;
    }

    $File = $this->filesModel->item($FileKey);
    if (!$File) {
      return 1;
    }

    if ($File['acl'] === self::PRIVATE) {
      if ($File['ownerId'] && $File['ownerId'] !== $CurrentAuthId) {
        return 2;
      }
    } else {
      if ($File['acl'] !== self::PUBLIC_READ_WRITE && $File['acl'] !== self::AUTHENTICATED_READ_WRITE) {
        if ($File['ownerId'] !== $CurrentAuthId) {
          return 3;
        }
      }
    }

    if ($File['remote']) {
      $this->RemoteStorageInstance->deleteFile($FileKey);
    } else {
      parent::deleteFile($FileKey);
    }

    return $this->filesModel->remove(true, $FileKey);
  }
  /**
   * 获取图片信息
   *
   * @param string $ObjectKey 对象键名
   * @return array{width:int,height:int,size:int}|false
   */
  function getImageInfo($FileKey)
  {
    $File = $this->getFileInfo($FileKey);
    if ($File['remote']) {
      return $this->RemoteStorageInstance->getImageInfo($FileKey);
    } else {
      return parent::getImageInfo($FileKey);
    }
  }
}
