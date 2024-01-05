<?php

namespace kernel\Foundation\File;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\HTTP\URL;
use kernel\Model\FilesModel;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class FileStorage
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
   * 文件存储签名实例
   *
   * @var FileStorageSignature
   */
  protected $signature = null;
  /**
   * 签名秘钥
   *
   * @var string
   */
  protected $signatureKey = null;

  /**
   * 文件存储表实例
   *
   * @var FilesModel
   */
  protected $filesModel = null;

  function __construct($SignatureKey)
  {
    $this->signatureKey = $SignatureKey;

    $this->signature = new FileStorageSignature($SignatureKey);
    $this->filesModel = new FilesModel();
  }

  /**
   * 添加文件记录
   *
   * @param string $FileKey 文件名
   * @param string $SourceFileName 原文件名
   * @param string $FileName 保存的文件名
   * @param string $FilePath 文件路径
   * @param int $FileSize 文件大小
   * @param string $OwnerId 拥有者ID
   * @param string $ACL 访问权限控制
   * @param string $extension 扩展名
   * @param int $Width 宽度
   * @param int $Height 高度
   * @param string $BelongsId 关联的数据ID
   * @param string $BelongsType 关联的数据类型
   * @param boolean $Remote 是否为远程存储文件
   * @return int 文件数字ID
   */
  function addFile($FileKey, $SourceFileName, $FileName, $FilePath, $FileSize, $OwnerId = null, $ACL = self::PRIVATE, $extension = null, $Width = null, $Height = null, $BelongsId = null, $BelongsType = null, $Remote = false)
  {
    if (!$extension) {
      $extension = pathinfo($SourceFileName, PATHINFO_EXTENSION);
    }

    return $this->filesModel->add($FileKey, $SourceFileName, $FileName, $FilePath, $FileSize, $extension, $OwnerId, $ACL, $Remote, $BelongsId, $BelongsType, $Width, $Height);
  }

  /**
   * 上传文件
   *
   * @param File $File 文件
   * @param string $FileKey 文件名
   * @param string $OwnerId 拥有者标识符
   * @param string $BelongsId 关联内容ID
   * @param string $BelongsType  关联内容类型
   * @param string $ACL 访问权限控制
   * @return false|array{fileKey:string, sourceFileName:string, path:string, fileName:string, extension:string, size:int, fullPath:string, relativePath:string, width:int, height:int}
   */
  public function upload($File, $FileKey, $OwnerId = null, $BelongsId = null, $BelongsType = null, $ACL = self::PRIVATE)
  {
    if (!$File) return false;

    $FileInfo = pathinfo($FileKey);

    $fileName = "{$FileInfo['basename']}.{$FileInfo['extension']}";
    $fileSavePath = FileHelper::combinedFilePath(F_APP_STORAGE, $FileInfo['dirname']);

    $UploadedResult = Files::upload($File, $fileSavePath, $fileName);
    if (is_bool($UploadedResult) && $UploadedResult === false) {
      return false;
    }

    $this->filesModel->add($FileKey, $UploadedResult['sourceFileName'], $UploadedResult['fileName'], $UploadedResult['path'], $UploadedResult['size'], $UploadedResult['extension'], $OwnerId, $ACL, false, $BelongsId, $BelongsType, $UploadedResult['width'], $UploadedResult['height']);

    $UploadedResult['fileKey'] = $FileKey;
    $UploadedResult['ownerId'] = $OwnerId;
    $UploadedResult['acl'] = $ACL;
    $UploadedResult['belongsId'] = $BelongsId;
    $UploadedResult['belongsType'] = $BelongsType;

    return $UploadedResult;
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
  function generateAccessAuth($FileKey, $Expires = 600, $URLParams = [], $Headers = [], $HTTPMethod = "get", $toString = false)
  {
    if (!$FileKey) {
      throw new Exception("文件名不可为空", 400, 400);
    }

    return $this->signature->createAuthorization($FileKey, $URLParams, $Headers, $Expires, $HTTPMethod, $toString);
  }
  /**
   * 验证授权签名
   *
   * @param string $FileKey 文件名称
   * @param array $RawURLParams 请求参数
   * @param array $RawHeaders 请求头
   * @param string $HTTPMethod 请求方式
   * @return boolean truly验证通过，返回false或者数字就是验证失败
   */
  function verifyAccessAuth($FileKey, $RawURLParams, $RawHeaders = [], $HTTPMethod = "get")
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
   * 生成访问链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param boolean $WithSignature URL中携带签名
   * @param integer $Expires 有效期，秒级
   * @param string $HTTPMethod 请求方式
   * @return string 访问URL
   */
  function generateAccessURL($FileKey, $URLParams = [], $Headers = [], $WithSignature = true, $Expires = 600,  $HTTPMethod = "get")
  {
    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->generateAccessAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod, false));
    }

    $AccessURLIns = new URL(F_BASE_URL);
    $AccessURLIns->pathName = URL::combinedPathName("fileStorage", $FileKey, "preview");
    $AccessURLIns->queryParam($URLParams);

    return $AccessURLIns->toString();
  }
  /**
   * 生成下载链接
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param boolean $WithSignature URL中携带签名
   * @param integer $Expires 有效期，秒级
   * @param string $HTTPMethod 请求方式
   * @return string 下载URL地址
   */
  function generateDownloadURL($FileKey, $URLParams = [], $Headers = [], $WithSignature = true, $Expires = 600)
  {
    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->generateAccessAuth($FileKey, $Expires, $URLParams, $Headers, "get", false));
    }

    $DownloadURL = new URL(F_BASE_URL);
    $DownloadURL->pathName = URL::combinedPathName("fileStorage", $FileKey, "preview");
    $DownloadURL->queryParam($URLParams);

    return $DownloadURL->toString();
  }
  /**
   * 获取文件信息
   *
   * @param string $FileKey 文件名
   * @param string $Signature 签名，如果传入该值，就会进行签名校验，需要传入后面的所有参数
   * @param string $CurrentAuthId 当前登录态的用户ID，对应的是文件表的OwnerId，会进行一个权限比较
   * @param array $RawURLParams URL请求参数
   * @param array $RawHeaders 请求头
   * @param string $HTTPMethod 请求方式
   * @return ReturnResult<false|array{fileKey:string,sourceFileName:string,path:string,fileName:string,extension:string,size:int,fullPath:string,relativePath:string,width:int,height:int}> 文件信息
   */
  function getFileInfo($FileKey, $Signature = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult =  $this->verifyAccessAuth($FileKey, $RawURLParams, $RawHeaders, $HTTPMethod);
      if ($verifyResult !== true)
        return 0;
    }

    $File = $this->filesModel->item($FileKey);
    if (!$File) {
      return 1;
    }

    if ($File['acl'] === FileStorage::PRIVATE) {
      if ($File['ownerId'] && $File['ownerId'] !== $CurrentAuthId) {
        return 2;
      }
    } else {
      if (!$Signature) {
        if (in_array($File['acl'], [
          FileStorage::AUTHENTICATED_READ,
          FileStorage::AUTHENTICATED_READ_WRITE
        ])) {
          return 3;
        }
      }
    }

    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));
    if (!file_exists($FilePath)) {
      return 4;
    }

    $File['fullPath'] = $FilePath;
    $File['fileKey'] = $FileKey;

    return $File;
  }
  /**
   * 删除文件
   *
   * @param string $FileKey 文件名
   * @param string $Signature 签名，如果传入该值，就会进行签名校验，需要传入后面的所有参数
   * @param string $CurrentAuthId 当前登录态的用户ID，对应的是文件表的OwnerId，会进行一个权限比较
   * @param array $RawURLParams URL请求参数
   * @param array $RawHeaders 请求头
   * @param string $HTTPMethod 请求方式
   * @return ReturnResult<boolean> 是否已删除，true=删除完成，false=删除失败
   */
  function deleteFile($FileKey, $Signature = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult =  $this->verifyAccessAuth($FileKey, $RawURLParams, $RawHeaders, $HTTPMethod);
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

    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));
    if (file_exists($FilePath)) {
      unlink($FilePath);
    }

    return $this->filesModel->remove(true, $FileKey);
  }
  /**
   * 获取图片信息
   *
   * @param string $FileKey 文件名
   * @return array{width:int,height:int,mime:string,bits:int}
   */
  function getImageInfo($FileKey)
  {
    $FilePath = FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey);
    if (!file_exists($FilePath)) return false;

    $ImageInfo = getimagesize($FilePath);
    return [
      "width" => $ImageInfo[0],
      "height" => $ImageInfo[1],
      "mime" => $ImageInfo['mime'],
      "bits" => $ImageInfo['bits']
    ];
  }
}
