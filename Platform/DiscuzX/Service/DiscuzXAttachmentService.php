<?php

namespace kernel\Platform\DiscuzX\Service;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use forum_upload;
use kernel\Foundation\Config;
use kernel\Foundation\File;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\File\FileManager;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Router;
use kernel\Foundation\Service;
use kernel\Platform\DiscuzX\Controller\Attachment as AttachmentNamespace;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXModel;

class DiscuzXAttachmentService extends Service
{
  /**
   * 保存文件
   *
   * @param File|array $files 上传的文件或者上传的文件列表
   * @param string $saveDir 保存的路径，基于data/plugindata/{插件ID}/attachments目录
   * @return ReturnResult
   */
  public static function saveFile($files, $saveDir = "")
  {
    $savePath = Config::get("attachmentPath");
    if (!$savePath) {
      $savePath = FileHelper::combinedFilePath("data", "plugindata", F_APP_ID, "attachments", $saveDir);
      if (!is_dir($savePath)) {
        mkdir($savePath, 0777, true);
      }
    }
    return new ReturnResult(FileManager::upload($files, $savePath));
  }
  /**
   * 上传文件
   *
   * @param File $file 上传的文件
   * @return ReturnResult
   */
  public static function uploadFile($file)
  {
    global $_G;
    $R = new ReturnResult(null);
    $_GET['uid'] = $_G['uid'];
    $_GET['hash'] = md5(substr(md5($_G['config']['security']['authkey']), 8) . $_G['uid']);

    $_FILES['Filedata'] = $file;
    $FU = new forum_upload(true);
    if ($FU->statusid) {
      $errorMessage = lang("touch/template", "uploadstatusmsg" . $FU->statusid);
      $R->error(400, 400, $errorMessage, [
        "statusId" => $FU->statusid
      ]);
      return $R;
    }
    $aid = $FU->aid;
    $TableId = dintval(strval($aid)[strlen($aid) - 1]);
    $FU->attach['aid'] = $aid;
    $FU->attach['tableId'] = $TableId;

    include libfile("function/post");
    updateattach(0, intval("-" . $aid), 0, [
      $aid => $FU->attach
    ]);

    $R->addData($FU->attach, true);
    return $R;
  }
  /**
   * 根据附件ID获取附件信息
   *
   * @param integer $AttachmentId 附件ID
   * @return ReturnResult
   */
  public static function getAttachment($AttachmentId, $thumbWidth = null, $thumbHeight = null)
  {
    $AM = new DiscuzXModel("forum_attachment");
    $attachment = $AM->where("aid", $AttachmentId)->getOne();
    if (!$attachment) {
      return new ReturnResult(null, 404, 404001, "附件不存在");
    }
    $TableId = $attachment['tableid'];
    $SAM = new DiscuzXModel("forum_attachment_$TableId");
    $attachment = $SAM->where("aid", $AttachmentId)->getOne();
    if (!$attachment) {
      return new ReturnResult(null, 404, 404001, "附件不存在");
    }
    $attachment['downloadLink'] = "forum.php?mod=attachment&aid=" . aidencode($AttachmentId) . "&nothumb=yes";
    $attachment['thumbURL'] = null;
    if ($attachment['isimage']) {
      if (is_null($thumbWidth)) {
        $thumbWidth = $attachment['width'];
      }
      if (is_null($thumbHeight)) {
        $thumbHeight = $attachment['height'];
      }
      $attachment['thumbURL'] = getforumimg($AttachmentId, 0, $thumbWidth, $thumbHeight, fileext($attachment['filename']));
    }

    $attachment = [
      "aid" => $attachment['aid'],
      "fileName" => $attachment['filename'],
      "isImage" => $attachment['isimage'],
      "size" => $attachment['filesize'],
      "width" =>  $attachment['width'],
      "height" =>  $attachment['height'],
      "downloadLink" => $attachment['downloadLink'],
      "thumbURL" => $attachment['thumbURL']
    ];

    return new ReturnResult($attachment);
  }
  /**
   * 删除附件
   *
   * @param int|array $aids 附件ID|附件ID列表
   * @return bool
   */
  public static function deleteAttachment($aids)
  {
    if (!is_array($aids)) {
      $aids = [$aids];
    }
    \C::t('forum_attachment')->delete_by_id("aid", $aids);
    \C::t('forum_attachment_exif')->delete($aids);
    $tables = [];
    foreach ($aids as $aid) {
      $tableId = intval(strval($aid)[strlen($aid) - 1]);
      if (!isset($tables[$tableId])) {
        $tables[$tableId] = [];
      }
      array_push($tables[$tableId], $aid);
    }
    foreach ($tables as $tableId => $aids) {
      \C::t('forum_attachment_n')->delete_attachment($tableId, $aids);
    }
    return true;
  }
  /**
   * 注册附件相关路由
   *
   * @return void
   */
  public static function registerRoute()
  {
    Router::post("attachment", AttachmentNamespace\UploadAttachmentController::class);
    Router::same("attachment/{attach:\w+}", function () {
      Router::get(AttachmentNamespace\GetAttachmentController::class);
      Router::delete(AttachmentNamespace\DeleteAttachmentController::class);
    });
  }
}
