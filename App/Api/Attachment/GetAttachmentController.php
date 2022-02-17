<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\Controller;
use kernel\Foundation\Request;
use kernel\Foundation\Response;
use kernel\Service\AttachmentService;

class GetAttachmentController extends Controller
{
  public function data(Request $request)
  {
    $fileId = $request->query('fileId');
    if (!$fileId) {
      Response::error(400, "GetAttachment:400001", "附件不存在", [], "fileId无效");
    }
    $fileId = \urldecode($fileId);
    $attachment = AttachmentService::getAttachmentInfo($fileId);
    if (!$attachment) {
      Response::error(400, "GetAttachment:400002", "附件不存在", [], [
        "fileId" => $fileId
      ]);
    }
    return $attachment;
  }
}
