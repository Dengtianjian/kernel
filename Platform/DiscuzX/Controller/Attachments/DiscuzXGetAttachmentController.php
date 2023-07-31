<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Foundation\Controller\AuthController;
use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentsService;

class DiscuzXGetAttachmentController extends AuthController
{
  public function data($attachmentId)
  {
    return DiscuzXAttachmentsService::getAttachment($attachmentId);
  }
}
