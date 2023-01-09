<?php

namespace kernel\App\Api\Attachments;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Lang;
use kernel\Foundation\Response;
use kernel\Platform\Discuzx\Attachment;

class UploadAttachmentController extends AuthController
{
  public function post()
  {
    if (count($_FILES) === 0 || !$_FILES['file']) {
      Response::error(400, "Attachment:400001", Lang::value("kernel/attachments/pleaseUploadFile"), $_FILES);
    }
    $file = $_FILES['file'];
    return Attachment::upload($file);
  }
}
