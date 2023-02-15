<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Foundation\Response;
use kernel\Service\AttachmentService;

class UploadAttachmentController extends Controller
{
  public function data()
  {
    if (count($_FILES) === 0 || !$_FILES['file']) {
      return new ResponseError(400, "Attachment:400001", "请上传文件", $_FILES);
    }
    $file = $_FILES['file'];
    $uploadResult = AttachmentService::upload($file, "Data/Attachments");

    return $uploadResult;
  }
}
