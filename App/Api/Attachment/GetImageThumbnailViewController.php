<?php

namespace kernel\App\Api\Attachment;

use kernel\Foundation\Controller;
use kernel\Foundation\File;
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
    switch (exif_imagetype($filePath)) {
      case IMAGETYPE_GIF:
        $sourceImage = imagecreatefromgif($filePath);
        break;
      case IMAGETYPE_JPEG:
      case IMAGETYPE_JPEG2000:
        $sourceImage = imagecreatefromjpeg($filePath);
        break;
      case IMAGETYPE_PNG:
        $sourceImage = imagecreatefrompng($filePath);
        break;
      case IMAGETYPE_BMP:
      case IMAGETYPE_WEBP:
        $sourceImage = imagecreatefromwbmp($filePath);
        break;
      case IMAGETYPE_XBM:
        $sourceImage = imagecreatefromxbm($filePath);
        break;
      case IMAGETYPE_WEBP:
        $sourceImage = imagecreatefromwebp($filePath);
        break;
      default:
        Response::download($filePath, $attachment['fileName'], $attachment['fileSize']);
        break;
    }

    $imageInfo = getimagesize($filePath);
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    //* 2832 4240
    //* 480 290
    // Output::debug($sourceWidth, $sourceHeight);
    if ($targetRatio) {
      $targetWdith = $targetWdith * $targetRatio;
      $targetHeight = $targetHeight * $targetRatio;
    } else {
      if ($targetWdith === false && $targetHeight === false) {
        $targetWdith = $sourceWidth;
        $targetHeight = $sourceHeight;
      } else {
        if ($targetWdith && $targetHeight) {
        } else if ($targetWdith) {
          $targetHeight = $sourceHeight / ($sourceWidth / $targetWdith);
        } else if ($targetHeight) {
          $targetWdith = $sourceWidth / ($sourceHeight / $targetHeight);
        }
      }
    }
    $targetImage = imagecreatetruecolor($targetWdith, $targetHeight);
    $copyImage = imagecreatetruecolor($targetWdith, $targetHeight);

    imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWdith, $targetHeight, $sourceWidth, $sourceHeight);

    $fileName = substr($attachment['fileName'], 0, strrpos($attachment['fileName'], ".")) . ".webp";
    header('content-type:image/webp');
    header('Content-Disposition: inline; filename=' . urlencode($fileName));
    imagewebp($targetImage);

    imagedestroy($copyImage);
    imagedestroy($targetImage);
    imagedestroy($sourceImage);
  }
  public function data(Request $R)
  {
    $GetAttachment = new GetAttachmentController($R);
    $attachment = $GetAttachment->data($R);
    $filePath = F_APP_ROOT . "/" . $attachment['path'] . "/" . $attachment['saveFileName'];
    $imageInfo = getimagesize($filePath);
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $targetWdith = $this->query['width'] ?: false;
    $targetHeight = $this->query['height'] ?: false;
    $targetRatio = $this->query['ratio'] ?: false;

    $fileTag = $R->fileId . ":$sourceWidth-$sourceHeight-$targetWdith-$targetHeight-$targetRatio";
    $fileTag = md5($fileTag);

    if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
      $etag = $_SERVER['HTTP_IF_NONE_MATCH'];
      // if ($fileTag === $etag) {
      //   header("HTTP/1.1 304 Not Modified");
      //   exit;
      // }
    }
    header("Last-modified:" . date("D, d M Y H:i:s", time()));
    header("etag: " . $fileTag);
    header("cache-control:no-cache");

    $this->createThumb($attachment, $targetWdith, $targetHeight, $targetRatio);
  }
}
