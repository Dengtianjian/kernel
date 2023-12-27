<?php

namespace kernel\Platform\DiscuzX\Controller\Files;

use kernel\Foundation\Config;
use kernel\Foundation\File\FileHelper;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Service\DiscuzXFileStorageService;
use kernel\Traits\FileControllerTrait;

class DiscuzXAccessFileController extends DiscuzXController
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
      } else if (!$Signature) {
        showmessage("抱歉，您没有权限预览该文件");
      }
    } else {
      $Signature = null;
      unset($URLParams['authId']);
    }

    $SignatureKey = Config::get("signatureKey") ?: "";
    $Headers = $this->request->header->some();
    unset($URLParams['id'], $URLParams['uri']);

    $File = DiscuzXFileStorageService::getFileInfo($FileKey, $Signature, $SignatureKey, $URLParams, $Headers, $authId);
    if ($File->error) return $File;
    $FileInfo = $File->getData();

    if (array_key_exists("authId", $URLParams)) {
      if (FileHelper::isImage($FileInfo["fullPath"])) {
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

    return $this->response->file($FileInfo["fullPath"]);
  }
}
