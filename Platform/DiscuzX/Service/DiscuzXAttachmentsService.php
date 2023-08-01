<?php

namespace kernel\Platform\DiscuzX\Service;

use kernel\Foundation\File;
use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Model\DiscuzXAttachmentsModel;
use kernel\Service\AttachmentsService;
use kernel\Platform\DiscuzX\Controller\Attachments as Attachments;

class DiscuzXAttachmentsService extends AttachmentsService
{
  static function getDownloadURL($attachmentId)
  {
    return F_BASE_URL . "/plugin.php?id=" . F_APP_ID . "&uri=attachments/" . $attachmentId . "/download";
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

    DiscuzXAttachmentsModel::singleton()->add($attachId, 0, $saveFileResult['sourceFileName'], $saveFileResult['saveFileName'], $saveFileResult['size'], $savePath, $saveFileResult['width'], $saveFileResult['height'], $saveFileResult['extension']);

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
  static function getAttachment($attachmentId)
  {
    $AM = new DiscuzXAttachmentsModel();
    $attachment = $AM->where("attachId", $attachmentId)->getOne();
    if (!$attachment) return null;
    return $attachment;
  }
  static function useService()
  {
    //* 附件
    Router::post("attachments", Attachments\DiscuzXUploadAttachmentController::class);
    Router::get("attachments/{attachId:\w+}", Attachments\DiscuzXGetAttachmentController::class);
    Router::delete("attachments/{attachId:\w+}", Attachments\DiscuzXDeleteAttachmentController::class);
    Router::get("attachments/{attachId:\w+}/download", Attachments\DiscuzXDownloadAttachmentController::class);
    Router::get("attachments/{attachId:\w+}/preview", Attachments\DiscuzXPreviewAttachmentController::class);
  }
}
