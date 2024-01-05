<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\File\FileHelper;
use kernel\Foundation\HTTP\Request;

class ResponseFile extends ResponseDownload
{
  /**
   * 预览图片的质量
   *
   * @var integer
   */
  private $imageQuality = null;
  /**
   * 文件格式输出响应
   *
   * @param Request $R 请求体
   * @param string $filePath 下载的文件绝对路径
   * @param ?string $downloadFileName 下载到下载者设备时保存的文件名
   * @param int $imageQuality 如果是图片类型文件，该值将影响输出的图片质量
   */
  public function __construct(Request $R, $filePath, $downloadFileName = null, $imageQuality = null)
  {
    $this->imageQuality = $imageQuality;
    parent::__construct($R, $filePath, $downloadFileName, false);
  }
  private function createThumb($filePath, $fileName, $targetWdith, $targetHeight, $targetRatio, $NewExtension = null)
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
      if ($targetRatio > 1) {
        $targetRatio = doubleval("0.$targetRatio");
      }
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

    $quality = $this->request->query->get("q") ?: $this->imageQuality;
    $targetExt = $NewExtension ?: $targetExt;
    $fileName = substr($fileName, 0, strrpos($fileName, ".")) . "." . $targetExt;
    switch ($targetExt) {
      case "jpg":
      case "jpeg":
        imagejpeg($targetImage, null, $quality);
        break;
      case "png":
        $NumberList = [10 => 0, 9 => 1, 8 => 2, 7 => 3, 6 => 4, 5 => 5, 4 => 6, 3 => 7, 2 => 8, 1 => 9, 0 => 9];
        if ($quality !== -1) {
          $firstStr = $quality > 99.99 ? substr($quality, 0, 2) : substr($quality, 0, 1);
          $quality = substr_replace($quality, $NumberList[$firstStr], 0, 1);
        }

        if ($quality <= -1) {
          $quality = -1;
        } else if ($quality > 9) {
          $quality = doubleval("0.$quality") * 10;
        } else if ($quality < 1) {
          $quality = $quality * 10;
        }

        imagepng($targetImage, null, $quality);
        break;
      case "gif":
        imagegif($targetImage);
        break;
      case "bmp":
        imagebmp($targetImage);
        break;
      default:
        imagewebp($targetImage, null, $quality);
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
    $PathInfo = pathinfo($this->filePath);

    if (FileHelper::isImage($this->filePath)) {
      if ($this->request->query->has("w") || $this->request->query->has("h") || $this->request->query->has("r") || $this->request->query->has("q") || $this->request->query->get("ext")) {
        header_remove("Content-Length");
        $imageInfo = getimagesize($this->filePath);
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $targetWdith = $this->request->query->get("w") ?: false;
        $targetHeight = $this->request->query->get("h") ?: false;
        $targetRatio = $this->request->query->get("r") ?: false;
        $outputExtension = $this->request->query->get("ext") ?: false;
        if ($outputExtension) {
          header('Content-Disposition: inline; filename=' . urlencode($PathInfo['filename'] . ".{$outputExtension}"));
          header("Content-type: image/{$outputExtension};", true);
        }

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

        $this->createThumb($this->filePath, $this->fileName, $targetWdith, $targetHeight, $targetRatio, $outputExtension);
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
