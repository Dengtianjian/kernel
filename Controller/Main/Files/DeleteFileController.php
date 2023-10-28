<?php

namespace kernel\Controller\Main\Files;

use kernel\Foundation\Controller\Controller;
use kernel\Service\FileStoreService;

class DeleteFileController extends Controller
{
  public function data($fileId)
  {
    return FileStoreService::deleteFile($fileId);
  }
}
