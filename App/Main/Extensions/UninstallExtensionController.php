<?php

namespace kernel\App\Main\Extensions;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Request;
use kernel\Foundation\Controller;
use kernel\Foundation\File;
use kernel\Model\ExtensionsModel;

/**
 * 卸载扩展API
 */
class UninstallExtensionController extends Controller
{
  protected $Admin = 1;
  public function data(Request $request)
  {
    $extensionId = \addslashes($request->body("extension_id"));
    $EM = new ExtensionsModel();
    $extension = $EM->getByExtensionId($extensionId);
    if (empty($extension)) {
      return true;
    }
    $extension = $extension[0];
    $installFile = \F_ROOT . $extension['path'] . "/Iuu/Uninstall.php";
    if (\file_exists($installFile)) {
      $namespace = "\\" . $extension['plugin_id'] . "\\Extensions\\" . $extension['extension_id'] . "\\Iuu\\Uninstall";
      $instance = new $namespace();
      $instance->handle();
    }
    $result = $EM->where("extension_id", $extensionId)->delete(true);
    if ($result) {
      $extensionRootPath = \F_ROOT . $extension['path'];
      File::deleteDirectory($extensionRootPath);
    }
    return $result;
  }
}
