<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\File;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Output;

class ResponseFile extends ResponseDownload
{
  public function __construct(Request $R, $filePath, $downloadFileName = null)
  {
    parent::__construct($R, $filePath, $downloadFileName, false);
  }
  private function createThumb($filePath, $fileName, $targetWdith, $targetHeight, $targetRatio)
  {
    $targetExt = "webp";
    switch (exif_imagetype($filePath)) {
      case IMAGETYPE_GIF:
        $sourceImage = imagecreatefromgif($filePath);
        $targetExt = "gif";
        break;
      case IMAGETYPE_JPEG:
      case IMAGETYPE_JPEG2000:
        $sourceImage = imagecreatefromjpeg($filePath);
        break;
      case IMAGETYPE_PNG:
        $sourceImage = imagecreatefrompng($filePath);
        $targetExt = "png";
        break;
      case IMAGETYPE_BMP:
        $sourceImage = imagecreatefromwbmp($filePath);
        $targetExt = "bmp";
        break;
      case IMAGETYPE_XBM:
        $sourceImage = imagecreatefromxbm($filePath);
        $targetExt = "xbm";
        break;
      case IMAGETYPE_WEBP:
      default:
        $sourceImage = imagecreatefromwebp($filePath);
        break;
    }

    $imageInfo = getimagesize($filePath);
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    if ($targetRatio) {
      $targetWdith = $sourceWidth * $targetRatio;
      $targetHeight = $sourceHeight * $targetRatio;
    } else {
      if ($targetWdith === false && $targetHeight === false) {
        $targetWdith = $sourceWidth;
        $targetHeight = $sourceHeight;
      } else {
        if ($targetWdith && !$targetHeight) {
          $targetHeight = $sourceHeight / ($sourceWidth / $targetWdith);
        } else if ($targetHeight) {
          $targetWdith = $sourceWidth / ($sourceHeight / $targetHeight);
        }
      }
    }
    imagesavealpha($sourceImage, true);
    $targetImage = imagecreatetruecolor($targetWdith, $targetHeight);
    imagealphablending($targetImage, false);
    imagesavealpha($targetImage, true);

    imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWdith, $targetHeight, $sourceWidth, $sourceHeight);

    $fileName = substr($fileName, 0, strrpos($fileName, ".")) . "." . $targetExt;
    header('Content-type:image/' . $targetExt);
    header('Content-Length: ' . "", true);
    switch ($targetExt) {
      case "png":
        imagepng($targetImage);
        break;
      case "gif":
        imagegif($targetImage);
        break;
      case "bmp":
        imagebmp($targetImage);
        break;
      default:
        imagewebp($targetImage);
        break;
    }

    imagedestroy($targetImage);
    imagedestroy($sourceImage);
  }
  public function output()
  {
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $this->fileSize);
    header('Content-Disposition: inline; filename=' . urlencode($this->fileName));

    header('Content-type: ' . mime_content_type($this->filePath) . ';', true);

    if (File::isImage($this->filePath)) {
      if ($this->request->query->has("w") || $this->request->query->has("h") || $this->request->query->has("r")) {
        $imageInfo = getimagesize($this->filePath);
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $targetWdith = $this->request->query->get("w") ?: false;
        $targetHeight = $this->request->query->get("h") ?: false;
        $targetRatio = $this->request->query->get("r") ?: false;

        $fileTag = $this->filePath . ":$sourceWidth-$sourceHeight-$targetWdith-$targetHeight-$targetRatio";
        $fileTag = md5($fileTag);

        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
          $etag = $_SERVER['HTTP_IF_NONE_MATCH'];
          if ($fileTag === $etag) {
            header("HTTP/1.1 304 Not Modified");
            exit;
          }
        }
        header("Last-modified:" . date("D, d M Y H:i:s", time()));
        header("etag: " . $fileTag);
        header("cache-control:no-cache");

        $this->createThumb($this->filePath, $this->fileName, $targetWdith, $targetHeight, $targetRatio);
      } else {
        $this->printContent(false);
      }
    } else {
      if (file_exists($this->filePath)) {
        $this->printContent(true);
      } else {
        echo "";
      }
    }
  }
}
