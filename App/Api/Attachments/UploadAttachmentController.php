<?php

namespace gstudio_kernel\App\Api\Attachments;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Controller\AuthController;
use gstudio_kernel\Foundation\Lang;
use gstudio_kernel\Foundation\Response;
use gstudio_kernel\Platform\Discuzx\Attachment;

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
