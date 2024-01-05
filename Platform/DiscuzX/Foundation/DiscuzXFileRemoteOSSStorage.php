<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\FileRemoteOSSStorage;
use kernel\Foundation\HTTP\URL;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;

class DiscuzXFileRemoteOSSStorage extends FileRemoteOSSStorage
{
  function __construct($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket, $SignatureKey)
  {
    parent::__construct($OSSPlatform, $SecretId, $SecretKey, $Region, $Bucket, $SignatureKey);

    $this->filesModel = new DiscuzXFilesModel();
    $this->FileStorageInstance = new DiscuzXFileStorage($SignatureKey);
  }
}
