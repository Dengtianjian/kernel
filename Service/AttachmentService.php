<?php

namespace kernel\Service;

use kernel\Foundation\File;
use kernel\Model\AttachmentModel;

class AttachmentService
{
  private static function genFileId($attachmentId, string $savePath, string $fileName): string
  {
    $savePath = substr($savePath, stripos($savePath, "/") + 1);
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
  /**
   * 上传文件并且写入到Attachment表
   *
   * @param File $file 文件
   * @param string $realSaveDir 真实保存的地址，该文件会被存放在这个路径
   * @param boolean $baseProject 保存的地址是否基于项目路径
   * @param string|null $saveDir 存入附件表的的路径。场景：存放附件的路径可能不在当前项目的文件夹下，而是动态的，那存进去的用相对地址，获取的时候用配置的路径再拼上数据表的路径即可
   * @return Array 附件数据
   */
  public static function upload($file, $realSaveDir = "Data/Attachments", $baseProject = true, $saveDir = null)
  {
    if ($baseProject) {
      if (!is_dir($realSaveDir)) {
        File::mkdir(explode("/", $realSaveDir), F_APP_ROOT);
      }
      $realSaveDir = F_APP_ROOT . "/" . $realSaveDir;
    }

    $saveFileResult = File::upload($file, $realSaveDir);
    if (!$saveDir) {
      $saveDir = $saveFileResult['relativePath'];
    }
    return self::addRecord($realSaveDir, $saveFileResult['sourceFileName'], $saveDir, $saveFileResult['saveFileName'], $saveFileResult['size']);
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
  static function getAttachment(string $attachmentId)
  {
    $AM = new AttachmentModel();
    $attachment = $AM->where("id", $attachmentId)->getOne();
    if (!$attachment) return null;
    return $attachment;
  }
  static function getUrlById(string $attachmentId)
  {
    $attachment = self::getAttachment($attachmentId);
    if (!$attachment) return false;
    return F_BASE_URL . "/downloadAttachment?fileId=" . urlencode($attachment['fileId']);
  }
  static function updateAttachmentUseState(?string $fileId = null, ?string $attachmentId = null, string|int $state = 0): bool
  {
    $AM = new AttachmentModel();
    $query = [];
    if ($fileId) {
      $query['fileId'] = $fileId;
    }
    if ($attachmentId) {
      $query['id'] = $attachmentId;
    }

    return $AM->where($query)->update([
      "used" => (string)$state
    ]);
  }
}
