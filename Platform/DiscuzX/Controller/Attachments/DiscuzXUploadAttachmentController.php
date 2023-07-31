<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Foundation\Controller\AuthController;
use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentsService;

class DiscuzXUploadAttachmentController extends AuthController
{
  public function data()
  {
    return DiscuzXAttachmentsService::upload($_FILES['file']);
  }
}
