<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Foundation\HTTP\Request;
use kernel\Platform\DiscuzX\DiscuzXAttachment;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;

class DeleteAttachmentController extends DiscuzXController
{
  public function data($aid)
  {
    DiscuzXAttachment::deleteAttachment([175, 176]);
    return true;
  }
}
