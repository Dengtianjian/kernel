<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\Controller;
use kernel\Foundation\Request;
use kernel\Foundation\Response;

class DownloadAttachmentController extends Controller
{
  public function data(Request $request)
  {
    $GetAttachment = new GetAttachmentController($request);
    $attachment = $GetAttachment->data($request);
    $fullPath = F_APP_ROOT . "/" . $attachment['path'] . "/" . $attachment['saveFileName'];

    Response::download($fullPath, $attachment['fileName'], $attachment['fileSize']);
    exit();
  }
}
