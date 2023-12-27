<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Foundation\Config;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXFileStorageService;
use kernel\Traits\FileControllerTrait;

class DiscuzXDeleteFileController extends DiscuzXController
{
  use FileControllerTrait;

  public function data($FileKey)
  {
    $Signature = $this->query->get("signature");
    $URLParams = $this->request->query->some();

    global $_G;
    $authId = null;
    if ($_G['adminid'] != 1) {
      if (array_key_exists("authId", $URLParams)) {
        $authId = getglobal("uid");
      }
      if (!$Signature) {
        return $this->response->error(403, 403, "抱歉，您没有权限删除该文件");
      }
    } else {
      $Signature = null;
      unset($URLParams['authId']);
    }

    $SignatureKey = Config::get("signatureKey") ?: "";
    $HTTPMethod = $this->request->method;
    $Headers = $this->request->header->some();
    unset($URLParams['id'], $URLParams['uri']);

    $authId = null;
    if (array_key_exists("authId", $URLParams)) {
      $authId = getglobal("uid");
    }

    return DiscuzXFileStorageService::deleteFile($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $authId, $HTTPMethod);
  }
}
