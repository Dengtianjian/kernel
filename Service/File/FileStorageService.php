<?php

namespace kernel\Service\File;

use kernel\Controller\Main\Files\FileStorage as FileStorageNamespace;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Serializer;
use kernel\Foundation\Database\PDO\DB;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileStorage;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Foundation\Service;
use kernel\Model\FilesModel;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;
use kernel\Service\File\FileService;
use Qcloud\Cos\Signature;

class FileStorageService extends FileService
{
  /**
   * 使用服务
   *
   * @return void
   */
  static function useService()
  {
    Router::post("fileStorage/upload/auth", FileStorageNamespace\FileStorageGetUploadFileAuthController::class);
    Router::post("fileStorage/{fileId:.+?}", FileStorageNamespace\FileStorageUploadFileController::class);
    Router::delete("fileStorage/{fileId:.+?}", FileStorageNamespace\FileStorageDeleteFileController::class);
    Router::get("fileStorage/{fileId:.+?}/preview", FileStorageNamespace\FileStorageAccessFileController::class);
    Router::get("fileStorage/{fileId:.+?}/download", FileStorageNamespace\FileStorageDownloadFileController::class);
    Router::get("fileStorage/{fileId:.+?}", FileStorageNamespace\FileStorageGetFileController::class);
  }
  static function init()
  {
    FilesModel::singleton()->createTable();
  }
  /**
   * 验证访问签名
   *
   * @param string $FileKey 文件名
   * @param string $Signature 签名
   * @param string $SignatureKey 签名秘钥
   * @param array $RawURLParams 请求参数
   * @param array $RawHeaders 请求头
   * @param string $HTTPMethod 请求方式
   * @return boolean truly验证通过，返回false或者数字就是验证失败
   */
  static function verifyAccessAuth($FileKey, $Signature, $SignatureKey, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    if (!array_key_exists("signature", $RawURLParams)) {
      $RawURLParams['signature'] = $Signature;
    }

    return FileStorage::verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders, $HTTPMethod);
  }
  /**
   * 获取访问授权信息字符串
   *
   * @param string $FileKey 文件名
   * @param string $SignatureKey 签名秘钥
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param string $HTTPMethod 请求方式
   * @param string $ToString 字符串格式返回
   * @return ReturnResult{string} URL请求参数格式的授权信息字符串
   */
  static function getAccessAuth($FileKey, $SignatureKey, $Expires = 600, $URLParams = [], $HTTPMethod = "get", $ToString = false)
  {
    $R = new ReturnResult(true);

    if (!$FileKey) {
      return $R->error(400, 400, "文件名不可为空");
    }

    $AccessAuth = FileStorage::generateAccessAuth($FileKey, $SignatureKey, $Expires, $URLParams, $HTTPMethod, $ToString);

    return $R->success($AccessAuth);
  }
  /**
   * 获取访问URL地址
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param string $SignatureKey 签名秘钥。如果传入该值，请按需传入下面的值，会生成带签名的URL地址
   * @param integer $Expires 签名有效期，秒级
   * @param string $HTTPMethod 请求方式
   * @return ReturnResult{string} 访问的URL地址
   */
  static function getAccessURL($FileKey, $URLParams = [], $SignatureKey = NULL, $Expires = 600, $HTTPMethod = "get")
  {
    $accessURL = "";
    $R = new ReturnResult($accessURL);

    if ($SignatureKey) {
      $FileKeyInfo = pathinfo($FileKey);
      $accessURL = FileStorage::generateAccessURL($FileKeyInfo['dirname'], $FileKeyInfo['basename'], $SignatureKey, $Expires, $URLParams, $HTTPMethod);
    } else {
      $U = new URL(F_BASE_URL);
      $U->pathName = URL::combinedPathName("files", $FileKey);
      foreach ($URLParams as $key => $value) {
        $U->queryParam($value, $key);
      }
      return $U->toString();
    }

    return $R->success($accessURL);
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
   * @return ReturnResult{int}
   */
  static function upload($File, $FileKey, $OwnerId = null, $BelongsId = null, $BelongsType = null, $ACL = 'private')
  {
    $FileInfo = pathinfo($FileKey);
    $UploadedResult = parent::upload($File, $FileInfo['dirname'], $FileInfo['basename']);
    if ($UploadedResult->error) return $UploadedResult;
    $UploadFileInfo = $UploadedResult->getData();

    $FS = new DiscuzXFilesModel();
    return $UploadedResult->success($FS->add($FileKey, $UploadFileInfo['sourceFileName'], $UploadFileInfo['fileName'], $UploadFileInfo['path'], $UploadFileInfo['size'], $UploadFileInfo['extension'], $OwnerId, $ACL, false, $BelongsId, $BelongsType, $UploadFileInfo['width'], $UploadFileInfo['height']));
  }
  /**
   * 获取文件信息
   *
   * @param string $FileKey 文件名
   * @param string $Signature 签名，如果传入该值，就会进行签名校验，需要传入后面的所有参数
   * @param string $SignatureKey 签名秘钥
   * @param string $CurrentAuthId 当前登录态的用户ID，对应的是文件表的OwnerId，会进行一个权限比较
   * @param array $RawURLParams URL请求参数
   * @param array $RawHeaders 请求头
   * @param string $HTTPMethod 请求方式
   * @return ReturnResult<false|array{fileKey:string,sourceFileName:string,path:string,fileName:string,extension:string,size:int,fullPath:string,relativePath:string,width:int,height:int}> 文件信息
   */
  static function getFileInfo($FileKey, $Signature = null, $SignatureKey = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult = FileStorage::verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders, $HTTPMethod);
      if ($verifyResult !== true)
        return $R->error(403, 403001, "签名错误", $verifyResult);
    }

    $File = DiscuzXFilesModel::singleton()->item($FileKey);
    if (!$File) {
      return $R->error(404, 404001, "文件不存在", [], false);
    }

    if ($File['acl'] === FileStorage::PRIVATE) {
      if ($File['ownerId'] && $File['ownerId'] !== $CurrentAuthId) {
        return $R->error(403, 403002, "无权访问", [], $File['acl']);
      }
    } else {
      if (!$Signature) {
        if (in_array($File['acl'], [
          FileStorage::AUTHENTICATED_READ,
          FileStorage::AUTHENTICATED_READ_WRITE
        ])) {
          return $R->error(403, 403003, "无权访问", [], $File['acl']);
        }
      }
    }

    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));
    if (!file_exists($FilePath)) {
      return $R->error(404, 404002, "文件不存在", [], false);
    }

    return $R->success([
      "fileKey" => $FileKey,
      "path" => $File['filePath'],
      "fileName" => $File['fileName'],
      "extension" => $File['extension'],
      "size" => $File['fileSize'],
      "fullPath" => $FilePath,
      "relativePath" => FileHelper::optimizedPath(dirname($FileKey)),
      "ownerId" => $File['ownerId'],
      "width" => $File['width'],
      "height" => $File['height'],
      'acl' => $File['acl'],
      "createdAt" => $File['createdAt'],
      "updatedAt" => $File['updatedAt']
    ]);
  }
  /**
   * 删除文件
   *
   * @param string $FileKey 文件名
   * @param string $Signature 签名，如果传入该值，就会进行签名校验，需要传入后面的所有参数
   * @param string $SignatureKey 签名秘钥
   * @param array $RawURLParams URL请求参数
   * @param array $RawHeaders 请求头
   * @param string $CurrentAuthId 当前登录态的用户ID，对应的是文件表的OwnerId，会进行一个权限比较
   * @param string $HTTPMethod 请求方式
   * @return ReturnResult<boolean> 是否已删除，true=删除完成，false=删除失败
   */
  static function deleteFile($FileKey, $Signature = null, $SignatureKey = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult = FileStorage::verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders, $HTTPMethod);
      if ($verifyResult !== true)
        return $R->error(403, 403001, "签名错误", $verifyResult);
    }

    $FS = new DiscuzXFilesModel();

    $File = $FS->item($FileKey);
    if (!$File) {
      return $R->error(404, 404001, "文件不存在", [], false);
    }

    if ($File['acl'] === FileStorage::PRIVATE) {
      if ($File['ownerId'] && $File['ownerId'] !== $CurrentAuthId) {
        return $R->error(403, 403002, "无权删除", [], $File['acl']);
      }
    } else {
      if ($File['acl'] !== FileStorage::PUBLIC_READ_WRITE && $File['acl'] !== FileStorage::AUTHENTICATED_READ_WRITE) {
        if ($File['ownerId'] !== $CurrentAuthId) {
          return $R->error(403, 403002, "无权删除", [], $File['acl']);
        }
      }
    }

    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));
    if (file_exists($FilePath)) {
      unlink($FilePath);
    }
    $FS->remove(true, $FileKey);

    return $R;
  }
}
