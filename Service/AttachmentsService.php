<?php

namespace kernel\Service;

use kernel\Foundation\File;
use kernel\Foundation\Router;
use kernel\Foundation\Service;
use kernel\Model\AttachmentsModel;

use kernel\Controller\Main\Attachments as Attachments;

class AttachmentsService extends Service
{
  /**
   * 生成附件ID
   *
   * @param string $savePath 保存的路径
   * @param string $fileName 文件名称（含扩展名）
   * @return string
   */
  protected static function genAttachId($savePath, $fileName)
  {
    $savePath = substr($savePath, stripos($savePath, "/") + 1);
    return md5($savePath . "/" . $fileName);
  }
  /**
   * 获取附件下载地址
   *
   * @param string $attachmentId 附件ID
   * @return string
   */
  static function getDownloadURL($attachmentId)
  {
    return F_BASE_URL . "/attachments/" . $attachmentId . "/download";
  }
  /**
   * 上传文件
   *
   * @param File $file 文件
   * @param string $savePath 基于项目根目录
   * @return Array 附件数据
   */
  public static function upload($file, $savePath = "Data/Attachments")
  {
    if (!$file) return 0;

    $SaveFilePath = File::genPath(F_APP_ROOT, $savePath);
    if (!is_dir($SaveFilePath)) {
      File::mkdir($SaveFilePath);
    }

    $saveFileResult = File::upload($file, $SaveFilePath);
    if (!$saveFileResult) return $saveFileResult;
    if (!$savePath) {
      $savePath = $saveFileResult['relativePath'];
    }
    $attachId = self::genAttachId($savePath, $file['name']);

    return AttachmentsModel::singleton()->add($attachId, 0, $saveFileResult['sourceFileName'], $saveFileResult['saveFileName'], $saveFileResult['size'], $savePath, $saveFileResult['width'], $saveFileResult['height'], $saveFileResult['extension']);
  }
  /**
   * 根据附件ID删除附件
   *
   * @param string $attachmentId 附件ID
   * @return boolean
   */
  static function deleteByAttachmentId($attachmentId)
  {
    $AM = new AttachmentsModel();
    $attachment = self::getAttachment($attachmentId);
    if ($attachment) {
      $attachmentSavePath = File::genPath(F_APP_ROOT, $attachment['filePath'], $attachment['fileName']);
      unlink($attachmentSavePath);
      $AM->deleteItem($attachmentId);
    }
    return true;
  }
  /**
   * 获取附件信息
   *
   * @param string $attachmentId 附件ID
   * @return array
   */
  static function getAttachment($attachmentId)
  {
    $AM = new AttachmentsModel();
    $attachment = $AM->where("attachId", $attachmentId)->getOne();
    if (!$attachment) return null;
    return $attachment;
  }
  static function useService()
  {
    //* 附件
    Router::post("attachments", Attachments\UploadAttachmentController::class);
    Router::get("attachments/{attachId:\w+}", Attachments\GetAttachmentController::class);
    Router::delete("attachments/{attachId:\w+}", Attachments\DeleteAttachmentController::class);
    Router::get("attachments/{attachId:\w+}/download", Attachments\DownloadAttachmentController::class);
    Router::get("attachments/{attachId:\w+}/thumbnail", Attachments\GetImageThumbnailViewController::class);
  }
}
