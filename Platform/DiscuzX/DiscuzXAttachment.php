<?php

namespace kernel\Platform\DiscuzX;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use forum_upload;
use kernel\Foundation\Config;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\File;
use kernel\Foundation\ReturnResult;
use kernel\Foundation\Router;
use kernel\Foundation\Store;
use kernel\Platform\DiscuzX\Controller\Attachments as AttachmentsNamespace;
use kernel\Platform\DiscuzX\Foundation\Database\DiscuzXModel;

class DiscuzXAttachment
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
      $savePath = File::genPath("data", "plugindata", F_APP_ID, "attachments", $saveDir);
      if (!is_dir($savePath)) {
        mkdir($savePath, 0777, true);
      }
    }
    return new ReturnResult(File::upload($files, $savePath));
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

    $R->addData($aid, true);
    return $R;
  }
  /**
   * aidcode解码
   *
   * @param string $code 附件码
   * @return array
   */
  public static function aidDecode($code)
  {
    $code = base64_decode(rawurldecode($code));
    list($aid, $hash, $timestamp, $uid, $tid, $savePath) = explode("|", $code);
    return [
      "aid" => $aid,
      "hash" => $hash,
      "timestamp" => $timestamp,
      "uid" => $uid,
      "tid" => $tid,
      "savePath" => $savePath,
    ];
  }
  /**
   * aidcode编码
   *
   * @param string $code 附件码
   * @return array
   */
  public static function aidEncode($aid, $dir = "plugindata", $type = 0, $tid = 0)
  {
    global $_G;
    $s = !$type ? $aid . '|' . substr(md5($aid . md5($_G['config']['security']['authkey']) . TIMESTAMP . $_G['uid']), 0, 8) . '|' . TIMESTAMP . '|' . $_G['uid'] . '|' . $tid : $aid . '|' . md5($aid . md5($_G['config']['security']['authkey']) . TIMESTAMP) . '|' . TIMESTAMP;
    $s .= "|" . File::genPath($dir, F_APP_ID, "attachments");
    return rawurlencode(base64_encode($s));
  }
  /**
   * 根据附件ID获取附件信息
   *
   * @param integer $AttachmentId 附件ID
   * @return ReturnResult
   */
  public static function getAttachment($AttachmentId, $thumbWidth = 140, $thumbHeight = 140)
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
      $attachment['thumbURL'] = getforumimg($AttachmentId, 0, $thumbWidth, $thumbHeight, fileext($attachment['filename']));
    }

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
    Router::post("attachment", AttachmentsNamespace\UploadAttachmentController::class);
    Router::same("attachment/{fileId:\w+}", function () {
      Router::get(AttachmentsNamespace\GetAttachmentController::class);
      Router::delete(AttachmentsNamespace\DeleteAttachmentController::class);
    });
  }
}
