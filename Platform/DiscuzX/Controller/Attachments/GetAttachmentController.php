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
    "aid",
    "filename",
    "attachment",
    "downloadLink",
    "thumbURL",
    "isimage",
    "width",
    "height",
    "filesize"
  ];
  public function data(Request $request, $attachId)
  {
    return DiscuzXAttachment::getAttachment($attachId, $this->query->get("w"), $this->query->get("h"));
  }
}
