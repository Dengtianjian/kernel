<?php

namespace kernel\Service;

use kernel\Foundation\File;
use kernel\Foundation\GlobalVariables;
use kernel\Foundation\Output;
use kernel\Model\AttachmentModel;

class AttachmentService
{
  private static function genFileId($attachmentId, string $savePath, string $fileName): string
  {
    return "attachment:" . time() . "." . $attachmentId . "/" . $savePath . "/" . $fileName;
  }
  public static function getAttachmentInfo($fileId)
  {
    $AM = new AttachmentModel();
    $get = $AM->where([
      "fileId" => $fileId
    ]);
    if (is_array($fileId)) {
      return $get->getAll();
    } else {
      return $get->getOne();
    }
  }
  public static function addRecord($saveDir, $sourceFileName, $relativePath, $saveFileName, $fileSize)
  {
    $AM = new AttachmentModel();
    $attachmentId = $AM->genId();

    $attachmentFileId = self::genFileId($attachmentId, $saveDir, $saveFileName);
    $nowTime = time();
    $insertData = [
      "id" => $attachmentId,
      "path" => $relativePath,
      "saveFileName" => $saveFileName,
      "fileName" => $sourceFileName,
      "fileId" => $attachmentFileId,
      "fileSize" => $fileSize,
      "remote" => 0,
      "remoteId" => "",
      "createdAt" => $nowTime,
      "updatedAt" => $nowTime
    ];
    $AM->sql(false)->insert($insertData);
    return $insertData;
  }
  public static function upload($file, $saveDir = "Attachments")
  {
    if (!is_dir($saveDir)) {
      File::mkdir(explode("/", $saveDir), F_APP_ROOT);
    }
    $saveFileResult = File::upload($file, F_APP_ROOT . "/" . $saveDir);
    return self::addRecord($saveDir, $saveFileResult['sourceFileName'], $saveFileResult['relativePath'], $saveFileResult['saveFileName'], $saveFileResult['size']);
  }
  static function getUrl(string $attachmentFileId)
  {
    return F_BASE_URL . "/downloadAttachment?fileId=" . urlencode($attachmentFileId);
  }
  static function deleteByFileId(string $fileId)
  {
    $AM = new AttachmentModel();
    $attachment = self::getAttachmentInfo($fileId);
    if ($attachment) {
      $attachmentSavePath = F_APP_ROOT . $attachment['path'] . "/" . $attachment['saveFileName'];
      unlink($attachmentSavePath);
      $AM->deleteByFileId($fileId);
    }
    return true;
  }
}
