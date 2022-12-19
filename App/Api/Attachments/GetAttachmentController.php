<?php

namespace gstudio_kernel\App\Api\Attachments;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Controller\AuthController;
use gstudio_kernel\Foundation\Lang;
use gstudio_kernel\Foundation\Request;
use gstudio_kernel\Foundation\Response;
use gstudio_kernel\Platform\Discuzx\Attachment;

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
