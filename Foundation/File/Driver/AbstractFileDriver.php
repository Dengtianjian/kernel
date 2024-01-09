<?php

namespace kernel\Foundation\File\Driver;

use kernel\Foundation\App;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileStorageSignature;
use kernel\Foundation\ReturnResult\ReturnResult;

abstract class AbstractFileDriver
{
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

  /**
   * 是否硬性验证授权信息  
   * 当前属性用于实例化时作为标记，实际业务时可根据该属性去判断是否需要校验授权信息
   *
   * @var boolean
   */
  public $verifyAuth = false;
  /**
   * 文件存储签名实例
   *
   * @var FileStorageSignature
   */
  protected $signature = null;

  /**
   * 处理结果返回实例
   *
   * @var ReturnResult
   */
  protected $return = null;

  /**
   * 路由URI前缀
   *
   * @var string
   */
  protected $routePrefix = "files";

  /**
   * 实例化文件驱动类
   *
   * @param boolean $VerifyAuth 是否硬性验证授权信息  
   * 当前属性用于实例化时作为标记，实际业务时可根据该属性去判断是否需要校验授权信息
   * @param string $SignatureKey 签名秘钥
   * @param string $RoutePrefix 路由前缀
   */
  public function __construct($VerifyAuth, $SignatureKey, $RoutePrefix = "files")
  {
    $this->verifyAuth = $VerifyAuth;
    $this->signature = new FileStorageSignature($SignatureKey);
    $this->return = new ReturnResult(NULL);
    $this->routePrefix = $RoutePrefix;
  }
  public function __get($name)
  {
    return $this->$name;
  }
  /**
   * 生成访问授权信息
   *
   * @param string $FileKey 文件名
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param string $HTTPMethod 请求方式
   * @param boolean $toString 字符串形式返回参数，如果传入false，将会返回参数数组
   * @return string|array 授权信息
   */
  function getFileAuth($FileKey, $Expires = 1800, $URLParams = [], $Headers = [], $HTTPMethod = "get", $toString = false)
  {
    if (!$FileKey) {
      throw new Exception("文件名不可为空", 400, 400);
    }

    return $this->signature->createAuthorization($FileKey, $URLParams, $Headers, $Expires, $HTTPMethod, $toString);
  }
  /**
   * 验证授权信息
   *
   * @param string $FileKey 文件名称
   * @param array $RawURLParams 请求参数
   * @param array $RawHeaders 请求头
   * @param string $HTTPMethod 请求方式
   * @return boolean truly验证通过，返回false或者数字就是验证失败
   */
  public function verifyAuth($FileKey, $RawURLParams, $RawHeaders = [], $HTTPMethod = "get")
  {
    $URLParamKeys = ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"];
    foreach ($URLParamKeys as $key) {
      if (!array_key_exists($key, $RawURLParams)) {
        return 0;
      }
    }
    $SignAlgorithm = $RawURLParams['sign-algorithm'];
    $SignTime = $RawURLParams['sign-time'];
    $KeyTime = $RawURLParams['key-time'];
    $HeaderList = $RawURLParams['header-list'] ? explode(";", urldecode($RawURLParams['header-list'])) : [];
    $URLParamList = $RawURLParams['url-param-list'] ? explode(";", rawurldecode(urldecode($RawURLParams['url-param-list']))) : [];
    $Signature = $RawURLParams['signature'];

    if ($SignAlgorithm !== FileStorageSignature::getSignAlgorithm()) return 2;
    if (strpos($SignTime, ";") === false || strpos($KeyTime, ";") === false) return 3;
    if ($SignTime !== $KeyTime) return 4;
    list($startTime, $endTime) = explode(";", $SignTime);
    list($keyStartTime, $keyEndTime) = explode(";", $KeyTime);
    $startTime = intval($startTime);
    $endTime = intval($endTime);
    $keyStartTime = intval($keyStartTime);
    $keyEndTime = intval($keyEndTime);
    if ($endTime < $startTime) return 5;
    if ($endTime < time()) return 6;
    if ($keyEndTime < $keyStartTime) return 7;
    if ($keyEndTime < time()) return 8;

    $Headers = [];
    if ($HeaderList) {
      foreach ($RawHeaders as $key => $value) {
        $key = rawurldecode(urldecode($key));
        $value = rawurldecode(urldecode($value));
        if (!array_key_exists($key, $HeaderList)) {
          return 9;
        }
        $Headers[$key] = $value;
      }
    }

    $URLParams = [];
    foreach ($RawURLParams as $key => $value) {
      $key = rawurldecode(urldecode($key));
      $value = rawurldecode(urldecode($value));
      if (!in_array($key, $URLParamList)) {
        if (!in_array($key, $URLParamKeys)) {
          return 10;
        }
      }
      if (!in_array($key, $URLParamKeys)) {
        $URLParams[$key] = $value;
      }
    }

    return $this->signature->verifyAuthorization($Signature, $FileKey, $startTime, $endTime, $URLParams, $Headers, $HTTPMethod);
  }
  /**
   * 校验请求的参数授权是否通过
   *
   * @param string $FileKey 文件名
   * @param boolean $Force 是否强制校验，如果传入false，会看当前驱动实例的verifyAuth的值再去决定是否校验
   * @return boolean true=校验通过，false=校验失败
   */
  public function verifyRequestAuth($FileKey, $Force = FALSE)
  {
    if (!$this->verifyAuth && !$Force) return true;

    $Request = getApp()->request();
    $URLParams = $Request->query->some();
    unset($URLParams['id'], $URLParams['uri']);

    $RequestHeaders = $Request->header->some();

    return $this->verifyAuth($FileKey, $URLParams, $RequestHeaders, $Request->method);
  }

  public abstract function uploadFile($File);
  public abstract function getFileInfo($FileKey);
  public abstract function deleteFile($FileKey);
  public abstract function getFileRemoteAuth($FileKey);
  public abstract function getFilePreviewURL($FileKey, $URLParams);
  public abstract function getFileRemotePreviewURL($FileKey, $URLParams);
  public abstract function getFileDownloadURL($FileKey, $URLParams);
  public abstract function getFileRemoteDownloadURL($FileKey, $URLParams);
  public abstract function getImageInfo($FileKey);
}
