<?php

namespace kernel\Platform\DiscuzX\Service;

use kernel\Foundation\File;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Model\DiscuzXAttachmentsModel;
use kernel\Service\AttachmentsService;
use kernel\Platform\DiscuzX\Controller\Attachments as Attachments;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;
use kernel\Platform\DiscuzX\Model\DiscuzXAttachmentKeysModel;

class DiscuzXAttachmentsService extends AttachmentsService
{
  static private function getURLQueryString($uri, $attachmentId, $withKey = false, $userId = null, $periodSeconds = 300, $download = true, $preview = false, $width = null, $height = null, $ratio = null, $outputExtension = null, $quality = null)
  {
    $QueryStrings = [
      "id" => F_APP_ID,
      "uri" => $uri
    ];
    if ($withKey) {
      $QueryStrings['key'] = self::getAccessKey($attachmentId, $userId, $periodSeconds, $preview, $download, $width, $height, $ratio, $outputExtension, $quality);
    }
    if ($width) {
      $QueryStrings['w'] = $width;
    }
    if ($height) {
      $QueryStrings['w'] = $height;
    }
    if ($ratio) {
      $QueryStrings['r'] = $ratio;
    }
    if ($outputExtension) {
      $QueryStrings['ext'] = $outputExtension;
    }
    if (!is_null($quality)) {
      $QueryStrings['q'] = $quality;
    }

    return http_build_query($QueryStrings);
  }
  static function getDownloadURL($attachmentId, $withKey = false, $userId = null, $periodSeconds = 300, $preview = true, $width = null, $height = null, $ratio = null, $outputExtension = null, $quality = null)
  {
    return F_BASE_URL . "/plugin.php?" . self::getURLQueryString("attachments/$attachmentId/download", $attachmentId, $withKey, $userId, $periodSeconds, true, false, $width, $height, $ratio, $outputExtension, $quality);
  }
  static function getPreviewURL($attachmentId, $withKey = false, $userId = null, $periodSeconds = 300, $download = true, $width = null, $height = null, $ratio = null, $baseURL = F_BASE_URL, $outputExtension = null, $quality = null)
  {
    return F_BASE_URL . "/plugin.php?" . self::getURLQueryString("attachments/$attachmentId/preview", $attachmentId, $withKey, $userId, $periodSeconds, false, true, $width, $height, $ratio, $outputExtension, $quality);
  }
  static function getAccessKey($attachId, $userId = null, $periodSeconds = 300, $preview = true, $download = true, $width = 0, $height = 0, $ratio = null, $outputExtension = null, $quality = null)
  {
    $ExpirationTime = time() + $periodSeconds;
    $key = self::generateAccessKey($attachId, $userId, $periodSeconds, $ExpirationTime, $preview, $download, $width, $height, $ratio, $outputExtension, $quality);
    if ($key) {
      $KeyId = DiscuzXAttachmentKeysModel::singleton()->add($attachId, $key, $userId, $download, $preview, $ExpirationTime);
      if ($KeyId) {
        return $key;
      }
    }
    return false;
  }
  public static function upload($file, $savePath = "attachments")
  {
    if (!$file) return 0;

    $SaveFilePath = FileHelper::combinedFilePath(F_DISCUZX_DATA_PLUGIN, $savePath);
    if (!is_dir($SaveFilePath)) {
      mkdir($SaveFilePath);
    }

    $saveFileResult = DiscuzXFileStorage::upload($file, $SaveFilePath);
    if (!$saveFileResult) return $saveFileResult;
    if (!$savePath) {
      $savePath = $saveFileResult['relativePath'];
    }
    $attachId = self::genAttachId($savePath, $file['name']);

    DiscuzXAttachmentsModel::singleton()->add($attachId, getglobal("uid"), $saveFileResult['sourceFileName'], $saveFileResult['saveFileName'], $saveFileResult['size'], $savePath, $saveFileResult['width'], $saveFileResult['height'], $saveFileResult['extension']);

    return $attachId;
  }
  static function deleteByAttachmentId($attachmentId)
  {
    $AM = new DiscuzXAttachmentsModel();
    $attachment = self::getAttachment($attachmentId);
    if ($attachment) {
      $attachmentSavePath = FileHelper::combinedFilePath(F_DISCUZX_DATA_PLUGIN, $attachment['filePath'], $attachment['fileName']);
      unlink($attachmentSavePath);
      $AM->deleteItem(null, $attachmentId);
    }
    return true;
  }
  static function deleteBelongsSameTypeId($belongsId, $belongsType)
  {
    $Attachs = DiscuzXAttachmentsModel::singleton()->field("filePath", "fileName")->list(null, null, null, $belongsId, $belongsType);
    if (count($Attachs)) {
      foreach ($Attachs as $item) {
        $attachmentSavePath = FileHelper::combinedFilePath(F_DISCUZX_DATA_PLUGIN, $item['filePath'], $item['fileName']);
        unlink($attachmentSavePath);
      }
      DiscuzXAttachmentsModel::singleton()->deleteBelongsSameIdType($belongsId, $belongsType);
    }

    return true;
  }
  static function getAttachment($attachmentId)
  {
    $AM = new DiscuzXAttachmentsModel();
    $attachment = $AM->where("attachId", $attachmentId)->getOne();
    if (!$attachment) return null;
    return $attachment;
  }
  /**
   * 使用服务
   *
   * @param boolean $RegisterRoute 是否注册路由
   * @return void
   */
  static function useService($RegisterRoute = true)
  {
    if ($RegisterRoute) {
      //* 附件路由注册
      Router::post("attachments", Attachments\DiscuzXUploadAttachmentController::class);
      Router::get("attachments/{attachId:\w+}", Attachments\DiscuzXGetAttachmentController::class);
      Router::delete("attachments/{attachId:\w+}", Attachments\DiscuzXDeleteAttachmentController::class);
      Router::get("attachments/{attachId:\w+}/download", Attachments\DiscuzXDownloadAttachmentController::class);
      Router::get("attachments/{attachId:\w+}/preview", Attachments\DiscuzXPreviewAttachmentController::class);
    }
  }
}
