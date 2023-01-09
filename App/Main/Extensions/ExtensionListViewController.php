<?php

namespace kernel\App\Main\Extensions;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Extension\Extensions;
use kernel\Foundation\Lang;
use kernel\Foundation\Request;
use kernel\Foundation\Response;
use kernel\Foundation\Store;
use kernel\Foundation\View;
use kernel\Model\ExtensionsModel;

/**
 * 扩展列表页
 */
class ExtensionListViewController extends AuthController
{
  protected $Admin = true;
  public function data($request)
  {
    $extensions = Extensions::scanDir("source/plugin/" . Store::getApp("id"));
    $extensionIds = array_keys($extensions);
    $EM = new ExtensionsModel();
    $DBExtensions = $EM->getByExtensionId($extensionIds);
    $DBExtensions = Arr::indexToAssoc($DBExtensions, "extension_id");
    $insertNewData = [];
    $now = time();
    $pluginId = Store::getApp("id");
    foreach ($extensions as $id => &$extension) {
      if ($DBExtensions[$id]) {
        unset($extension['id']);
        $extension = \array_merge($extension, $DBExtensions[$id]);
      } else {
        $insertData = [
          "created_time" => $now,
          "install_time" => 0,
          "upgrade_time" => 0,
          "local_version" => "",
          "plugin_id" => $pluginId,
          "extension_id" => $extension['id'],
          "enabled" => 0,
          "installed" => 0,
          "path" => $extension['root'],
          "parent_id" => $extension['parent'] ? $extension['parent'] : 0,
          "name" => $extension['name']
        ];
        array_push($insertNewData, $insertData);
        $extension = \array_merge($extension, $insertData);
      }
    }
    if (count($insertNewData)) {
      $EM->batchInsert(array_keys($insertNewData[0]), $insertNewData)->save();
    }

    View::title(Lang::value("kernel/extension_list"));
    Response::success([
      "extensions" => $extensions,
      "extensionCount" => count($extensions)
    ]);
  }
}
