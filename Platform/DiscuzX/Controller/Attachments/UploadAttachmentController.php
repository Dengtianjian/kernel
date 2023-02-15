<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Platform\DiscuzX\DiscuzXAttachment;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;

class UploadAttachmentController extends DiscuzXController
{
  public function data()
  {
    if (count($_FILES) === 0 || !$_FILES['file']) {
      return new ResponseError(400, "Attachment:400001", "请上传文件", $_FILES);
    }

    return DiscuzXAttachment::uploadFile($_FILES['file']);
  }
}
