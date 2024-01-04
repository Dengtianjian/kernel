<?php

namespace kernel\Platform\DiscuzX\Service\File;

use kernel\Foundation\File\FileHelper;
use kernel\Foundation\HTTP\URL;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Files\FileStorage as DiscuzXFileStorageNamespace;
use kernel\Platform\DiscuzX\DiscuzXURL;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;
use kernel\Service\File\FileService;
use kernel\Service\File\FileStorageService;

class DiscuzXFileStorageService extends FileStorageService
{
  static function init()
  {
    DiscuzXFilesModel::singleton()->createTable();
  }
  static function useService()
  {
    Router::post("fileStorage/upload/auth", DiscuzXFileStorageNamespace\DiscuzXFileStorageGetUploadFileAuthController::class);
    Router::post("fileStorage/{fileId:.+?}", DiscuzXFileStorageNamespace\DiscuzXFileStorageUploadFileController::class);
    Router::delete("fileStorage/{fileId:.+?}", DiscuzXFileStorageNamespace\DiscuzXFileStorageDeleteFileController::class);

    Router::get("fileStorage/{fileId:.+?}/preview", DiscuzXFileStorageNamespace\DiscuzXFileStorageAccessFileController::class);
    Router::get("fileStorage/{fileId:.+?}/download", DiscuzXFileStorageNamespace\DiscuzXFileStorageDownloadFileController::class);

    Router::get("fileStorage/{fileId:.+?}", DiscuzXFileStorageNamespace\DiscuzXFileStorageGetFileController::class);
  }
  static function getAccessURL($FileKey, $URLParams = [], $SignatureKey = NULL, $Expires = 600, $HTTPMethod = "get")
  {
    $accessURL = "";
    $R = new ReturnResult($accessURL);

    if ($SignatureKey) {
      $accessURL = DiscuzXFileStorage::generateAccessURL($FileKey, $URLParams, $SignatureKey, $Expires, $HTTPMethod);
    } else {
      $U = new DiscuzXURL(F_BASE_URL);
      $U->pathName = DiscuzXURL::combinedPathName("files", $FileKey);
      foreach ($URLParams as $key => $value) {
        $U->queryParam($value, $key);
      }
      $accessURL = $U->toString();
    }

    return $R->success($accessURL);
  }
  static function upload($File, $FileKey, $OwnerId = null, $BelongsId = null, $BelongsType = null, $ACL = 'private')
  {
    $FileInfo = pathinfo($FileKey);
    $UploadedResult = FileService::upload($File, $FileInfo['dirname'], $FileInfo['basename']);
    if ($UploadedResult->error) return $UploadedResult;
    $UploadFileInfo = $UploadedResult->getData();

    $FS = new DiscuzXFilesModel();
    return $UploadedResult->success($FS->add($FileKey, $UploadFileInfo['sourceFileName'], $UploadFileInfo['fileName'], $UploadFileInfo['path'], $UploadFileInfo['size'], $UploadFileInfo['extension'], $OwnerId, $ACL, false, $BelongsId, $BelongsType, $UploadFileInfo['width'], $UploadFileInfo['height']));
  }
  static function getFileInfo($FileKey, $Signature = null, $SignatureKey = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult = DiscuzXFileStorage::verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders, $HTTPMethod);
      if ($verifyResult !== true)
        return $R->error(403, 403001, "签名错误", $verifyResult);
    }

    $File = DiscuzXFilesModel::singleton()->item($FileKey);
    if (!$File) {
      return $R->error(404, 404001, "文件不存在", [], false);
    }

    if ($File['acl'] === DiscuzXFileStorage::PRIVATE) {
      if ($File['ownerId'] && $File['ownerId'] !== $CurrentAuthId) {
        return $R->error(403, 403002, "无权访问", [], $File['acl']);
      }
    } else {
      if (!$Signature) {
        if (in_array($File['acl'], [
          DiscuzXFileStorage::AUTHENTICATED_READ,
          DiscuzXFileStorage::AUTHENTICATED_READ_WRITE
        ])) {
          return $R->error(403, 403003, "无权访问", [], $File['acl']);
        }
      }
    }

    if (!$File['remote']) {
      $FilePath = FileHelper::optimizedPath(FileHelper::combinedFilePath(F_APP_STORAGE, $FileKey));
      if (!file_exists($FilePath)) {
        return $R->error(404, 404002, "文件不存在", [], false);
      }
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
      "updatedAt" => $File['updatedAt'],
      "remote" => $File['remote']
    ]);
  }
  static function deleteFile($FileKey, $Signature = null, $SignatureKey = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "get")
  {
    $R = new ReturnResult(true);
    if ($Signature) {
      if (!array_key_exists("signature", $RawURLParams)) {
        $RawURLParams['signature'] = $Signature;
      }
      $verifyResult = DiscuzXFileStorage::verifyAccessAuth($SignatureKey, $FileKey, $RawURLParams, $RawHeaders, $HTTPMethod);
      if ($verifyResult !== true)
        return $R->error(403, 403001, "签名错误", $verifyResult);
    }

    $FS = new DiscuzXFilesModel();

    $File = $FS->item($FileKey);
    if (!$File) {
      return $R->error(404, 404001, "文件不存在", [], false);
    }

    if ($File['acl'] === DiscuzXFileStorage::PRIVATE) {
      if ($File['ownerId'] && $File['ownerId'] !== $CurrentAuthId) {
        return $R->error(403, 403002, "无权删除", [], $File['acl']);
      }
    } else {
      if ($File['acl'] !== DiscuzXFileStorage::PUBLIC_READ_WRITE && $File['acl'] !== DiscuzXFileStorage::AUTHENTICATED_READ_WRITE) {
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
