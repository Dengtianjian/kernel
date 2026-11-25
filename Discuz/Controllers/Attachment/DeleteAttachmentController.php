<?php

namespace kernel\Discuz\Controllers\Attachment;

use kernel\Discuz\Services\DiscuzAttachmentService;
use kernel\Discuz\Foundation\DiscuzController;

class DeleteAttachmentController extends DiscuzController
{
  public function data($aid)
  {
    return DiscuzAttachmentService::deleteAttachment($aid);
  }
}
