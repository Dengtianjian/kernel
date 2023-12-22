<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Foundation\File;
use kernel\Foundation\HTTP\Response\ResponseDownload;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Model\DiscuzXAttachmentKeysModel;
use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentsService;

class DiscuzXDownloadAttachmentController extends DiscuzXController
{
  public $query = [
    "key" => "string",
  ];
  public function data($attachmentId)
  {
    global $_G;
    if (!$_G['group']['allowgetattach']) {
      return $this->response->error(403, 403001, "抱歉，您无权下载附件");
    }
    $Attachment = DiscuzXAttachmentsService::getAttachment($attachmentId);
    if (!$Attachment) {
      return $this->response->error(404, 404001, "附件不存在或已被删除");
    }
    $FilePath = File::genPath(F_DISCUZX_DATA_PLUGIN, $Attachment['filePath'], $Attachment['fileName']);
    if (!$FilePath) {
      return $this->response->error(404, 404002, "附件不存在或已被删除");
    }

    if ($Attachment['userId'] != getglobal("uid") && getglobal("adminid") != 1) {
      if ($Attachment['key']) {
        if (!$this->query->has("key")) {
          return $this->response->error(403, 403002, "无权下载该附件");
        }
        $Key = $this->query->get("key");
        $KeyData = DiscuzXAttachmentKeysModel::singleton()->item(null, $Key, $attachmentId);
        DiscuzXAttachmentKeysModel::singleton()->deleteExpired();
        if (!$KeyData) {
          return $this->response->error(403, 403003, "下载秘钥错误");
        }
        if ($KeyData['expirationTime'] < time()) {
          return $this->response->error(403, 403004, "附件下载权限已过期");
        }
        if ($KeyData['download'] != "1") {
          return $this->response->error(403, 403005, "无权下载该附件");
        }
        // if ($KeyData['userId'] != "0" && $KeyData['userId'] != getglobal("uid")) {
        //   return $this->response->error(403, 403006, "无权下载该附件");
        // }
        $rawKey = DiscuzXAttachmentsService::generateAccessKey($attachmentId, $KeyData['userId'], null, $KeyData['expirationTime'], $KeyData['preview'], $KeyData['download']);
        if ($rawKey !== $Key) {
          return $this->response->error(403, 403007, "访问秘钥错误");
        }
      }
    }

    return new ResponseDownload($this->request, $FilePath, $Attachment['sourceFileName'], false, 1);
  }
}
