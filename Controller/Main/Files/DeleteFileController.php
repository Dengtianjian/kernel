<?php

namespace kernel\Controller\Main\Files;

use kernel\Facades\Storage;
use kernel\Foundation\Controller\Controller;

class DeleteFileController extends Controller
{
  public function data($fileKey)
  {
    if (!Storage::authorizeOperation($fileKey, "write")) return Storage::return();
    if (!Storage::exists($fileKey)) return true;

    return Storage::delete($fileKey);
  }
}
