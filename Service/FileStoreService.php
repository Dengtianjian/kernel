<?php

namespace kernel\Service;

use kernel\Foundation\File;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Foundation\Service;
use kernel\Controller\Main\Files as FilesNamespace;

class FileStoreService extends Service
{
  /**
   * 生成文件ID
   *
   * @param string $fileName 文件名称，包含扩展名
   * @param string $savePath 保存的目录，相对于根目录下的Data文件夹
   * @param boolean $user 可查看文件的用户ID
   * @param boolean $auth 是否权限校验
   * @param boolean $remote 是否是远程附件
   * @return string 文件ID
   */
  static function genFileId($fileName, $savePath, $userId = 0, $auth = true, $remote = false)
  {
    return rawurlencode(base64_encode(implode("|", [
      uniqid("file:"),
      $savePath,
      $fileName,
      $remote,
      $userId,
      intval($auth)
    ])));
  }
  /**
   * 解码文件ID
   *
   * @param string $fileId 文件ID
   * @return ReturnResult
   */
  static function decodeFileId($fileId)
  {
    if (strpos($fileId, ".") !== false) {
      $fileId = explode(".", $fileId)[0];
    }
    list($tag, $fileId) = explode(":", base64_decode(rawurldecode($fileId)));

    if ($tag !== "file") {
      return new ReturnResult(false, 400, 400, "文件ID错误");
    }
    list($uniqueId, $saveDir, $fileName, $remote, $userId, $auth) = explode("|", $fileId);
    $filePath = File::genPath(F_APP_DATA, $saveDir, $fileName);
    return new ReturnResult([
      "uniqueId" => $uniqueId,
      "saveDir" => $saveDir,
      "fileName" => $fileName,
      "filePath" => $filePath,
      "remote" => $remote,
      "userId" => $userId,
      "auth" => $auth,
    ]);
  }
  /**
   * 注册路由
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
   * 保存文件
   *
   * @param \File $file 被保存的文件
   * @param string $saveDir 保存到的目录，相对于根目录下的Data文件夹，默认是files，也就是{F_APP_ID}/Data/files
   * @param string $fileName 文件名称，不用带扩展名
   * @param boolean $auth 是否校验权限
   * @return ReturnResult accessPath就是可以直接通过URL访问的路径，fileId是base46编码后的文件数据
   */
  static function save($file, $saveDir = "files", $fileName = null, $auth = true)
  {
    $saveBasePath = File::genPath(F_APP_DATA, $saveDir);
    $file = File::upload($file, $saveBasePath, $fileName);

    $file['relativePath'] = str_replace(F_APP_DATA, "", $file['relativePath']);
    if ($file['relativePath'][0] === "\\") {
      $file['relativePath'] = substr($file['relativePath'], 1);
    }
    if ($file['relativePath'][1] === "\\") {
      $file['relativePath'] = substr($file['relativePath'], 2);
    }

    $accessPath = File::genPath($file['relativePath'], $file['saveFileName']);

    $file['accessPath'] = $accessPath;
    $file['fileId'] = self::genFileId($file['saveFileName'], $saveDir, false, $auth);
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
    $R = new ReturnResult(null);
    $decodeData = self::decodeFileId($fileId);
    if ($decodeData->error) return $decodeData;
    $decodeData = $decodeData->getData();
    if (!file_exists($decodeData['filePath'])) {
      return $R->error(404, 404, "文件不存在或已删除");
    }

    $res = unlink($decodeData['filePath']);
    if (!$res) {
      return $R->error(500, 500, "文件删除失败");
    }
    return new ReturnResult(true);
  }
  /**
   * 获取文件访问链接地址
   *
   * @param string $FileId 文件ID
   * @return string
   */
  static function getAccessURL($FileId)
  {
    return F_BASE_URL . "/files/$FileId";
  }
}
