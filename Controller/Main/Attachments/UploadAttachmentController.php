<?php

namespace kernel\Controller\Main\Attachments;

use kernel\Foundation\Controller\AuthController;
use kernel\Service\AttachmentsService;

class UploadAttachmentController extends AuthController
{
  public function data()
  {
    return AttachmentsService::upload($_FILES['file']);
  }
}
