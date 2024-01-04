<?php

namespace kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS;

use kernel\Controller\Main\Files\FileStorage\FileStorageAccessFileController;
use kernel\Foundation\Config;
use kernel\Service\OSS\OSSService;

class FileRemoteStorageOSSAccessFileController extends FileStorageAccessFileController
{
  public function data($FileKey)
  {
    $Params = $this->getParams();

    $File = OSSService::getFileInfo($FileKey, $Params['signature'], $Params['signatureKey'], null, $Params['URLParams'], $Params['headers'], $this->request->method);
    if ($File->error) return $File;

    $FileData = $File->getData();
    if ($FileData['remote']) {
      return $this->response->redirect(OSSService::getAccessURL($FileKey, $this->getRequestParams())->getData(), 302);
    }

    return $this->response->file($FileData['fullPath']);
  }
}
