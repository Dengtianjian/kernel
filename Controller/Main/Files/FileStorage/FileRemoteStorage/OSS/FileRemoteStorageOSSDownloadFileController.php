<?php

namespace kernel\Controller\Main\Files\FileStorage\FileRemoteStorage\OSS;

use kernel\Controller\Main\Files\FileStorage\FileStorageDownloadFileController;
use kernel\Foundation\Config;
use kernel\Service\OSS\OSSService;

class FileRemoteStorageOSSDownloadFileController extends FileStorageDownloadFileController
{
  public function data($FileKey)
  {
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();
    $Headers = $this->request->header->some();
    unset($URLParams['id'], $URLParams['uri']);

    $File = OSSService::getFileInfo($FileKey, $Signature, $SignatureKey, null, $URLParams, $Headers, $this->request->method);
    if ($File->error) return $File;

    $FileData = $File->getData();
    if ($FileData['remote']) {
      return $this->response->redirect(OSSService::getAccessURL($FileKey, [], 600, NULL, "get", true, true)->getData(), 302);
    }

    return $this->response->download($FileData['fullPath']);
  }
}
