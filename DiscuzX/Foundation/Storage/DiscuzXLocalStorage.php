<?php

namespace kernel\DiscuzX\Foundation\Storage;

use kernel\Foundation\FileSystem\Storage\Drivers\LocalStorage;
use kernel\DiscuzX\Foundation\DiscuzXPath;
class DiscuzXLocalStorage extends LocalStorage
{
  public function __construct()
  {
    $this->name = "local";
    $this->basePath = DiscuzXPath::storage();
  }
}
