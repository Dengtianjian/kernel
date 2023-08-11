<?php

namespace kernel\Controller\Main\Attachments;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\AttachmentsService;

class DeleteAttachmentController extends AuthController
{
  public function data($attachmentId)
  {
    return AttachmentsService::deleteByAttachmentId($attachmentId);
  }
}
