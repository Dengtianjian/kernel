<?php

namespace gstudio_kernel\App\Main\Extensions;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Controller\AuthController;
use gstudio_kernel\Foundation\Request;
use gstudio_kernel\Foundation\Lang;
use gstudio_kernel\Foundation\Response;
use gstudio_kernel\Model\ExtensionsModel;

/**
 * 开启和关闭扩展API
 */
class OpenCloseExtensionController extends AuthController
{
  protected $Admin = 1;
  public function data($request)
  {
    $extensionId = \addslashes($request->params("extension_id"));
    $enabled = $request->params("enabled");
    $EM = new ExtensionsModel();
    $extension = $EM->getByExtensionId($extensionId);
    if (empty($extension)) {
      Response::error(404, 404001, Lang::value("kernel/extensionNotExists"));
    }
    $extension = $extension[0];
    $extensionRootPath = \DISCUZ_ROOT . $extension['path'];
    $mainFilePath = $extensionRootPath . "/Main.php";
    if (!is_dir($extensionRootPath) || !\file_exists($mainFilePath)) {
      Response::error(500, 500001, Lang::value("kernel/extensionFileCorrupted"));
    }
    if ($enabled == 1 && $extension['enabled'] == 1) {
      Response::error(400, 400001, Lang::value("kernel/extensionAlreadyOn"));
    }
    if ($enabled == 0 && $extension['enabled'] == 0) {
      Response::error(400, 400001, Lang::value("kernel/extensionClosed"));
    }
    $EM->where("extension_id", $extensionId)->update([
      "enabled" => $enabled
    ])->save();
    return true;
  }
}
