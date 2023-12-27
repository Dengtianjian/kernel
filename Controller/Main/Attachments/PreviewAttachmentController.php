<?php

namespace kernel\Controller\Main\Attachments;

use kernel\Foundation\File\FileHelper;
use kernel\Foundation\HTTP\Response\ResponseFile;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Service\AttachmentsService;

class PreviewAttachmentController extends DiscuzXController
{
  public function data($attachmentId)
  {
    $Attachment = AttachmentsService::getAttachment($attachmentId);
    if (!$Attachment) {
      return $this->response->error(404, 404001, "附件不存在或已被删除");
    }
    $FilePath = FileHelper::combinedFilePath(F_APP_ROOT, $Attachment['filePath'], $Attachment['fileName']);
    if (!$FilePath || !file_exists($FilePath)) {
      return $this->response->error(404, 404002, "附件不存在或已被删除");
    }
    return new ResponseFile($this->request, $FilePath, $Attachment['fileName']);
  }
}
