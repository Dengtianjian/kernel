<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Response\ResponseFile;
use kernel\Service\FileStoreService;

class AccessFileController extends Controller
{
  public function data($FileId)
  {
    $decodeData = FileStoreService::decodeFileId($FileId);
    if ($decodeData->error) {
      return $this->$decodeData->errorMessage();
    }
    $decodeData = $decodeData->getData();
    // if (isset($decodeData['auth']) && $decodeData['auth']) {

    // }

    return new ResponseFile($this->request, $decodeData["filePath"]);
  }
}
