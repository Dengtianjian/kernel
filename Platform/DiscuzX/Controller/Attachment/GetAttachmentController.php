<?php

namespace kernel\Platform\DiscuzX\Controller\Attachment;

use kernel\Platform\DiscuzX\Service\DiscuzXAttachmentService;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;

class GetAttachmentController extends DiscuzXController
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
    return DiscuzXAttachmentService::getAttachment($attachId, $this->query->get("w"), $this->query->get("h"));
  }
}
