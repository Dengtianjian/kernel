<?php

namespace kernel\Platform\DiscuzX\Service;

use kernel\Foundation\HTTP\URL;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Platform\DiscuzX\Controller\Files as DiscuzXFilesNamespace;
use kernel\Platform\DiscuzX\Foundation\DiscuzXFileStorage;
use kernel\Service\FileStorageService;

class DiscuzXFileStorageService extends FileStorageService
{
  static function init()
  {
    $TableName = F_APP_ID . "_files";
    $SQL = <<<EOT
DROP TABLE IF EXISTS `{$TableName}`;
CREATE TABLE `{$TableName}`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '附件数字ID',
  `key` text NOT NULL COMMENT '文件名',
  `remote` tinyint(4) NOT NULL DEFAULT 0 COMMENT '远程附件',
  `belongsId` varchar(34) NULL DEFAULT NULL COMMENT '所属ID',
  `belongsType` varchar(32) NULL DEFAULT NULL COMMENT '所属ID类型',
  `authId` varchar(32) NOT NULL DEFAULT '0' COMMENT '授权ID',
  `sourceFileName` varchar(255) NOT NULL COMMENT '原本的文件名称',
  `fileName` varchar(255) NOT NULL COMMENT '保存后的文件名称',
  `fileSize` double NOT NULL COMMENT '文件尺寸',
  `filePath` text NOT NULL COMMENT '保存的文件路径',
  `width` double NULL DEFAULT 0 COMMENT '宽度（媒体文件才有该值）',
  `height` double NULL DEFAULT 0 COMMENT '高度（媒体文件才有该值）',
  `extension` varchar(30) NOT NULL COMMENT '文件扩展名',
  `createdAt` varchar(12) NOT NULL COMMENT '创建时间',
  `updatedAt` varchar(12) NOT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `userId`(`authId`) USING BTREE COMMENT '用户ID'
) COMMENT = '文件';
EOT;
    \DB::query($SQL);
  }
  static function useService()
  {
    Router::post("files", DiscuzXFilesNamespace\DiscuzXUploadFileController::class);
    Router::delete("files/{fileId:.+?}", DiscuzXFilesNamespace\DiscuzXDeleteFileController::class);
    Router::get("files/{fileId:.+?}/preview", DiscuzXFilesNamespace\DiscuzXAccessFileController::class);
    Router::get("files/{fileId:.+?}/download", DiscuzXFilesNamespace\DiscuzXDownloadFileController::class);
    Router::get("files/{fileId:.+?}", DiscuzXFilesNamespace\DiscuzXGetFileController::class);
  }
  static function getAccessURL($FileKey, $URLParams = [], $SignatureKey = NULL, $Expires = 600, $AuthId = null, $HTTPMethod = "get", $ACL = DiscuzXFileStorage::PRIVATE)
  {
    $accessURL = "";
    $R = new ReturnResult($accessURL);

    if ($SignatureKey) {
      $FileKeyInfo = pathinfo($FileKey);
      $accessURL = DiscuzXFileStorage::generateAccessURL($FileKeyInfo['dirname'], $FileKeyInfo['basename'], $SignatureKey, $Expires, $URLParams, $AuthId, $HTTPMethod, $ACL);
    } else {
      $U = new URL(F_BASE_URL);
      $U->pathName = URL::combinedPathName("files", $FileKey);
      foreach ($URLParams as $key => $value) {
        $U->queryParam($value, $key);
      }
      return $U->toString();
    }

    return $R->success($accessURL);
  }
}
