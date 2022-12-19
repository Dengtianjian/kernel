<?php

namespace gstudio_kernel\App\Api\Attachments;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use DB;
use gstudio_kernel\Foundation\Config;
use gstudio_kernel\Foundation\Controller\AuthController;
use gstudio_kernel\Foundation\Database\Model;
use gstudio_kernel\Foundation\File;
use gstudio_kernel\Foundation\Lang;
use gstudio_kernel\Foundation\Output;
use gstudio_kernel\Foundation\Response;

class DeleteAttachmentController extends AuthController
{
  public $body = [
    "aid" => "integer"
  ];
  public function delete()
  {
    $AttachmentId = $this->body['aid'];

    $AM = new Model("forum_attachment");
    $attachment = $AM->where("aid", $AttachmentId)->getOne();
    if (!$attachment) {
      Response::error(403, "403001:AttachmentNotExist", Lang::value("kernel/attachments/notExist"), [], Lang::value("kernel/attachments/notExistDetails"));
    }
    $TableId = $attachment['tableid'];
    $SAM = new Model("forum_attachment_$TableId");
    $attachment = $SAM->where("aid", $AttachmentId)->getOne();
    if (!$attachment) {
      Response::error(403, "403002:AttachmentNotExist", Lang::value("kernel/attachments/notExist"), [], Lang::value("kernel/attachments/notExistDetails"));
    }
    $attachmentPath = File::genPath(Config::get("attachmentPath"), $attachment['attachment']);
    if (!file_exists($attachmentPath)) {
      $AM->where("aid", $AttachmentId)->delete(true);
      $SAM->where("aid", $AttachmentId)->delete(true);
      return true;
    }
    $unlinkResult = unlink($attachmentPath);
    if ($unlinkResult) {
      $AM->where("aid", $AttachmentId)->delete(true);
      $SAM->where("aid", $AttachmentId)->delete(true);
    } else {
      Response::error(500, "500:DeleteAttachmentFalied", Lang::value("kernel/attachments/deletedFailed"));
    }

    return true;
  }
}
