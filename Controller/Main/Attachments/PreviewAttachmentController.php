<?php

namespace kernel\Controller\Main\Attachments;

use kernel\Foundation\File;
use kernel\Foundation\HTTP\Response\ResponseFile;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Service\AttachmentsService;

class PreviewAttachmentController extends DiscuzXController
{
  public function data($attachmentId)
  {
    global $_G;
    $Attachment = AttachmentsService::getAttachment($attachmentId);
    if (!$Attachment) {
      return $this->response->error(404, 404001, "附件不存在或已被删除");
    }
    $FilePath = File::genPath(F_APP_DATA, $Attachment['filePath'], $Attachment['fileName']);
    if (!$FilePath) {
      return $this->response->error(404, 404002, "附件不存在或已被删除");
    }
    return new ResponseFile($this->request, $FilePath, $Attachment['fileName']);
  }
}
