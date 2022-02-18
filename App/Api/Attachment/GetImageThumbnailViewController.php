<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\Controller;
use kernel\Foundation\Output;
use kernel\Foundation\Request;
use kernel\Foundation\Response;

class GetImageThumbnailViewController extends Controller
{
  private function createThumb($attachment, $targetWdith, $targetHeight)
  {
    $filePath = F_APP_ROOT . "/" . $attachment['path'] . "/" . $attachment['saveFileName'];
    $sourceImage = imagecreatefromjpeg($filePath);
    $imageInfo = getimagesize($filePath);
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];

    $targetImage = imagecreatetruecolor($targetWdith, $targetHeight);
    $bg = imagecolorallocate($targetImage, 250, 250, 250);
    imagefill($targetImage, 0, 0, $bg);

    imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWdith, $targetHeight, $sourceWidth, $sourceHeight);

    imagewebp($targetImage);
  }
  public function data(Request $R)
  {
    $GetAttachment = new GetAttachmentController($R);
    $attachment = $GetAttachment->data($R);
    $fullPath = F_APP_ROOT . "/" . $attachment['path'] . "/" . $attachment['saveFileName'];

    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $attachment['fileSize']);
    header('Content-type: image/webp;', true);
    header('Content-Disposition: inline; filename=' . urlencode($attachment['fileName']));
    $this->createThumb($attachment, 500, 300);
    // Output::debug($attachment);
    // return 1;

    // Response::download($fullPath, $attachment['fileName'], $attachment['fileSize']);
  }
}
