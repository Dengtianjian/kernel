<?php

namespace kernel\App\Main\Extensions;


use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Request;
use kernel\Foundation\Extension\ExtensionProvisioner;
use kernel\Foundation\Extension\Extensions;
use kernel\Platform\DiscuzX\Foundation\DiscuzXLang;
use kernel\Foundation\Response;
use kernel\Model\ExtensionsModel;

/**
 * 安装扩展API
 */
class InstallExtensionController extends AuthController
{
  public $Admin = true;
  public function data($request)
  {
    $extensionId = \addslashes($request->params("extension_id"));
    $EM = new ExtensionsModel();
    $extension = $EM->getByExtensionId($extensionId);
    if (empty($extension)) {
      Response::error(404, 404001, DiscuzXLang::value("kernel/extensionNotExists"));
    }
    $extension = $extension[0];
    if ($extension['installed'] && $extension['install_time']) {
      Response::error(400, 400001, DiscuzXLang::value("kernel/extensionDoNotInstall"));
    }
    $extensionConfig = Extensions::config($extension['extension_id'], $extension['path']);

    $ext = new ExtensionProvisioner($extension['plugin_id'], $extension['extension_id'], NULL);
    $ext->install()->runInstallSql()->cleanInstall();
    $EM->where("extension_id", $extension['extension_id'])->where("plugin_id", $extension['plugin_id'])->update([
      "install_time" => time(),
      "installed" => 1,
      "local_version" => $extensionConfig['version']
    ])->save();

    return true;
  }
}
