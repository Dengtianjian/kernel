<?php

namespace kernel\App\Main\Extensions;

use kernel\Foundation\Arr;
use kernel\Foundation\Controller;
use kernel\Foundation\Extension\ExtensionIuu;
use kernel\Foundation\Extension\Extensions;
use kernel\Foundation\GlobalVariables;
use kernel\Foundation\Lang;
use kernel\Foundation\Request;
use kernel\Foundation\View;
use kernel\Model\ExtensionsModel;

/**
 * 扩展列表页
 */
class ExtensionListViewController extends Controller
{
  protected $Admin = true;
  public function data(Request $request)
  {
    $extensions = Extensions::scanDir("source/plugin/" . GlobalVariables::getGG("id"));
    $extensionIds = array_keys($extensions);
    $EM = new ExtensionsModel();
    $DBExtensions = $EM->getByExtensionId($extensionIds);
    $DBExtensions = Arr::valueToKey($DBExtensions, "extension_id");
    $insertNewData = [];
    $now = time();
    $pluginId = GlobalVariables::getGG("id");
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

    View::title(Lang::value("extension_list"));
    // View::systemDashboard("extensions/list", [
    //   "extensions" => $extensions,
    //   "extensionCount" => count($extensions)
    // ]);
  }
}
