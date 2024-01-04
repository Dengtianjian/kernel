<?php

namespace kernel\Platform\DiscuzX\Service\File;

use kernel\Foundation\File\FileHelper;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Files\FileStorage as DiscuzXFileStorageNamespace;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;
use kernel\Service\OSS\OSSService;

class DiscuzXOSSService extends OSSService
{
  static function useService($OSSPlatform = "QCloudCos", $SecretId = null, $SecretKey = null, $Region = null, $Bucket = null)
  {
    parent::useService($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket);

    Router::get("fileStorage/oss/upload/auth", DiscuzXFileStorageNamespace\DiscuzXFileRemoteStorageOSSGetUploadAuthController::class);
  }
  static function addFile($FileKey, $SourceFileName, $FileName, $FilePath, $FileSize, $OwnerId = null, $ACL = DiscuzXFileStorage::PRIVATE, $extension = null, $Width = null, $Height = null, $BelongsId = null, $BelongsType = null)
  {
    if (!$extension) {
      $extension = pathinfo($SourceFileName, PATHINFO_EXTENSION);
    }

    return DiscuzXFilesModel::singleton()->add($FileKey, $SourceFileName, $FileName, $FilePath, $FileSize, $extension, $OwnerId, $ACL, true, $BelongsId, $BelongsType, $Width, $Height);
  }
  static function deleteFile($FileKey, $Signature = null, $SignatureKey = null, $CurrentAuthId = null, $RawURLParams = [], $RawHeaders = [], $HTTPMethod = "delete")
  {
    $R = new ReturnResult(true);

    $FS = new DiscuzXFilesModel();
    $File = $FS->item($FileKey);
    if (!$File) {
      return $R->error(404, 404001, "文件不存在", [], false);
    }
    $DeletedResult = parent::deleteFile($FileKey);
    if ($DeletedResult->error) return $DeletedResult;

    self::$RemoteStorageInstance->deleteObject($FileKey);

    return $DeletedResult;
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

    $width = $File['width'];
    $height = $File['height'];
    $size = $File['fileSize'];
    if ($File['remote']) {
      if (!$width || !$height) {
        $ImageInfo = self::$RemoteStorageInstance->getImageInfo($FileKey);
        if ($ImageInfo === false) {
          return $R->error(500, 500, "获取远程文件信息失败", [], $ImageInfo);
        }
        if (!is_null($ImageInfo)) {
          $width = (int)$ImageInfo['width'];
          $height = (int)$ImageInfo['height'];
          $size = (float)$ImageInfo['size'];

          DiscuzXFilesModel::singleton()->update([
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
          $width = (int)$ImageInfo[0];
          $height = (int)$ImageInfo[1];
          $size = (float)filesize($FilePath);

          DiscuzXFilesModel::singleton()->update([
            "width" => $width,
            "height" => $height,
            "fileSize" => $size
          ]);
        }
      }
    }

    return $R->success([
      "fileKey" => $FileKey,
      "path" => $File['filePath'],
      "fileName" => $File['fileName'],
      "extension" => $File['extension'],
      "size" => $size,
      "fullPath" => $FilePath,
      "relativePath" => FileHelper::optimizedPath(dirname($FileKey)),
      "ownerId" => $File['ownerId'],
      "width" => $width,
      "height" => $height,
      'acl' => $File['acl'],
      "remote" => $File['remote'],
      "createdAt" => $File['createdAt'],
      "updatedAt" => $File['updatedAt']
    ]);
  }
}
