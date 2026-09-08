<?php

namespace kernel\Modules\DiscuzX\Controller\Attachment;

use kernel\Modules\DiscuzX\Service\DiscuzXAttachmentService;
use kernel\Modules\DiscuzX\Foundation\DiscuzXController;

class DeleteAttachmentController extends DiscuzXController
{
  public function data($aid)
  {
    return DiscuzXAttachmentService::deleteAttachment($aid);
  }
}
