<?php

namespace kernel\Controllers\Main\Files;

use kernel\Providers\Storage\Storage;
use kernel\Foundation\Controller\Controller;

class DeleteFileController extends Controller
{
  public function data(string $fileKey)
  {
    $storage = $this->app()->make(Storage::class);
    if (!$storage->authorizeOperation($fileKey, "write", "delete")) return $storage->return();
    if (!$storage->exists($fileKey)) return true;

    return $storage->delete($fileKey);
  }
}
