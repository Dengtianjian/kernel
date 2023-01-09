<?php

namespace kernel\App\Api\Attachments;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Lang;
use kernel\Foundation\Request;
use kernel\Foundation\Response;
use kernel\Platform\Discuzx\Attachment;

class GetAttachmentController extends AuthController
{
  public $body = [
    "aid" => "integer"
  ];
  // static $Admin = false;
  // static function Admin()
  // {
  //   return true;
  // }
  // static function verifyAdmin(){
  //   return false;
  // }
  static $Auth = false;
  static function Auth()
  {
    return true;
  }
  public function get($R)
  {
    $AttachmentId = $R->params("attachmentId");

    $attachment = Attachment::getAttachment($AttachmentId);
    if (!$attachment) {
      Response::error(403, "403002:AttachmentNotExist", Lang::value("kernel/attachments/notExist"), [], Lang::value("kernel/attachments/notExistDetails"));
    }

    return $attachment;
  }
}
