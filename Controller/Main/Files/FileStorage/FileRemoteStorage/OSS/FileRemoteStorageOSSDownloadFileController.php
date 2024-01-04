<?php

namespace kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS;

use kernel\Controller\Main\Files\FileStorage\FileStorageDownloadFileController;
use kernel\Foundation\Config;
use kernel\Service\OSS\OSSService;

class FileRemoteStorageOSSDownloadFileController extends FileStorageDownloadFileController
{
  public function data($FileKey)
  {
    $Params = $this->getParams();

    $File = OSSService::getFileInfo($FileKey, $Params['signature'], $Params['signatureKey'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
    if ($File->error) return $File;

    $FileData = $File->getData();
    if ($FileData['remote']) {
      return $this->response->redirect(OSSService::getAccessURL($FileKey, $this->getRequestParams(), 600, NULL, "get", true, true)->getData(), 302);
    }

    return $this->response->download($FileData['fullPath']);
  }
}
