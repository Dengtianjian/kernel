<?php

namespace kernel\Service\File;

use kernel\Foundation\File\FileRemoteStorage;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Service\File\FileStorageService;

class FileRemoteStorageService extends FileStorageService
{
  static function useService($SignatureKey = null)
  {
    parent::useService($SignatureKey);

    self::$FileStorageInstance = new FileRemoteStorage($SignatureKey);
    self::$FileRemoteStorageInstance = new FileRemoteStorage($SignatureKey);
  }
  /**
   * 获取访问URL地址
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param string $WithSignature 生成的URL是否携带签名
   * @param integer $Expires 签名有效期，秒级
   * @return ReturnResult{string} 访问的URL地址
   */
  static function getRemotePreviewURL($FileKey, $URLParams = [], $Headers = [], $WithSignature = TRUE, $Expires = 600)
  {
    $R = new ReturnResult(null);

    return $R->success(self::$FileRemoteStorageInstance->getFilePreviewURL($FileKey, $URLParams, $Headers, $Expires, $WithSignature, true));
  }
  /**
   * 获取下载URL地址
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param string $WithSignature 生成的URL是否携带签名
   * @param integer $Expires 签名有效期，秒级
   * @return ReturnResult{string} 下载的URL地址
   */
  static function getRemoteDownloadURL($FileKey, $URLParams = [], $Headers = [], $WithSignature = TRUE, $Expires = 600)
  {
    $R = new ReturnResult(null);

    return $R->success(self::$FileRemoteStorageInstance->getFileDownloadURL($FileKey, $URLParams, $Headers, $Expires, $WithSignature, true));
  }
}
