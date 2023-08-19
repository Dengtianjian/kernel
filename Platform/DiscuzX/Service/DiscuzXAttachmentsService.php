<?php

namespace kernel\Platform\DiscuzX\Service;

use kernel\Foundation\File;
use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Model\DiscuzXAttachmentsModel;
use kernel\Service\AttachmentsService;
use kernel\Platform\DiscuzX\Controller\Attachments as Attachments;
use kernel\Platform\DiscuzX\Model\DiscuzXAttachmentKeysModel;

class DiscuzXAttachmentsService extends AttachmentsService
{
  static function getDownloadURL($attachmentId, $withKey = false, $userId = null, $periodSeconds = 300, $preview = true, $width = null, $height = null, $ratio = null)
  {
    $QueryStrings = [
      "id" => F_APP_ID,
      "uri" => "attachments/$attachmentId/download"
    ];
    if ($withKey) {
      $QueryStrings['key'] = self::getAccessKey($attachmentId, $userId, $periodSeconds, $preview, true, $width, $height, $ratio);
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
    return F_BASE_URL . "/plugin.php?" . http_build_query($QueryStrings);
  }
  static function getPreviewURL($attachmentId, $withKey = false, $userId = null, $periodSeconds = 300, $download = true, $width = null, $height = null, $ratio = null)
  {
    $QueryStrings = [
      "id" => F_APP_ID,
      "uri" => "attachments/$attachmentId/preview"
    ];
    if ($withKey) {
      $QueryStrings['key'] = self::getAccessKey($attachmentId, $userId, $periodSeconds, true, $download, $width, $height, $ratio);
    }
    if ($width) {
      $QueryStrings['w'] = $width;
    }
    if ($height) {
      $QueryStrings['h'] = $height;
    }
    if ($ratio) {
      $QueryStrings['r'] = $ratio;
    }
    return F_BASE_URL . "/plugin.php?" . http_build_query($QueryStrings);
  }
  static function getAccessKey($attachId, $userId = null, $periodSeconds = 300, $preview = true, $download = true, $width = 0, $height = 0, $ratio = null)
  {
    $ExpirationTime = time() + $periodSeconds;
    $key = self::generateAccessKey($attachId, $userId, $periodSeconds, $ExpirationTime, $preview, $download, $width, $height, $ratio);
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

    $SaveFilePath = File::genPath(F_DISCUZX_DATA_PLUGIN, $savePath);
    if (!is_dir($SaveFilePath)) {
      File::mkdir($SaveFilePath);
    }

    $saveFileResult = File::upload($file, $SaveFilePath);
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
      $attachmentSavePath = File::genPath(F_DISCUZX_DATA_PLUGIN, $attachment['filePath'], $attachment['fileName']);
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
        $attachmentSavePath = File::genPath(F_DISCUZX_DATA_PLUGIN, $item['filePath'], $item['fileName']);
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
  static function useService()
  {
    //* 附件路由注册
    Router::post("attachments", Attachments\DiscuzXUploadAttachmentController::class);
    Router::get("attachments/{attachId:\w+}", Attachments\DiscuzXGetAttachmentController::class);
    Router::delete("attachments/{attachId:\w+}", Attachments\DiscuzXDeleteAttachmentController::class);
    Router::get("attachments/{attachId:\w+}/download", Attachments\DiscuzXDownloadAttachmentController::class);
    Router::get("attachments/{attachId:\w+}/preview", Attachments\DiscuzXPreviewAttachmentController::class);
  }
}
