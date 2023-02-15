<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Service\AttachmentService;

class GetAttachmentController extends Controller
{
  public function data(Request $request)
  {
    $fileId = $request->query->get('fileId');
    if (!$fileId) {
      return new ResponseError(400, "GetAttachment:400001", "附件不存在", [], "fileId无效");
    }
    $fileId = \urldecode($fileId);
    $attachment = AttachmentService::getAttachmentInfo($fileId);
    if (!$attachment) {
      return new ResponseError(400, "GetAttachment:400002", "附件不存在", [], [
        "fileId" => $fileId
      ]);
    }
    return $attachment;
  }
}
