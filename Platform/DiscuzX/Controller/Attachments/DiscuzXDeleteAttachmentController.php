<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentsService;

class DiscuzXDeleteAttachmentController extends DiscuzXController
{
  public function data($attachmentId)
  {
    $Attachment = DiscuzXAttachmentsService::getAttachment($attachmentId);
    if (!$Attachment) {
      return $this->response->error(404, 404001, "附件不存在或已被删除");
    }
    if ($Attachment['userId'] != getglobal("uid") || !getglobal("adminid")) {
      return $this->response->error(403, 403, "抱歉，您无权删除该附件");
    }

    return DiscuzXAttachmentsService::deleteByAttachmentId($attachmentId);
  }
}
