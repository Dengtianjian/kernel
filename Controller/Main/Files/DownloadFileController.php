<?php

namespace kernel\Controller\Main\Files;

use kernel\Facades\Storage;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\FileSystem\Storage\FileStorage;

class DownloadFileController extends Controller
{
  public function data($fileKey)
  {
    if (!Storage::authorizeOperation($fileKey, "read")) return Storage::return();

    $accessControl = FileStorage::AUTHENTICATED_READ;
    if (Storage::dataSave()) {
      $file = Storage::model()->where("key", $fileKey)->first();
    } else {
      $file = Storage::get($fileKey);
      if (Storage::isError()) return Storage::return();
    }

    if (!$file) {
      return $this->fail(404, 404, "文件不存在");
    }

    $querys = $this->query();
    $urlParams = [];
    foreach ($querys as $key => $value) {
      if (!in_array($key, ["sign-algorithm", "sign-time", "key-time", "header-list", "signature", "url-param-list"])) {
        $urlParams[$key] = $value;
      }
    }

    $disk = Storage::disk($file['disk']);
    if ($file['disk'] !== "local") {
      if (!$disk) {
        return $this->fail(500, 500, "抱歉，当前文件无法下载", "文件所属存储平台未实例化");
      }

      $url = $disk->url($fileKey, $urlParams);
      if (!$url) return $this->response->error(500, 500, "下载文件失败", "获取到的远程文件URL为空");

      return $this->response->redirect($url, 302);
    } else {
      $filePath = Path::join(Path::storage(), $fileKey);
      if (!file_exists($filePath)) {
        return $this->response->error(500, 500, "文件不存在", "文件实体不存在");
      }

      return $this->response->download($filePath, $file['source_file_name']);
    }
  }
}
