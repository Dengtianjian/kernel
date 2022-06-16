<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\Controller;
use kernel\Foundation\Request;
use kernel\Foundation\Response;
use kernel\Foundation\Data\Str;
use kernel\Model\AttachmentModel;
use kernel\Service\AttachmentService;

class DeleteAttachmentController extends Controller
{
  public $body = [
    "fileId" => "string",
    "id" => "string"
  ];
  public function data()
  {
    $fileId = $this->body['fileId'] ? urldecode($this->body['fileId']) : null;
    $attachmentId = $this->body['id'];
    if (!$fileId) {
      if (!$attachmentId) {
        Response::error(400, "GetAttachment:400001", "附件不存在");
      }
    }

    $AM = new AttachmentModel();
    $query = [];
    if ($fileId) {
      $query['fileId'] = $fileId;
    }
    if ($attachmentId) {
      $query['id'] = $attachmentId;
    }
    $attachment = $AM->where($query)->getOne();
    $deleteResult = $AM->where($query)->delete(true);
    if ($deleteResult) {
      $filePath = F_APP_ROOT . "/" . $attachment['path'] . "/" . $attachment['saveFileName'];
      if (file_exists($filePath)) {
        unlink($filePath);
      }
    }

    return [
      "attachmentId" => $attachmentId,
      "fileId" => $fileId,
      "deleteCount" => $deleteResult
    ];
  }
}
