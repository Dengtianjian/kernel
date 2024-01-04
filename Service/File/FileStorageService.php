<?php

namespace kernel\Service\File;

use kernel\Controller\Main\Files\FileStorage as FileStorageNamespace;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileStorage;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Model\FilesModel;
use kernel\Service\File\FileService;

class FileStorageService extends FileService
{
  /**
   * 文件存储实例
   *
   * @var FileStorage
   */
  protected static $FileStorageInstance = null;
  /**
   * 文件存储表模型实例
   *
   * @var FilesModel
   */
  protected static $FilesModelInstance = null;

  /**
   * 使用服务
   *
   * @return void
   */
  static function useService($SignatureKey = null)
  {
    Router::post("fileStorage/upload/auth", FileStorageNamespace\FileStorageGetUploadFileAuthController::class);
    Router::post("fileStorage/{fileId:.+?}", FileStorageNamespace\FileStorageUploadFileController::class);
    Router::delete("fileStorage/{fileId:.+?}", FileStorageNamespace\FileStorageDeleteFileController::class);
    Router::get("fileStorage/{fileId:.+?}/preview", FileStorageNamespace\FileStorageAccessFileController::class);
    Router::get("fileStorage/{fileId:.+?}/download", FileStorageNamespace\FileStorageDownloadFileController::class);
    Router::get("fileStorage/{fileId:.+?}", FileStorageNamespace\FileStorageGetFileController::class);

    self::$FileStorageInstance = new FileStorage($SignatureKey);
    self::$FilesModelInstance = new FilesModel();
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
   * @param array $RawURLParams 请求参数
   * @param array $RawHeaders 请求头
   * @param string $HTTPMethod 请求方式
   * @return ReturnResulr{boolean} truly验证通过，返回false或者数字就是验证失败
   */
  static function verifyAccessAuth($FileKey, $Signature, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    if (!array_key_exists("signature", $RawURLParams)) {
      $RawURLParams['signature'] = $Signature;
    }

    return (new ReturnResult(self::$FileStorageInstance->verifyAccessAuth($FileKey, $RawURLParams, $RawHeaders, $HTTPMethod)));
  }
  /**
   * 获取访问授权信息字符串
   *
   * @param string $FileKey 文件名
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param string $HTTPMethod 请求方式
   * @param boolean $ToString 字符串格式返回
   * @return ReturnResult{string} URL请求参数格式的授权信息字符串
   */
  static function getAccessAuth($FileKey, $Expires = 600, $URLParams = [], $HTTPMethod = "get", $ToString = false)
  {
    $R = new ReturnResult(null);

    if (!$FileKey) {
      return $R->error(400, 400, "文件名不可为空");
    }

    $AccessAuth = self::$FileStorageInstance->generateAccessAuth($FileKey, $Expires, $URLParams, $HTTPMethod, $ToString);

    return $R->success($AccessAuth);
  }
  /**
   * 获取访问URL地址
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param string $WithSignature 生成的URL是否携带签名
   * @param integer $Expires 签名有效期，秒级
   * @param string $HTTPMethod 请求方式
   * @return ReturnResult{string} 访问的URL地址
   */
  static function getAccessURL($FileKey, $URLParams = [], $WithSignature = TRUE, $Expires = 600, $HTTPMethod = "get")
  {
    $R = new ReturnResult(null);

    return $R->success(self::$FileStorageInstance->generateAccessURL($FileKey, $URLParams, $WithSignature, $Expires, $HTTPMethod));
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
   * @return int 文件数字ID
   */
  static function addFile($FileKey, $SourceFileName, $FileName, $FilePath, $FileSize, $OwnerId = null, $ACL = FileStorage::PRIVATE, $extension = null, $Width = null, $Height = null, $BelongsId = null, $BelongsType = null)
  {
    return (new ReturnResult(self::$FileStorageInstance->addFile($FileKey, $SourceFileName, $FileName, $FilePath, $FileSize, $extension, $OwnerId, $ACL, true, $BelongsId, $BelongsType, $Width, $Height)));
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
   * @return ReturnResult{false|array{fileKey:string, sourceFileName:string, path:string, fileName:string, extension:string, size:int, fullPath:string, relativePath:string, width:int, height:int}}
   */
  static function upload($File, $FileKey, $OwnerId = null, $BelongsId = null, $BelongsType = null, $ACL = 'private')
  {
    $R = new ReturnResult(null);

    $UploadedResult = self::$FileStorageInstance->upload($File, $FileKey, $OwnerId, $BelongsId, $BelongsType, $ACL);
    if ($UploadedResult === false) return $R->error(500, 500, "上传失败", $UploadedResult);

    return $R->success($UploadedResult);
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
  static function getFileInfo($FileKey, $Signature = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);

    $FileInfo = self::$FileStorageInstance->getFileInfo($FileKey, $Signature, $CurrentAuthId, $RawURLParams, $RawHeaders, $HTTPMethod);

    if (is_bool($FileInfo)) {
      return $R->error(500, 500, "获取文件信息失败", $FileInfo);
    }
    if (is_numeric($FileInfo)) {
      switch ($FileInfo) {
        case 0:
          return $R->error(403, 403001, "无权获取文件信息", $FileInfo);
        case 1:
          return $R->error(404, 404001, "文件不存在", [], false);
        case 2:
          return $R->error(403, 403002, "无权获取文件信息");
        case 3:
          return $R->error(403, 403003, "无权获取文件信息");
        case 4:
          return $R->error(404, 404002, "文件不存在");
      }
    }

    $width = $FileInfo['width'];
    $height = $FileInfo['height'];
    $size = $FileInfo['fileSize'];
    if ($FileInfo['remote']) {
      if (!$width || !$height) {
        $ImageInfo = self::$FileStorageInstance->getImageInfo($FileKey);
        if ($ImageInfo === false) {
          return $R->error(500, 500, "获取远程文件信息失败", [], $ImageInfo);
        }
        if (!is_null($ImageInfo)) {
          $FileInfo['width'] = $width = (int)$ImageInfo['width'];
          $FileInfo['height'] = $height = (int)$ImageInfo['height'];
          $FileInfo['fileSize'] = $size = (float)$ImageInfo['size'];

          self::$FilesModelInstance->update([
            "width" => $width,
            "height" => $height,
            "fileSize" => $size
          ]);
        }
      }
    } else {
      $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));

      if (!file_exists($FilePath)) {
        return $R->error(404, 404003, "文件不存在", [], false);
      }

      if (FileHelper::isImage($FilePath) && (!$width || !$height)) {
        $ImageInfo = getimagesize($FilePath);
        if ($ImageInfo) {
          $FileInfo['width'] = $width = (int)$ImageInfo[0];
          $FileInfo['height'] = $height = (int)$ImageInfo[1];
          $FileInfo['fileSize'] = $size = (float)filesize($FilePath);

          self::$FilesModelInstance->update([
            "width" => $width,
            "height" => $height,
            "fileSize" => $size
          ]);
        }
      }
    }

    return $R->success($FileInfo);
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
  static function deleteFile($FileKey, $Signature = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);

    $DeletedResult = self::$FileStorageInstance->deleteFile($FileKey, $Signature, $CurrentAuthId, $RawURLParams, $RawHeaders, $HTTPMethod);

    if (is_bool($DeletedResult)) {
      return $R->error(500, 500, "删除文件失败", $DeletedResult);
    }
    if (is_numeric($DeletedResult)) {
      switch ($DeletedResult) {
        case 0:
          return $R->error(403, 403001, "签名错误");
          break;
        case 1:
          return $R->error(404, 404001, "文件不存在");
          break;
        case 2:
          return $R->error(403, 403002, "无权删除");
          break;
        case 3:
          return $R->error(403, 403002, "无权删除");
          break;
      }
    }

    return $R->success($DeletedResult);
  }
}
