<?php

namespace kernel\Foundation\File;

use kernel\Foundation\BaseObject;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\HTTP\URL;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class FilesRemote extends BaseObject
{
  protected $fileRemoteDriver = null;
  public function __construct($Driver)
  {
    $this->fileRemoteDriver = $Driver;
  }
  function getFileInfo($FileKey)
  {
    return $this->fileRemoteDriver->getFileInfo($FileKey);
  }
  function getFileAuth($FileKey, $Expires = 600, $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return $this->fileRemoteDriver->getFileAuth($FileKey, $Expires, $URLParams, $Headers, $HTTPMethod);
  }
  function getFilePreviewURL($FileKey, $URLParams = [])
  {
    return $this->fileRemoteDriver->getFileAuth($FileKey, $URLParams);
  }
  function getFileDownloadURL($FileKey, $URLParams = [])
  {
    return $this->fileRemoteDriver->getFileAuth($FileKey, $URLParams);
  }
  function deleteFile($FileKey)
  {
    return $this->fileRemoteDriver->deleteFile($FileKey);
  }
}
