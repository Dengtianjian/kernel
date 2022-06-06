<?php

namespace kernel\Service;

use kernel\Foundation\Database\PDO\DB;
use kernel\Foundation\Date;
use kernel\Foundation\File;
use kernel\Model\AttachmentModel;

class AttachmentService
{
  private static function genFileId($attachmentId, string $savePath, string $fileName): string
  {
    $savePath = substr($savePath, stripos($savePath, "/") + 1);
    return "attachment:" . Date::milliseconds() . "." . $attachmentId . "/" . $savePath . "/" . $fileName;
  }
  static function initTable(string $extraFieldsSql = "", array $extraFields = [])
  {
    $sql = <<<SQL
-- ----------------------------
-- Table structure for attachments
-- ----------------------------
DROP TABLE IF EXISTS `attachments`;
CREATE TABLE `attachments`  (
  `id` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'id',
  `path` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '物理相对地址',
  `saveFileName` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '保存在服务器的文件名称',
  `fileId` varchar(160) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '文件Id',
  `userId` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户Id',
  $extraFieldsSql
  `organizationId` varchar(26) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '组织Id',
  `fileName` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL COMMENT '源文件Id',
  `fileSize` double NULL DEFAULT NULL COMMENT '文件大小',
  `remote` tinyint(1) NULL DEFAULT NULL COMMENT '是否远程',
  `used` enum('0','1') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '1' COMMENT '附件是否被使用了',
  `remoteId` varchar(260) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '远程附件Id',
  `createdAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '创建时间',
  `updatedAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '更新时间',
  `deletedAt` varchar(22) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`, `fileId`, `userId`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;
SQL;
    DB::query($sql);
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
  public static function addRecord(string $saveDir, string $sourceFileName, string $relativePath, string $saveFileName, float|int $fileSize, string|int $used = 0, array $extraFields = [])
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
      ...$extraFields,
      "used" => (string)$used,
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
  public static function upload(File $file, string $realSaveDir = "Data/Attachments", bool $baseProject = true, string|null $saveDir = null, string|int $used = 0)
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
    return self::addRecord($realSaveDir, $saveFileResult['sourceFileName'], $saveDir, $saveFileResult['saveFileName'], $saveFileResult['size'], $used);
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
      $attachmentSavePath = File::genPath(F_APP_ROOT, $attachment['path'], $attachment['saveFileName']);
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
