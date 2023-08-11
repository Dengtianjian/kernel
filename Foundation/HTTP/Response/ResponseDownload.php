<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\File;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\Output;

class ResponseDownload extends Response
{
  /**
   * 下载的文件绝对路径
   *
   * @var string
   */
  protected $filePath = null;
  /**
   * 下载的文件名称，也就是保存到下载者电脑的文件名称，需要包含文件扩展名
   *
   * @var string
   */
  protected $fileName = null;
  /**
   * 文件大小，字节
   *
   * @var int
   */
  protected $fileSize = null;
  /**
   * 文件扩展名
   *
   * @var string
   */
  protected $fileExtension = null;
  /**
   * 下载速率限制。如果值不为false，即开启了文件下载速率限制，单位是：千字节
   *
   * @var boolean|int
   */
  protected $DownloadRateLimit = false;
  /**
   * 请求体
   *
   * @var Request
   */
  protected $request = null;
  /**
   * 下载响应
   *
   * @param Request $R 请求体
   * @param string $filePath 下载的文件绝对路径
   * @param ?string $downloadFileName 下载到下载者设备时保存的文件名
   * @param boolean|int $rateLimit 下载速率限制，如果值不为false，即开启了下载速率，kb/秒，单位是：千字节
   */
  function __construct(Request $R, $filePath, $downloadFileName = null, $rateLimit = false)
  {
    $this->request = $R;

    $FileInfo = pathinfo($filePath);
    $this->filePath = $filePath;
    $this->fileName = $downloadFileName ?: $FileInfo['basename'];
    $this->fileExtension = $FileInfo['extension'];
    $this->fileSize = filesize($filePath);

    $this->DownloadRateLimit = $rateLimit;
  }
  /**
   * 输出文件内容
   *
   * @param boolean $readFile 未开启速率限制时，true就是以readfile函数来读取输出文件，否则是file_get_contents后再echo输出文件内容
   * @return void
   */
  protected function printContent($readFile = false)
  {
    if ($this->DownloadRateLimit) {
      flush();
      $file = fopen($this->filePath, "r");
      while (!feof($file)) {
        print fread($file, round($this->DownloadRateLimit * 1024));
        flush();
        sleep(1);
      }
      fclose($file);
    } else {
      if ($readFile) {
        readfile($this->filePath);
      } else {
        $content = file_get_contents($this->filePath);
        echo $content;
      }
    }
  }
  public function output()
  {
    $range = $this->request->header->get("Range") ?: false;

    $remainingLength = 0;
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $this->fileSize);
    if ($range) {
      $remainingLength = $this->fileSize - $range;
      header("Content-Range: bytes $range-$remainingLength/$this->fileSize");
      header('Content-Length: ' . $remainingLength);
    }

    header('Content-type: application/x-' . $this->fileExtension, true);
    header('Content-Disposition: attachment; filename=' . urlencode($this->fileName));

    if ($range) {
      header("HTTP/1.1 206 Partial Content");
      $content = file_get_contents($this->filePath, false, null, $range, $this->fileSize);
      echo $content;
    } else {
      if (file_exists($this->filePath)) {
        $this->printContent(true);
      } else {
        echo "";
      }
    }
  }
}
