<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\File;
use kernel\Foundation\HTTP\Response\ResponseDownload;
use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentsService;

class DiscuzXDownloadAttachmentController extends AuthController
{
  public function data($attachmentId)
  {
    $Attachment = DiscuzXAttachmentsService::getAttachment($attachmentId);
    if (!$Attachment) {
      return $this->response->error(404, 404001, "附件不存在或已被删除");
    }
    $FilePath = File::genPath(F_DISCUZX_DATA_PLUGIN, $Attachment['filePath'], $Attachment['fileName']);
    if (!$FilePath) {
      return $this->response->error(404, 404002, "附件不存在或已被删除");
    }

    return new ResponseDownload($this->request, $FilePath, $Attachment['sourceFileName'], false, 1);
  }
}
