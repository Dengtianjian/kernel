<?php

namespace kernel\Service;

use kernel\Controller\Main\Files as FilesNamespace;
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
    Router::post("files", FilesNamespace\UploadFilesController::class);
    Router::delete("files/{fileId:.+?}", FilesNamespace\DeleteFileController::class);
    Router::get("files/{fileId:.+?}", FilesNamespace\AccessFileController::class);
  }
  /**
   * 上传文件
   *
   * @param array|string $Files 文件或者多个文件数组
   * @param string $SavePath 保存的完整路径
   * @param string $saveFileName 保存的文件名称，不含扩展名
   * @return ReturnResult<array|false> 上传失败会返回false，成功返回文件信息
   */
  static function upload($Files, $SavePath, $saveFileName = null)
  {
    $R = new ReturnResult(true);
    if ($saveFileName) {
      $saveFileName = pathinfo($saveFileName)['filename'];
    }
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
   * @param string $SignatureKey 签名秘钥，如果传入该值，就会进行签名校验，需要传入后面的所有参数
   * @param string $Signature 签名
   * @param array $RawURLParams URL请求参数
   * @param array $RawHeaders 请求头
   * @param string $AuthId 授权ID
   * @param string $HTTPMethod 请求方式
   * @return ReturnResult<boolean> 是否已删除，true=删除完成，false=删除失败
   */
  static function deleteFile($FileKey, $SignatureKey = null, $Signature = null, $RawURLParams = [], $RawHeaders = [], $AuthId = null, $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);
    if ($SignatureKey) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      if (!FileStorage::verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders, $AuthId, $HTTPMethod)) return $R->error(403, 403, "签名错误");
    }

    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_ROOT, $FileKey));
    if (file_exists($FilePath)) {
      unlink($FilePath);
    }

    return $R;
  }
  /**
   * 删除文件
   *
   * @param string $FileKey 文件名
   * @param string $SignatureKey 签名秘钥，如果传入该值，就会进行签名校验，需要传入后面的所有参数
   * @param string $Signature 签名
   * @param array $RawURLParams URL请求参数
   * @param array $RawHeaders 请求头
   * @param string $AuthId 授权ID
   * @param string $HTTPMethod 请求方式
   * @return ReturnResult{boolean|array} 是否已删除，true=删除完成，false=删除失败
   */
  static function getFileInfo($FileKey, $SignatureKey = null, $Signature = null, $RawURLParams = [], $RawHeaders = [], $AuthId = null, $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);
    if ($SignatureKey) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      if (!FileStorage::verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders, $AuthId, $HTTPMethod)) return $R->error(403, 403, "签名错误");
    }

    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_ROOT, $FileKey));
    if (!file_exists($FilePath)) {
      return $R->error(404, 404, "文件不存在");
    }

    $FileInfo = pathinfo($FilePath);
    $File = [
      "path" => $FileInfo['dirname'],
      "fileName" => $FileInfo['filename'],
      "extension" => $FileInfo['extension'],
      "size" => filesize($FilePath),
      "fullPath" => $FilePath,
      "relativePath" => FileHelper::optimizedPath(dirname($FileKey)),
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
    $AccessAuth = FileStorage::generateAccessURL($FileKeyInfo['dirname'], $FileKeyInfo['basename'], $SignatureKey, $Expires, $URLParams, $AuthId, $HTTPMethod, $ACL);

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
