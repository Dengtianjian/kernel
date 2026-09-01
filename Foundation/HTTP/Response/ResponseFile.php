<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\FileSystem\FileHelper;
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
   * HTTP 缓存控制
   */
  private $cacheControl = "no-cache";
  /**
   * HTTP 资源过期时间，秒级时间戳
   */
  private $httpExpires = null;
  /**
   * 文件格式输出响应
   *
   * @param Request $R 请求体
   * @param string $filePath 下载的文件绝对路径
   * @param ?string $downloadFileName 下载到下载者设备时保存的文件名
   * @param int $imageQuality 如果是图片类型文件，该值将影响输出的图片质量
   * @param string $cacheControl HTTP 缓存控制属性值
   * @param string $httpExpires HTTP 资源过期时间，秒级时间戳
   */
  public function __construct(Request $R, $filePath, $downloadFileName = null, $imageQuality = null, $cacheControl = "no-cache", $httpExpires = null)
  {
    $this->imageQuality = $imageQuality;
    $this->cacheControl = $cacheControl;
    $this->httpExpires = $httpExpires;
    parent::__construct($R, $filePath, $downloadFileName, false);
  }
  private function createThumb($filePath, $fileName, $targetWidth, $targetHeight, $targetRatio, $NewExtension = null)
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
        $sourceImage = imagecreatefrombmp($filePath);
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

    if ($sourceImage === false) {
      throw new \kernel\Foundation\Exception\Error("无法读取图片源文件 - " . $filePath, 500);
    }

    $imageInfo = getimagesize($filePath);
    if ($imageInfo === false) {
      throw new \kernel\Foundation\Exception\Error("无法获取图片尺寸 - " . $filePath, 500);
    }
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    if ($targetRatio) {
      if ($targetRatio > 1) {
        $targetRatio = doubleval("0.$targetRatio");
      }
      $targetWidth = $sourceWidth * $targetRatio;
      $targetHeight = $sourceHeight * $targetRatio;
    } else {
      if ($targetWidth === false && $targetHeight === false) {
        $targetWidth = $sourceWidth;
        $targetHeight = $sourceHeight;
      } else {
        if ($targetWidth && !$targetHeight) {
          $targetHeight = $sourceHeight / ($sourceWidth / $targetWidth);
        } else if ($targetHeight) {
          $targetWidth = $sourceWidth / ($sourceHeight / $targetHeight);
        }
      }
    }

    imagesavealpha($sourceImage, true);
    $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
    imagealphablending($targetImage, false);
    imagesavealpha($targetImage, true);

    imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

    $quality = $this->request->query->get("q") ?: $this->imageQuality;
    $targetExt = $NewExtension ?: $targetExt;
    $fileName = substr($fileName, 0, strrpos($fileName, ".")) . "." . $targetExt;
    switch ($targetExt) {
      case "jpg":
      case "jpeg":
        $quality = $quality === null ? 75 : $quality;
        imagejpeg($targetImage, null, $quality);
        break;
      case "png":
        if ($quality === null) {
          $quality = -1;
        } else {
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
        $quality = $quality === null ? 80 : $quality;
        imagewebp($targetImage, null, $quality);
        break;
    }

    imagedestroy($targetImage);
    imagedestroy($sourceImage);
  }
  protected function setCache($fileTag)
  {
    $fileTag = md5($fileTag);

    if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
      $Etag = $_SERVER['HTTP_IF_NONE_MATCH'];
      if ($fileTag === $Etag) {
        header("HTTP/1.1 304 Not Modified");
        return;
      }
    }
    header("Last-modified:" . gmdate("D, d M Y H:i:s", time()) . " GMT");
    header("etag: " . $fileTag);

    header("cache-control: {$this->cacheControl}");

    if ($this->httpExpires) {
      header("expires: " . gmdate("D, d M Y H:i:s", $this->httpExpires) . " GMT");
    }
  }
  public function output()
  {
    if (!file_exists($this->filePath)) {
      header("HTTP/1.1 404 Not Found");
      echo "";
      return;
    }

    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $this->fileSize);
    header('Content-Disposition: inline; filename="' . urlencode($this->fileName) . '"');
    header('Content-type: ' . mime_content_type($this->filePath) . ';', true);
    $PathInfo = pathinfo($this->filePath);

    if (FileHelper::isImage($this->filePath)) {
      if ($this->request->query->has("w") || $this->request->query->has("h") || $this->request->query->has("r") || $this->request->query->has("q") || $this->request->query->get("ext")) {
        header_remove("Content-Length");
        $imageInfo = getimagesize($this->filePath);
        if ($imageInfo === false) {
          throw new \kernel\Foundation\Exception\Error("无法获取图片尺寸 - " . $this->filePath, 500);
        }
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $targetWidth = $this->request->query->get("w") ?: false;
        $targetHeight = $this->request->query->get("h") ?: false;
        $targetRatio = $this->request->query->get("r") ?: false;
        $outputExtension = $this->request->query->get("ext") ?: false;
        if ($outputExtension) {
          header('Content-Disposition: inline; filename="' . urlencode($PathInfo['filename'] . ".{$outputExtension}") . '"');
          header("Content-type: image/{$outputExtension};", true);
        }

        $this->setCache($this->filePath . ":$sourceWidth-$sourceHeight-$targetWidth-$targetHeight-$targetRatio");

        $this->createThumb($this->filePath, $this->fileName, $targetWidth, $targetHeight, $targetRatio, $outputExtension);
      } else {
        $this->setCache($this->filePath);

        $this->printContent(false);
      }
    } else {
      $this->setCache($this->filePath);

      $this->printContent(true);
    }
  }
}
