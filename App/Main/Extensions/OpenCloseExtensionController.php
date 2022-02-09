<?php

namespace kernel\App\Main\Extensions;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Request;
use kernel\Foundation\Controller;
use kernel\Foundation\Lang;
use kernel\Foundation\Response;
use kernel\Model\ExtensionsModel;

/**
 * 开启和关闭扩展API
 */
class OpenCloseExtensionController extends Controller
{
  protected $Admin = 1;
  public function data(Request $request)
  {
    $extensionId = \addslashes($request->body("extension_id"));
    $enabled = $request->body("enabled");
    $EM = new ExtensionsModel();
    $extension = $EM->getByExtensionId($extensionId);
    if (empty($extension)) {
      Response::error(404, 404001, Lang::value("extensionNotExists"));
    }
    $extension = $extension[0];
    $extensionRootPath = \F_ROOT . $extension['path'];
    $mainFilePath = $extensionRootPath . "/Main.php";
    if (!is_dir($extensionRootPath) || !\file_exists($mainFilePath)) {
      Response::error(500, 500001, Lang::value("extensionFileCorrupted"));
    }
    if ($enabled == 1 && $extension['enabled'] == 1) {
      Response::error(400, 400001, Lang::value("extensionAlreadyOn"));
    }
    if ($enabled == 0 && $extension['enabled'] == 0) {
      Response::error(400, 400001, Lang::value("extensionClosed"));
    }
    $EM->where("extension_id", $extensionId)->update([
      "enabled" => $enabled
    ])->save();
    return true;
  }
}
