<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\Controller;
use kernel\Foundation\Output;
use kernel\Foundation\Request;
use kernel\Foundation\Response;

class GetImageThumbnailViewController extends Controller
{
  public $query = [
    "width" => "integer",
    "height" => "integer",
    "ratio" => "double"
  ];
  private function createThumb($attachment, $targetWdith, $targetHeight, $targetRatio)
  {
    $filePath = F_APP_ROOT . "/" . $attachment['path'] . "/" . $attachment['saveFileName'];
    $sourceImage = imagecreatefromjpeg($filePath);
    $imageInfo = getimagesize($filePath);
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];

    if ($targetRatio !== false) {
      $targetWdith = $targetWdith * $targetRatio;
      $targetHeight = $targetHeight * $targetRatio;
    }
    $targetImage = imagecreatetruecolor($targetWdith, $targetHeight);
    $bg = imagecolorallocate($targetImage, 255, 255, 255);
    imagefill($targetImage, 0, 0, $bg);

    imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWdith, $targetHeight, $sourceWidth, $sourceHeight);

    $fileName = substr($attachment['fileName'], 0, strrpos($attachment['fileName'], ".")) . ".webp";
    header('content-type:image/webp');
    header('Content-Disposition: inline; filename=' . urlencode($fileName));
    imagewebp($targetImage);
    imagedestroy($targetImage);
  }
  public function data(Request $R)
  {
    $GetAttachment = new GetAttachmentController($R);
    $attachment = $GetAttachment->data($R);
    $filePath = F_APP_ROOT . "/" . $attachment['path'] . "/" . $attachment['saveFileName'];
    $imageInfo = getimagesize($filePath);
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $targetWdith = $this->query['width'] ?: $sourceWidth;
    $targetHeight = $this->query['height'] ?: $sourceHeight;
    $targetRatio = $this->query['ratio'] ?: false;

    $this->createThumb($attachment, $targetWdith, $targetHeight, $targetRatio);
  }
}
