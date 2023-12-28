<?php

namespace kernel\Service\File;

use kernel\Controller\Main\Files as FilesNamespace;
use kernel\Foundation\Database\PDO\DB;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileStorage;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Foundation\Service;
use kernel\Model\FilesModel;

class FileService extends Service
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
  /**
   * 上传文件
   *
   * @param array|string $Files 文件或者多个文件数组
   * @param string $SavePath 保存的完整路径
   * @param string $saveFileName 保存的文件名称。如果未传入该值，将会自动生成新的文件名称
   * @param string $OwnerId 文件拥有者ID
   * @param string $BelongsId 关联的ID
   * @param string $BelongsType 关联的ID类型
   * @param string $ACL 访问权限控制
   * @return ReturnResult<false|array{fileKey:string,sourceFileName:string,path:string,fileName:string,extension:string,size:int,fullPath:string,relativePath:string,width:int,height:int}> 上传失败会返回false，成功返回文件信息
   */
  static function upload($Files, $SavePath, $saveFileName = null, $OwnerId = null, $BelongsId = null, $BelongsType = null, $ACL = FileStorage::PRIVATE)
  {
    $R = new ReturnResult(true);
    $UploadedResult = FileStorage::upload($Files, $SavePath, $saveFileName);
    if (is_bool($UploadedResult) && $UploadedResult === false) {
      return $R->error(500, 500, "上传失败", [], false);
    }

    $FM = new FilesModel();
    if (is_array($Files)) {
      $Keys = [
        "key",
        "sourceFileName",
        "fileName",
        "filePath",
        "fileSize",
        "extension",
        "remote",
        "belongsId",
        "belongsType",
        "ownerId",
        "width",
        "height",
        "acl"
      ];
      $Values = [];
      foreach ($UploadedResult as $item) {
        array_push($Values, [
          $item['fileKey'],
          $item['sourceFileName'],
          $item['fileName'],
          $item['path'],
          $item['size'],
          $item['extension'],
          false,
          $BelongsId,
          $BelongsType,
          $OwnerId,
          $item['width'],
          $item['height'],
          $ACL
        ]);
      }
      return $FM->batchInsert($Keys, $Values, true);
    } else {
      return $FM->add($UploadedResult['fileKey'], $UploadedResult['sourceFileName'], $UploadedResult['fileName'], $UploadedResult['path'], $UploadedResult['size'], $UploadedResult['extension'], $OwnerId, $ACL, false, $BelongsId, $BelongsType, $UploadedResult['width'], $UploadedResult['height']);
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
   * @return ReturnResult{boolean} 是否已删除，true=删除完成，false=删除失败
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
