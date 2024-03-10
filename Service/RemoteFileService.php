<?php

namespace kernel\Service;

class RemoteFileService extends FileService
{
  /**
   * 驱动实例
   * @var AbstractFileDriver|AbstractFileStorageDriver
   */
  static protected $dirver = null;
  static function useService($Driver = null, $RoutePrefix = "files")
  {
    self::$dirver = $Driver;
    parent::useService($Driver, $RoutePrefix);
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
  static function getRemoteFileAuth($FileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return self::$dirver->getFileRemoteAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod, true);
  }
}
