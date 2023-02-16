<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\Controller\Controller;
use kernel\Foundation\File;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response\ResponseFile;

class GetImageThumbnailViewController extends Controller
{
  public function data(Request $R)
  {
    $GetAttachment = new GetAttachmentController($R);
    $attachment = $GetAttachment->data($R);
    return new ResponseFile($R, File::genPath(F_APP_ROOT, $attachment['path'], $attachment['saveFileName']));
  }
}
