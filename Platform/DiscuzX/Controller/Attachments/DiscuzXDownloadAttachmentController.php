<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Foundation\File;
use kernel\Foundation\HTTP\Response\ResponseDownload;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentsService;

class DiscuzXDownloadAttachmentController extends DiscuzXController
{
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

    return new ResponseDownload($this->request, $FilePath, $Attachment['sourceFileName'], false, 1);
  }
}
