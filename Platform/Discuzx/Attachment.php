<?php

namespace gstudio_kernel\Platform\Discuzx;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Config;
use gstudio_kernel\Foundation\Data\Arr;
use gstudio_kernel\Foundation\Database\Model as DatabaseModel;
use gstudio_kernel\Foundation\File;
use gstudio_kernel\Foundation\Output;
use gstudio_kernel\Foundation\Store;

class Attachment
{
  public static function save($files, $saveDir = "")
  {
    $savePath = File::genPath(Config::get("attachmentPath"), $saveDir);
    if (!is_dir($savePath)) {
      mkdir($savePath, 0777, true);
    }
    return File::upload($files, $savePath);
  }
  public static function upload($files, $tableName = "", $tid = 0, $pid = 0, $price = 0, $remote = 0, $saveDir = "", $extid = 0, $forcename = "")
  {
    $uploadResult = [];
    $onlyOne = false;
    if (Arr::isAssoc($files)) {
      $onlyOne = true;
      $files = [$files];
    } else {
      $files = array_values($files);
    }
    $uid = \getglobal("uid");
    $timestamp = \getglobal("timestamp");
    $insertDatas = [];
    $updateDatas = [];
    if ($tableName) {
      $insertDatas[$tableName] = [];
    }
    foreach ($files as $fileItem) {
      $path = Config::get("attachmentPath") . $saveDir;
      $updateResult = File::upload($fileItem, $path);
      $aid = getattachnewaid($uid);
      $width = 0;
      $fileInfo = [];

      $insertData = array(
        'aid' => $aid,
        "tid" => $tid,
        "pid" => $pid,
        'uid' => $uid,
        'dateline' => $timestamp,
        'filename' => dhtmlspecialchars(censor($updateResult['sourceFileName'])),
        'filesize' => $updateResult['size'],
        'attachment' => $updateResult['saveFileName'],
        'remote' => $remote,
        "description" => "",
        "readperm" => 0,
        "price" => $price,
        'isimage' => (int)File::isImage($updateResult['sourceFileName']),
        'width' => $width,
        'thumb' => 0,
        "picid" => 0
      );
      if (!$tableName) {
        $tableId = null;
        if ($tid) {
          $tableId = getattachtableid($tid);
        } else {
          $tableId = getattachtableid(time());
        }
        $tableName = "forum_attachment_" . $tableId;
        if (!$insertDatas[$tableName]) {
          $insertDatas[$tableName] = [];
        }
        array_push($insertDatas[$tableName], $insertData);
      } else {
        array_push($insertDatas[$tableName], $insertData);
      }
      $fileInfo = [
        "path" => $updateResult['path'],
        "extension" => $updateResult['extension'],
        "sourceFileName" => $updateResult['sourceFileName'],
        "saveFileName" => $updateResult['saveFileName'],
        "size" => $updateResult['size'],
        "fullPath" => $updateResult['fullPath'],
        "aid" => $aid,
        "tableId" => $tableId,
        "tableName" => $tableName,
        "dzAidEncode" => \aidencode($aid, 0, $tid),
        "downloadEncode" => self::aidencode($aid, "plugin/" . Store::getApp('id') . "/attachments")
      ];
      $uploadResult[] = $fileInfo;
      $updateDatas[] = [
        $aid,
        $tableId,
        $uid
      ];
    }

    foreach ($insertDatas as $tableName => $insertData) {
      $attachmenModel = new DatabaseModel($tableName);
      $fieldNames = array_keys($insertData[0]);
      $attachmenModel->batchInsert($fieldNames, $insertData);
    }
    $ForumAttachmentModel = new DatabaseModel("forum_attachment");
    $ForumAttachmentModel->batchUpdate([
      "aid", "tableid", "uid"
    ], $updateDatas);

    if ($onlyOne) {
      return $uploadResult[0];
    }

    return $uploadResult;
  }
  public static function aidencode($aid, $dir = "plugin", $type = 0, $tid = 0)
  {
    global $_G;
    $s = !$type ? $aid . '|' . substr(md5($aid . md5($_G['config']['security']['authkey']) . TIMESTAMP . $_G['uid']), 0, 8) . '|' . TIMESTAMP . '|' . $_G['uid'] . '|' . $tid : $aid . '|' . md5($aid . md5($_G['config']['security']['authkey']) . TIMESTAMP) . '|' . TIMESTAMP;
    $s .= "|" . $dir;
    return rawurlencode(base64_encode($s));
  }
  public static function getAttachment($AttachmentId)
  {
    $AM = new DatabaseModel("forum_attachment");
    $attachment = $AM->where("aid", $AttachmentId)->getOne();
    if (!$attachment) {
      return null;
    }
    $TableId = $attachment['tableid'];
    $SAM = new DatabaseModel("forum_attachment_$TableId");
    $attachment = $SAM->where("aid", $AttachmentId)->getOne();
    if (!$attachment) {
      return null;
    }

    return $attachment;
  }
}
