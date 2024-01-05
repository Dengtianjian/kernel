<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\FileRemoteStorage;
use kernel\Foundation\File\FileStorage;
use kernel\Foundation\HTTP\URL;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;

class DiscuzXFileRemoteStorage extends FileRemoteStorage
{
  function __construct($SignatureKey)
  {
    parent::__construct($SignatureKey);
    $this->filesModel = new DiscuzXFilesModel();

    $this->RemoteStorageInstance = $this;
    $this->FileStorageInstance = new DiscuzXFileStorage($SignatureKey);
  }
}
