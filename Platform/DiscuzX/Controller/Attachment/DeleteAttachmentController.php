<?php

namespace kernel\Platform\DiscuzX\Controller\Attachment;

use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentService;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;

class DeleteAttachmentController extends DiscuzXController
{
  public function data($aid)
  {
    return DiscuzXAttachmentService::deleteAttachment($aid);
  }
}
