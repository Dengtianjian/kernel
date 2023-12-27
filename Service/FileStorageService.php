<?php

namespace kernel\Service;

use kernel\Controller\Main\Files as FilesNamespace;
use kernel\Foundation\Database\PDO\DB;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileStorage;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Foundation\Service;

class FileStorageService extends Service
{
  /**
   * 使用服务
   *
   * @return void
   */
  static function useService()
  {
    Router::post("files", FilesNamespace\UploadFileController::class);
    Router::delete("files/{fileId:.+?}", FilesNamespace\DeleteFileController::class);
    Router::get("files/{fileId:.+?}/preview", FilesNamespace\AccessFileController::class);
    Router::get("files/{fileId:.+?}/download", FilesNamespace\DownloadFileController::class);
    Router::get("files/{fileId:.+?}", FilesNamespace\GetFileController::class);
  }
  static function init()
  {
    $SQL = <<<EOT
DROP TABLE IF EXISTS `files`;
CREATE TABLE `files`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '附件数字ID',
  `key` text NOT NULL COMMENT '文件名',
  `remote` tinyint(4) NOT NULL DEFAULT 0 COMMENT '远程附件',
  `belongsId` varchar(34) NULL DEFAULT NULL COMMENT '所属ID',
  `belongsType` varchar(32) NULL DEFAULT NULL COMMENT '所属ID类型',
  `authId` varchar(32) NOT NULL DEFAULT '0' COMMENT '授权ID',
  `sourceFileName` varchar(255) NOT NULL COMMENT '原本的文件名称',
  `fileName` varchar(255) NOT NULL COMMENT '保存后的文件名称',
  `fileSize` double NOT NULL COMMENT '文件尺寸',
  `filePath` text NOT NULL COMMENT '保存的文件路径',
  `width` double NULL DEFAULT 0 COMMENT '宽度（媒体文件才有该值）',
  `height` double NULL DEFAULT 0 COMMENT '高度（媒体文件才有该值）',
  `extension` varchar(30) NOT NULL COMMENT '文件扩展名',
  `createdAt` varchar(12) NOT NULL COMMENT '创建时间',
  `updatedAt` varchar(12) NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `userId`(`authId`) USING BTREE COMMENT '用户ID'
) COMMENT = '文件';
EOT;
    DB::query($SQL);
  }
  /**
   * 上传文件
   *
   * @param array|string $Files 文件或者多个文件数组
   * @param string $SavePath 保存的完整路径
   * @param string $saveFileName 保存的文件名称。如果未传入该值，将会自动生成新的文件名称
   * @return ReturnResult<false|array{fileKey:string,sourceFileName:string,path:string,fileName:string,extension:string,size:int,fullPath:string,relativePath:string,width:int,height:int}> 上传失败会返回false，成功返回文件信息
   */
  static function upload($Files, $SavePath, $saveFileName = null)
  {
    $R = new ReturnResult(true);
    $UploadedResult = FileStorage::upload($Files, $SavePath, $saveFileName);
    if (is_bool($UploadedResult) && $UploadedResult === false) {
      return $R->error(500, 500, "上传失败", [], false);
    }
    return $R->success($UploadedResult);
  }
  /**
   * 删除文件
   *
   * @param string $FileKey 文件名
   * @param string $Signature 签名，如果传入该值，就会进行签名校验，需要传入后面的所有参数
   * @param string $SignatureKey 签名秘钥
   * @param array $RawURLParams URL请求参数
   * @param array $RawHeaders 请求头
   * @param string $AuthId 授权ID，用于校验请求参数中的AuthId是否与当前值一致
   * @param string $HTTPMethod 请求方式
   * @return ReturnResult<boolean> 是否已删除，true=删除完成，false=删除失败
   */
  static function deleteFile($FileKey, $Signature = null, $SignatureKey = null, $RawURLParams = [], $RawHeaders = [], $AuthId = null, $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult = FileStorage::verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders, $AuthId, $HTTPMethod);
      if ($verifyResult !== false)
        return $R->error(403, 403, "签名错误", $verifyResult);
    } else if ($AuthId) {
      if (!array_key_exists("authId", $RawURLParams)) {
        return $R->error(403, 403002, "无权访问");
      }
      if ($RawURLParams['authId'] != $AuthId) {
        return $R->error(403, 403003, "无权访问");
      }
    }

    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));
    if (file_exists($FilePath)) {
      unlink($FilePath);
    }

    return $R;
  }
  /**
   * 获取文件信息
   *
   * @param string $FileKey 文件名
   * @param string $Signature 签名，如果传入该值，就会进行签名校验，需要传入后面的所有参数
   * @param string $SignatureKey 签名秘钥
   * @param array $RawURLParams URL请求参数
   * @param array $RawHeaders 请求头
   * @param string $AuthId 授权ID，用于校验请求参数中的AuthId是否与当前值一致
   * @param string $HTTPMethod 请求方式
   * @return ReturnResult<false|array{fileKey:string,sourceFileName:string,path:string,fileName:string,extension:string,size:int,fullPath:string,relativePath:string,width:int,height:int,authId:string}> 文件信息
   */
  static function getFileInfo($FileKey, $Signature = null, $SignatureKey = null, $RawURLParams = [], $RawHeaders = [], $AuthId = null, $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult = FileStorage::verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders, $AuthId, $HTTPMethod);
      if ($verifyResult !== true)
        return $R->error(403, 403001, "签名错误", $verifyResult);
    } else if ($AuthId) {
      if (!array_key_exists("authId", $RawURLParams)) {
        return $R->error(403, 403002, "无权访问");
      }
      if ($RawURLParams['authId'] != $AuthId) {
        return $R->error(403, 403003, "无权访问");
      }
    }

    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));
    if (!file_exists($FilePath)) {
      return $R->error(404, 404, "文件不存在", [], false);
    }

    $authId = null;
    if (!array_key_exists("authId", $RawURLParams)) {
      $RawURLParams['authId'] = $RawURLParams['authId'];
    }

    $FileInfo = pathinfo($FilePath);
    $File = [
      "path" => $FileInfo['dirname'],
      "fileName" => $FileInfo['filename'],
      "extension" => $FileInfo['extension'],
      "size" => filesize($FilePath),
      "fullPath" => $FilePath,
      "relativePath" => FileHelper::optimizedPath(dirname($FileKey)),
      "authId" => $authId,
      "width" => 0,
      "height" => 0
    ];
    if (FileHelper::isImage($FilePath)) {
      $imageInfo = \getimagesize($FilePath);
      $File['width'] = $imageInfo[0];
      $File['height'] = $imageInfo[1];
    }

    return $R->success($File);
  }
  /**
   * 获取访问授权信息字符串
   *
   * @param string $FileKey 文件名
   * @param string $SignatureKey 签名秘钥
   * @param integer $Expires 授权有效期
   * @param array $URLParams 请求参数
   * @param string $AuthId 授权ID。一般用于场景是，当前签名只允许给某个用户使用，就可传入该值；校验签名时也需要传入该值，并且校验请求参数的AuthId是否和传入的AuthId一致，不一致就是校验不通过。
   * @param string $HTTPMethod 请求方式
   * @param string $ACL 访问权限控制
   * @return ReturnResult{string} URL请求参数格式的授权信息字符串
   */
  static function getAccessAuth($FileKey, $SignatureKey, $Expires = 600, $URLParams = [], $AuthId = null, $HTTPMethod = "get", $ACL = FileStorage::PRIVATE)
  {
    $R = new ReturnResult(true);

    $FileKeyInfo = pathinfo($FileKey);
    $AccessAuth = FileStorage::generateAccessAuth($FileKeyInfo['dirname'], $FileKeyInfo['basename'], $SignatureKey, $Expires, $URLParams, $AuthId, $HTTPMethod, $ACL);

    return $R->success($AccessAuth);
  }
  /**
   * 获取访问URL地址
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @param string $SignatureKey 签名秘钥。如果传入该值，请按需传入下面的值，会生成带签名的URL地址
   * @param integer $Expires 签名有效期，秒级
   * @param string $AuthId 授权ID。一般用于场景是，当前签名只允许给某个用户使用，就可传入该值；校验签名时也需要传入该值，并且校验请求参数的AuthId是否和传入的AuthId一致，不一致就是校验不通过。
   * @param string $HTTPMethod 请求方式
   * @param string $ACL 访问权限控制
   * @return ReturnResult{string} 访问的URL地址
   */
  static function getAccessURL($FileKey, $URLParams = [], $SignatureKey = NULL, $Expires = 600, $AuthId = null, $HTTPMethod = "get", $ACL = FileStorage::PRIVATE)
  {
    $accessURL = "";
    $R = new ReturnResult($accessURL);

    if ($SignatureKey) {
      $FileKeyInfo = pathinfo($FileKey);
      $accessURL = FileStorage::generateAccessURL($FileKeyInfo['dirname'], $FileKeyInfo['basename'], $SignatureKey, $Expires, $URLParams, $AuthId, $HTTPMethod, $ACL);
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
}
