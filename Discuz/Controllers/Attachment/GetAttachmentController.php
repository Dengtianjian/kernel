<?php

namespace kernel\Discuz\Controllers\Attachment;

use kernel\Discuz\Services\DiscuzAttachmentService;
use kernel\Discuz\Foundation\DiscuzController;

class GetAttachmentController extends DiscuzController
{
  public $query = [
    "w" => "int",
    "h" => "int",
  ];
  public $serializes = [
    "aid" => "int",
    "fileName" => "string",
    "isImage" => "bool",
    "size" => "double",
    "width" => "double",
    "height" => "double",
    "downloadLink" => "string",
    "thumbURL" => "string",
  ];
  public function data($attachId)
  {
    return DiscuzAttachmentService::getAttachment($attachId, $this->query()->get("w"), $this->query()->get("h"));
  }
}
