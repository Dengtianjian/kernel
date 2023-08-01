<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Foundation\File;
use kernel\Foundation\HTTP\Response\ResponseFile;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentsService;

class DiscuzXPreviewAttachmentController extends DiscuzXController
{
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
    return new ResponseFile($this->request, $FilePath, $Attachment['fileName']);
  }
}
