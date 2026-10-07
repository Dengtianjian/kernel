<?php

namespace kernel\Modules\DiscuzX\Service;
use kernel\Foundation\FileSystem\FileSystem;


use forum_upload;
use kernel\Foundation\App;
use kernel\Foundation\Config;
use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\Result;
use kernel\Foundation\Router\Route;
use kernel\Foundation\Router\RouteSame;
use kernel\Foundation\Service;
use kernel\Modules\DiscuzX\Controller\Attachment as AttachmentNamespace;
use kernel\Modules\DiscuzX\Foundation\Database\DiscuzXModel;

class DiscuzXAttachmentService extends Service
{
  /**
   * 保存文件
   *
   * @param array $files 上传的文件或者上传的文件列表
   * @param string $saveDir 保存的路径，基于data/plugindata/{插件ID}/attachments目录
   * @return Result
   */
  public static function saveFile($files, $saveDir = "")
  {
    $savePath = Config::get("attachmentPath");
    if (!$savePath) {
      $savePath = Path::join("data", "plugindata", App::id(), "attachments", $saveDir);
      if (!is_dir($savePath)) {
        mkdir($savePath, 0777, true);
      }
    }
    return new Result(FileSystem::upload($files, $savePath));
  }
  /**
   * 上传文件
   *
   * @param array $file 上传的文件
   * @return Result
   */
  public static function uploadFile($file)
  {
    global $_G;
    $R = new Result(null);
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
   * @return Result
   */
  public static function getAttachment($AttachmentId, $thumbWidth = null, $thumbHeight = null)
  {
    $AM = new DiscuzXModel("forum_attachment");
    $attachment = $AM->where("aid", $AttachmentId)->getOne();
    if (!$attachment) {
      return new Result(null, 404, 404001, "附件不存在");
    }
    $TableId = $attachment['tableid'];
    $SAM = new DiscuzXModel("forum_attachment_$TableId");
    $attachment = $SAM->where("aid", $AttachmentId)->getOne();
    if (!$attachment) {
      return new Result(null, 404, 404001, "附件不存在");
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

    return new Result($attachment);
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
   * 获取附件的原始记录
   *
   * Discuz 的附件信息分散在两张表：
   * - `forum_attachment`       附件索引（含 tableid）
   * - `forum_attachment_{0..9}` 附件详情（按 aid 末位分表）
   * 本方法按 aid 合并两表，返回合并后的原始行；不存在时返回 null。
   *
   * @param int|string $AttachmentId 附件 ID（aid）
   * @return array|null
   */
  public static function getRawAttachment($AttachmentId)
  {
    if (!$AttachmentId) {
      return null;
    }

    $AttachmentModel = new DiscuzXModel("forum_attachment");
    $attachment = $AttachmentModel->where("aid", $AttachmentId)->getOne();
    if (!$attachment) {
      return null;
    }

    $TableId = intval($attachment['tableid']);
    $DetailModel = new DiscuzXModel("forum_attachment_" . $TableId);
    $detail = $DetailModel->where("aid", $AttachmentId)->getOne();
    if (!$detail) {
      return null;
    }

    return array_merge($attachment, $detail);
  }

  /**
   * 附件在服务端的绝对路径
   *
   * 形如 {attachdir}/{type}/{attachment}，其中 attachment 为 Discuz 记录的相对路径。
   *
   * @param array  $attachment 附件记录
   * @param string $type       附件类型（forum/common/... ），默认 forum
   * @return string|null 记录缺少 attachment 字段时返回 null
   */
  public static function attachmentStoragePath(array $attachment, $type = "forum")
  {
    if (empty($attachment['attachment'])) {
      return null;
    }

    $attachDir = rtrim(str_replace("\\", "/", (string) \getglobal("setting/attachdir")), "/");
    $type = trim((string) $type, "/");
    $relative = ltrim(str_replace("\\", "/", (string) $attachment['attachment']), "/");

    return "{$attachDir}/{$type}/{$relative}";
  }

  /**
   * 附件下载地址（站内转发）
   *
   * @param int|string $AttachmentId 附件 ID（aid）
   * @param bool       $thumb        是否取缩略图
   * @return string
   */
  public static function attachmentDownloadURL($AttachmentId, $thumb = false)
  {
    $params = [
      "mod" => "attachment",
      "aid" => aidencode($AttachmentId),
    ];
    if (!$thumb) {
      $params['nothumb'] = "yes";
    }

    return "forum.php?" . http_build_query($params);
  }

  /**
   * 附件缩略图地址（仅图片类附件有效）
   *
   * @param array      $attachment 附件记录
   * @param int|null   $width      目标宽度，null 取原图宽度
   * @param int|null   $height     目标高度，null 取原图高度
   * @return string|null 非图片附件返回 null
   */
  public static function attachmentThumbURL(array $attachment, $width = null, $height = null)
  {
    if (empty($attachment['isimage'])) {
      return null;
    }

    $width = is_null($width) ? intval($attachment['width']) : intval($width);
    $height = is_null($height) ? intval($attachment['height']) : intval($height);

    return getforumimg($attachment['aid'], 0, $width, $height, fileext($attachment['filename']));
  }

  /**
   * 注册附件相关路由
   *
   * @return void
   */
  public static function registerRoute()
  {
    Route::post("attachment", AttachmentNamespace\UploadAttachmentController::class);
    new RouteSame("attachment/{attach:\w+}", function (RouteSame $same) {
      $same->get(AttachmentNamespace\GetAttachmentController::class);
      $same->delete(AttachmentNamespace\DeleteAttachmentController::class);
    });
  }
}
