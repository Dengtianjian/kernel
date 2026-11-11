<?php

namespace kernel\DiscuzX\Controllers\Attachment;

use kernel\DiscuzX\Services\DiscuzXAttachmentService;
use kernel\DiscuzX\Foundation\DiscuzXController;

class DeleteAttachmentController extends DiscuzXController
{
  public function data($aid)
  {
    return DiscuzXAttachmentService::deleteAttachment($aid);
  }
}
