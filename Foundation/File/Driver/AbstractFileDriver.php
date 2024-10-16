<?php

namespace kernel\Foundation\File\Driver;

use kernel\Foundation\App;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File\FileInfoData;
use kernel\Foundation\File\FileStorageSignature;
use kernel\Foundation\Object\AbilityBaseObject;
use kernel\Foundation\ReturnResult\ReturnResult;

abstract class AbstractFileDriver extends AbilityBaseObject
{
  /**
   * 私有的，创作者与管理员具备全部权限，其他人没有权限
   */
  const PRIVATE = "private";
  /**
   * 共有读的，匿名用户具备 READ 权限，创作者与管理员具备全部权限
   */
  const PUBLIC_READ = "public-read";
  /**
   * 公有读写，创建者、管理员和匿名用户具备全部权限，通常不建议授予此权限
   */
  const PUBLIC_READ_WRITE = "public-read-write";
  /**
   * 认证用户具备 READ 权限，创作者与管理员具备全部权限
   */
  const AUTHENTICATED_READ = "authenticated-read";
  /**
   * 创建者、管理员和认证用户具备全部权限，通常不建议授予此权限
   */
  const AUTHENTICATED_READ_WRITE = "authenticated-read-write";

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
   * 文件存储签名实例
   *
   * @var FileStorageSignature
   */
  protected $signature = null;

  /**
   * 路由URI前缀
   *
   * @var string
   */
  protected $routePrefix = "files";

  /**
   * 基础地址。用于生成浏览、下载地址时作为基础URL
   *
   * @var string
   */
  protected $baseURL = null;

  /**
   * 实例化文件驱动类
   *
   * @param string $SignatureKey 签名秘钥
   * @param string $RoutePrefix 路由前缀
   * @param string $BaseURL 基础地址
   */
  public function __construct($SignatureKey, $RoutePrefix = "files", $BaseURL = F_BASE_URL)
  {
    $this->signature = new FileStorageSignature($SignatureKey);
    $this->routePrefix = $RoutePrefix;
    $this->baseURL = $BaseURL;
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
  public function verifyRequestAuth($FileKey)
  {
    if (!$this->authorizationEnabled) return TRUE;

    $Request = getApp()->request();
    $URLParams = $Request->query->some();

    $RequestHeaders = $Request->header->some();

    return $this->verifyAuth($FileKey, $URLParams, $RequestHeaders, $Request->method);
  }

  protected $authorizationEnabled = false;

  /**
   * 启用文件授权验证
   *
   */
  public function enableAuth()
  {
    $this->authorizationEnabled = TRUE;

    return $this;
  }

  /**
   * 上传文件，并且保存在服务器
   *
   * @param File $File 文件
   * @return FileInfoData 文件信息
   */
  public abstract function uploadFile($File);
  /**
   * 获取文件信息
   *
   * @param string $FileKey 文件名
   * @return FileInfoData 文件信息
   */
  public abstract function getFileInfo($FileKey);
  /**
   * 删除文件
   *
   * @param string $FileKey 文件名
   * @return boolean 是否已删除，true=删除完成，false=删除失败
   */
  public abstract function deleteFile($FileKey);
  /**
   * 生成远程存储授权信息
   *
   * @param string $FileKey 文件名
   * @return string 授权信息
   */
  public abstract function getFileRemoteAuth($FileKey);
  /**
   * 获取访问链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @return string 访问URL
   */
  public abstract function getFilePreviewURL($FileKey, $URLParams);
  /**
   * 获取远程浏览链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @return string 访问URL
   */
  public abstract function getFileRemotePreviewURL($FileKey, $URLParams);
  /**
   * 获取下载链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @return string 下载URL
   */
  public abstract function getFileDownloadURL($FileKey, $URLParams);
  /**
   * 获取远程下载链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @return string 下载URL
   */
  public abstract function getFileRemoteDownloadURL($FileKey, $URLParams);
}
