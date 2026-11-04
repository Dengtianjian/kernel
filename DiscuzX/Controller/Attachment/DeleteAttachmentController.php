<?php

namespace kernel\DiscuzX\Controller\Attachment;

use kernel\DiscuzX\Service\DiscuzXAttachmentService;
use kernel\DiscuzX\Foundation\DiscuzXController;

class DeleteAttachmentController extends DiscuzXController
{
  public function data($aid)
  {
    return DiscuzXAttachmentService::deleteAttachment($aid);
  }
}
