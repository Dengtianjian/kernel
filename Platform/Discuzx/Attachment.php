<?php

namespace kernel\Platform\Discuzx;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Arr;
use kernel\Foundation\Config;
use kernel\Foundation\Database\Model as DatabaseModel;
use kernel\Foundation\File;
use kernel\Foundation\Model;

class Attachment
{
  public static function save($files, $saveDir = "")
  {
    $savePath = Config::get("attachmentPath") . "/$saveDir";
    return File::upload($files, $savePath);
  }
  public static function upload($files, $tableName = "", $tid = 0, $pid = 0, $price = 0, $remote = 0, $saveDir = "", $extid = 0, $forcename = "")
  {
    global $app;
    include_once \libfile("discuz/upload", "class");
    $upload = new \discuz_upload();
    $uploadResult = [];
    $onlyOny = false;
    if (Arr::isAssoc($files)) {
      $onlyOny = true;
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
    include_once \libfile("function/core");
    foreach ($files as $fileItem) {
      $upload->init($fileItem, $saveDir, $extid, $forcename);
      if ($upload->error()) {
        $uploadResult[] =  [
          "error" => $upload->error(),
          "message" => $upload->errormessage()
        ];
        continue;
      } else {
        $upload->save(true);
        $saveFileName = explode("/", $upload->attach['attachment']);
        $path = Config::get("attachmentPath") . "/$saveDir/" . $upload->attach['attachment'];
        $aid = getattachnewaid($uid);
        $width = 0;
        $fileInfo = [];
        if ($upload->attach['isimage']) {
          $fileInfo['width'] = $upload->attach['imageinfo'][0];
          $fileInfo['height'] = $upload->attach['imageinfo'][1];
          $width = $fileInfo['width'];
          if (!$width) {
            $width = 0;
          }
        }
        $insertData = array(
          'aid' => $aid,
          "tid" => $tid,
          "pid" => $pid,
          'uid' => $uid,
          'dateline' => $timestamp,
          'filename' => dhtmlspecialchars(censor($upload->attach['name'])),
          'filesize' => $upload->attach['size'],
          'attachment' => $upload->attach['attachment'],
          'remote' => $remote,
          "description" => "",
          "readperm" => 0,
          "price" => $price,
          'isimage' => $upload->attach['isimage'],
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
          "path" => $path,
          "extension" => $upload->attach['extension'],
          "sourceFileName" => $upload->attach['name'],
          "saveFileName" => $saveFileName[count($saveFileName) - 1],
          "size" => $upload->attach['size'],
          "type" => $upload->attach['type'],
          "fullPath" => $path,
          "aid" => $aid,
          "tableId" => $tableId,
          "tableName" => $tableName,
          "dzAidEncode" => \aidencode($aid, 0, $tid),
          "downloadEncode" => self::aidencode($aid)
        ];
        $uploadResult[] = $fileInfo;
        $updateDatas[] = [
          $aid,
          $tableId,
          $uid
        ];
      }
    }
    foreach ($insertDatas as $tableName => $insertData) {
      $attachmenModel = new Model($tableName);
      $attachmenModel->batchInsertByMS($insertData)->save();
    }
    $ForumAttachmentModel = new DatabaseModel("forum_attachment");
    $ForumAttachmentModel->batchUpdate([
      "aid", "tableid", "uid"
    ], $updateDatas)->save();

    if ($onlyOny) {
      return $uploadResult[0];
    }

    return $uploadResult;
  }
  public static function aidencode($aid, $dir = "plugin", $type = 0, $tid = 0)
  {
    // global $_G;
    // $s = !$type ? $aid . '|' . substr(md5($aid . md5($_G['config']['security']['authkey']) . TIMESTAMP . $_G['uid']), 0, 8) . '|' . TIMESTAMP . '|' . $_G['uid'] . '|' . $tid : $aid . '|' . md5($aid . md5($_G['config']['security']['authkey']) . TIMESTAMP) . '|' . TIMESTAMP;
    // $s .= "|" . $dir;
    // return rawurlencode(base64_encode($s));
  }
}
