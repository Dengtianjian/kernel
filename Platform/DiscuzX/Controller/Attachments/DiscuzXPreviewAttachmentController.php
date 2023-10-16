<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Foundation\File;
use kernel\Foundation\HTTP\Response\ResponseFile;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Model\DiscuzXAttachmentKeysModel;
use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentsService;

class DiscuzXPreviewAttachmentController extends DiscuzXController
{
  public $query = [
    "w" => "string",
    "h" => "string",
    "r" => "double",
    "key" => "string",
    "ext" => "string",
    "q" => "double"
  ];
  public function data($attachmentId)
  {
    global $_G;
    if (!$_G['group']['allowgetimage']) {
      return $this->response->error(403, 403001, "抱歉，您无权预览附件");
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
          return $this->response->error(403, 403002, "无权访问该附件");
        }
        $Key = $this->query->get("key");
        $KeyData = DiscuzXAttachmentKeysModel::singleton()->item(null, $Key, $attachmentId);
        DiscuzXAttachmentKeysModel::singleton()->deleteExpired();
        if (!$KeyData) {
          return $this->response->error(403, 403003, "访问秘钥错误");
        }
        if ($KeyData['expirationTime'] < time()) {
          return $this->response->error(403, 403004, "附件预览权限已过期");
        }
        if ($KeyData['preview'] != "1") {
          return $this->response->error(403, 403005, "无权预览该附件");
        }
        // if ($KeyData['userId'] != "0" && $KeyData['userId'] != getglobal("uid")) {
        //   return $this->response->error(403, 403006, "无权预览该附件");
        // }
        $width = $this->query->has("w") ? $this->query->get("w") : null;
        $height = $this->query->has("h") ? $this->query->get("h") : null;
        $ratio = $this->query->has("r") ? $this->query->get("r") : null;
        $outputExtension = $this->query->has("ext") ? $this->query->get("ext") : null;
        $quality = $this->query->has("q") ? $this->query->get("q") : null;
        $rawKey = DiscuzXAttachmentsService::generateAccessKey($attachmentId, $KeyData['userId'], null, $KeyData['expirationTime'], $KeyData['preview'], $KeyData['download'], $width, $height, $ratio, $outputExtension, $quality);

        if ($rawKey !== $Key) {
          return $this->response->error(403, 403007, "访问秘钥错误");
        }
      }
    }

    return new ResponseFile($this->request, $FilePath, $Attachment['fileName'], $quality);
  }
}
