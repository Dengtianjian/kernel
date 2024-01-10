<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\Driver\FileStorageDriver;
use kernel\Platform\DiscuzX\DiscuzXURL;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;

class DiscuzXFileStorageDriver extends FileStorageDriver
{
  public function __construct($VerifyAuth, $SignatureKey, $Record = TRUE, $RoutePrefix = "files")
  {
    parent::__construct($VerifyAuth, $SignatureKey, $Record, $RoutePrefix);
    $this->routePrefix = $RoutePrefix;

    if ($Record) {
      $this->filesModel = new DiscuzXFilesModel();
    }
  }
  public function getFilePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new DiscuzXURL(F_BASE_URL);

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
    }
    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "{$this->routePrefix}/{$FileKey}/preview";

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  public function getFileDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE)
  {
    $AccessURL = new DiscuzXURL(F_BASE_URL);

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
    }
    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "{$this->routePrefix}/{$FileKey}/download";

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  public function verifyRequestAuth($FileKey, $Force = FALSE)
  {
    if (!$this->verifyAuth && !$Force) return true;

    $Request = getApp()->request();
    $URLParams = $Request->query->some();
    unset($URLParams['id'], $URLParams['uri']);

    $RequestHeaders = $Request->header->some();

    return $this->verifyAuth($FileKey, $URLParams, $RequestHeaders, $Request->method);
  }
}
