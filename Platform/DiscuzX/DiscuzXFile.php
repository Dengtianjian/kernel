<?php

namespace kernel\Platform\DiscuzX;

use kernel\Foundation\File;
use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Foundation\ReturnResult;
use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Files as FilesNamespace;

class DiscuzXFile
{
  /**
   * 生成文件ID
   *
   * @param string $fileName 文件名称，包含扩展名
   * @param string $savePath 保存的目录，相对于data\plugindata\{F_APP_ID}下，默认是files，也就是data\plugindata\{F_APP_ID}\files
   * @return string 文件ID
   */
  private static function genFileId($fileName, $savePath)
  {
    return base64_encode(rawurlencode(uniqid("file:") . "|" . $savePath . "|" . $fileName));
  }
  /**
   * 注册路由
   *
   * @return void
   */
  static function registerRoute()
  {
    Router::post("files", FilesNamespace\UploadFilesController::class);
    Router::delete("files/{fileId:\w+}", FilesNamespace\DeleteFileController::class);
    Router::get("files/{fileId:\w+}", FilesNamespace\AccessFileController::class);
  }
  /**
   * 保存文件
   *
   * @param \File $file 被保存的文件
   * @param string $saveDir 保存到的目录，相对于data\plugindata\{F_APP_ID}下，默认是files，也就是data\plugindata\{F_APP_ID}\files
   * @return ReturnResult accessPath就是可以直接通过URL访问的路径，fileId是base46编码后的文件数据
   */
  static function save($file, $saveDir = "files")
  {
    $saveBasePath = File::genPath(F_DISCUZX_DATA_PLUGIN, $saveDir);
    $file = File::upload($file, $saveBasePath);

    $file['relativePath'] = str_replace(F_DISCUZX_DATA, "", $file['relativePath']);
    if ($file['relativePath'][0] === "\\") {
      $file['relativePath'] = substr($file['relativePath'], 1);
    }
    if ($file['relativePath'][1] === "\\") {
      $file['relativePath'] = substr($file['relativePath'], 2);
    }

    $accessPath = File::genPath("data", $file['relativePath'], $file['saveFileName']);

    $file['accessPath'] = $accessPath;
    $file['fileId'] = self::genFileId($file['saveFileName'], $saveDir);
    return new ReturnResult($file);
  }
  /**
   * 删除文件
   *
   * @param string $fileId 文件ID，是通过save方法保存成功后返回的参数
   * @return ReturnResult
   */
  static function deleteFile($fileId)
  {
    $decodeData = self::decodeFileId($fileId);
    if ($decodeData->error) return $decodeData;
    $decodeData = $decodeData->getData();
    if (!file_exists($decodeData['filePath'])) {
      return new ResponseError(404, 404, "文件不存在或已删除");
    }
    $res = unlink($decodeData['filePath']);
    if (!$res) {
      return new ResponseError(500, 500, "文件删除失败");
    }
    return new ReturnResult(true);
  }
  /**
   * 解码文件ID
   *
   * @param string $fileId 文件ID
   * @return ReturnResult
   */
  static function decodeFileId($fileId)
  {
    list($tag, $fileId) = explode(":", rawurldecode(base64_decode($fileId)));

    if ($tag !== "file") {
      return new ResponseError(400, 400, "文件ID错误");
    }
    list($uniqueId, $saveDir, $fileName) = explode("|", $fileId);
    $filePath = File::genPath(F_DISCUZX_DATA_PLUGIN, $saveDir, $fileName);
    return new ReturnResult([
      "uniqueId" => $uniqueId,
      "saveDir" => $saveDir,
      "fileName" => $fileName,
      "filePath" => $filePath
    ]);
  }
}
