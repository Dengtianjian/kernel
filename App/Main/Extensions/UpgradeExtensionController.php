<?php

namespace kernel\App\Main\Extensions;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Request;
use kernel\Foundation\Controller;
use kernel\Foundation\Extension\ExtensionIuu;
use kernel\Foundation\Extension\Extensions;
use kernel\Foundation\Lang;
use kernel\Foundation\Response;
use kernel\Model\ExtensionsModel;

/**
 * 更新升级扩展API
 */
class UpgradeExtensionController extends Controller
{
  protected $Admin = 1;
  public function data(Request $request)
  {
    $extensionId = \addslashes($request->body("extension_id"));
    $EM = new ExtensionsModel();
    $extension = $EM->getByExtensionId($extensionId);
    if (empty($extension)) {
      Response::error(404, 404001, Lang::value("extensionNotExists"));
    }
    $extension = $extension[0];
    $extensionConfig = Extensions::config($extension['extension_id']);
    if (\version_compare($extension['local_version'], $extensionConfig['version']) !== -1) {
      Response::error(400, 400001, Lang::value("extensionNoNeedToUpgrade"));
    }

    $ext = new ExtensionIuu($extension['plugin_id'], $extension['extension_id'], NULL);
    $ext->upgrade()->runUpgradeSql()->cleanUpgrade();
    $EM->where("extension_id", $extension['extension_id'])->where("plugin_id", $extension['plugin_id'])->update([
      "upgrade_time" => time(),
      "local_version" => $extensionConfig['version']
    ])->save();

    return true;
  }
}
