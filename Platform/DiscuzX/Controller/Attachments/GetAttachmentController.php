<?php

namespace kernel\Platform\DiscuzX\Controller\Attachments;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response\ResponseError;
use kernel\Platform\DiscuzX\DiscuzXAttachment;
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
    return DiscuzXAttachment::getAttachment($attachId, $this->query->get("w"), $this->query->get("h"));
  }
}
