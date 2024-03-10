<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\File\Driver\FileStorageDriver;
use kernel\Platform\DiscuzX\DiscuzXURL;
use kernel\Platform\DiscuzX\Model\DiscuzXFilesModel;

class DiscuzXFileStorageDriver extends FileStorageDriver
{
  public function __construct($SignatureKey, $Record = TRUE, $RoutePrefix = "files")
  {
    parent::__construct($SignatureKey, $Record, $RoutePrefix);
    $this->routePrefix = $RoutePrefix;

    if ($Record) {
      $this->filesModel = new DiscuzXFilesModel();
    }
  }
  public function uploadFile($File, $FileKey = null, $ownerId = null, $BelongsId = null, $BelongsType = null, $ACL = self::AUTHENTICATED_READ)
  {
    if (is_null($ownerId)) {
      $ownerId = getglobal("uid");
    }
    return parent::uploadFile($File, $FileKey, $ownerId, $BelongsId, $BelongsType, $ACL);
  }
  public function getFilePreviewURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE, $WithAccessControl = TRUE)
  {
    $AccessURL = new DiscuzXURL(F_BASE_URL);

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
      if (array_key_exists("auth", $URLParams)) {
        unset($URLParams['auth']);
      }
    }
    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "{$this->routePrefix}/{$FileKey}/preview";
    if ($WithAccessControl) {
      $URLParams['uri'] .= "/auth";
    }

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  public function getFileDownloadURL($FileKey, $URLParams = [], $Expires = 1800, $WithSignature = TRUE, $WithAccessControl = TRUE)
  {
    $AccessURL = new DiscuzXURL(F_BASE_URL);

    if ($WithSignature) {
      $URLParams = array_merge($URLParams, $this->getFileAuth($FileKey, $Expires, $URLParams, []));
      if (array_key_exists("auth", $URLParams)) {
        unset($URLParams['auth']);
      }
    }
    $URLParams['id'] = F_APP_ID;
    $URLParams['uri'] = "{$this->routePrefix}/{$FileKey}/download";
    if ($WithAccessControl) {
      $URLParams['uri'] .= "/auth";
    }

    $AccessURL->queryParam($URLParams);

    return $AccessURL->toString();
  }
  public function verifyRequestAuth($FileKey)
  {
    $Request = getApp()->request();
    $URLParams = $Request->query->some();
    unset($URLParams['id'], $URLParams['uri']);

    $RequestHeaders = $Request->header->some();

    return $this->verifyAuth($FileKey, $URLParams, $RequestHeaders, $Request->method);
  }
}
