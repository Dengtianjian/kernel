<?php

namespace kernel\Service\File;

use kernel\Controller\Main\Files as FilesNamespace;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\Files;
use kernel\Foundation\File\FileStorage;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Foundation\Service;

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
   * @return ReturnResult{boolean} 是否已删除，true=删除完成，false=删除失败
   */
  static function deleteFile($FileKey)
  {
    $R = new ReturnResult(true);

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
   * @return ReturnResult<false|array{fileKey:string,sourceFileName:string,path:string,fileName:string,extension:string,size:int,fullPath:string,relativePath:string,width:int,height:int}> 文件信息
   */
  static function getFileInfo($FileKey)
  {
    $R = new ReturnResult(true);

    $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));
    if (!file_exists($FilePath)) {
      return $R->error(404, 404, "文件不存在", [], false);
    }

    $FileInfo = pathinfo($FilePath);
    $File = [
      "fileKey" => $FileKey,
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
   * 获取访问URL地址
   *
   * @param string $FileKey 文件名
   * @param array $URLParams 请求参数
   * @return ReturnResult{string} 访问的URL地址
   */
  static function getAccessURL($FileKey, $URLParams = [])
  {
    $R = new ReturnResult(null);

    $FileInfo = pathinfo($FileKey);
    $AccessURL = Files::generateAccessURL($FileInfo['dirname'], $FileInfo['filename'], $URLParams);


    return $R->success($AccessURL);
  }
}
