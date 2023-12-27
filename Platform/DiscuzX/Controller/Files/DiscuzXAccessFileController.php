<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Controller\Main\Files\AccessFileController;
use kernel\Foundation\Config;
use kernel\Foundation\File\FileHelper;
use kernel\Platform\DiscuzX\Service\DiscuzXFileStorageService;

class DiscuzXAccessFileController extends AccessFileController
{
  public function data($FileKey)
  {
    $SignatureKey = Config::get("signatureKey") ?: "";
    $Signature = $this->query->get("signature");
    $Headers = $this->request->header->some();
    $URLParams = $this->request->query->some();
    unset($URLParams['id'], $URLParams['uri']);

    $authId = null;
    if (array_key_exists("authId", $URLParams)) {
      $authId = getglobal("uid");
    }

    $File = DiscuzXFileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $authId);
    if ($File->error) return $File;
    $FileInfo = $File->getData();

    if (array_key_exists("authId", $FileInfo)) {
      global $_G;
      if (FileHelper::isImage($FileInfo["filePath"])) {
        if ($_G['group']['allowgetimage'] == "0" && $_G['adminid'] != 1) {
          if ($FileInfo['authId'] && $FileInfo['authId'] != $_G['uid']) {
            showmessage("抱歉，您没有权限获取图片信息");
          }
        }
      } else if ($_G['group']['allowgetattach'] == "0") {
        if ($_G['adminid'] != 1) {
          if ($FileInfo['authId'] && $FileInfo['authId'] != $_G['uid']) {
            showmessage("抱歉，您没有权限获取附件信息");
          }
        }
      }
    }

    return $this->response->file($FileInfo["filePath"]);
  }
}
