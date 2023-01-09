<?php

namespace kernel\App\Api\Attachments;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use DB;
use kernel\Foundation\Config;
use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Database\Model;
use kernel\Foundation\File;
use kernel\Foundation\Lang;
use kernel\Foundation\Output;
use kernel\Foundation\Response;

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
