<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentsService;

/**
 * 获取附件信息
 * @deprecated
 */
class DiscuzXGetAttachmentController extends DiscuzXController
{
  public $serializes = [
    "id" => "int",
    "attachId" => "string",
    "remote" => "boolean",
    "belongsId" => "string",
    "belongsType" => "string",
    "userId" => "int",
    "sourceFileName" => "string",
    "fileName" => "string",
    "fileSize" => "double",
    "filePath" => "string",
    "width" => "double",
    "height" => "double",
    "extension" => "string",
    "createdAt" => "int",
    "updatedAt" => "int",
  ];
  public function data($attachmentId)
  {
    $Attachment = DiscuzXAttachmentsService::getAttachment($attachmentId);
    if (!$Attachment) {
      return $this->response->error(404, 404001, "附件不存在或已被删除");
    }
    global $_G;
    if ($_G['group']['allowgetattach'] == "0") {
      return $this->response->error(403, 403, "抱歉，您目前没有权限获取附件信息");
    }
    return $Attachment;
  }
}
