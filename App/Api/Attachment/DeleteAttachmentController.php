<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\File;
use kernel\Foundation\Request;
use kernel\Foundation\Response;
use kernel\Foundation\Str;
use official\Foundation\Controller;
use official\Model\AttachmentModel;
use official\Service\AttachmentService;

class DeleteAttachmentController extends Controller
{
  public function data(Request $request)
  {
    $fileId = $request->body('fileId');
    if (!$fileId) {
      Response::error(400, "GetAttachment:400001", "附件不存在");
    }
    $fileId = Str::unescape($fileId);
    $attachment = AttachmentService::getAttachmentInfo($fileId);
    if (!$attachment) {
      return [
        "fileId" => $fileId
      ];
    }

    $AM = new AttachmentModel();
    $deleteResult = $AM->where([
      "fileId" => $attachment['fileId']
    ])->delete(true);
    if ($deleteResult) {
      $filePath = F_APP_ROOT . "/" . $attachment['path'] . "/" . $attachment['saveFileName'];
      if (file_exists($filePath)) {
        unlink($filePath);
      }
    }

    return [
      "fileId" => $fileId,
      "deleteCount" => $deleteResult
    ];
  }
}
