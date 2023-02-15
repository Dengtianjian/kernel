<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response\ResponseDownload;

class DownloadAttachmentController extends Controller
{
  public function data(Request $request)
  {
    $GetAttachment = new GetAttachmentController($request);
    $attachment = $GetAttachment->data($request);
    $fullPath = F_APP_ROOT . "/" . $attachment['path'] . "/" . $attachment['saveFileName'];

    return new ResponseDownload($request, $fullPath, $attachment['fileName']);
  }
}
