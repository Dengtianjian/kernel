<?php

namespace kernel\Platform\DiscuzX\Foundation;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Str;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File;
use kernel\Foundation\File\FileHelper;
use kernel\Foundation\HTTP\Response\ResponseView;
use kernel\Foundation\Response;
use kernel\Foundation\Store;

class DiscuzXView extends ResponseView
{
  /**
   * 响应视图类，构建函数渲染的是页面，相当于调用了page函数
   *
   * @param string $viewFile 渲染的视图文件，相对于$viewFileBaseDir目录
   * @param array $viewData 渲染的数据
   * @param string $viewFileBaseDir 视图文件所在的目录，相对于根目录
   * @param string $templateId 模板ID，用于缓存模板
   * @param string $viewFileDir 视图文件根目录，默认是基于F_APP_ROOT的，也就是当前项目的根目录，但是有时候可能需要渲染别的项目的视图文件，可通过该参数来修改
   */
  public function __construct($viewFile, $viewData = [], $viewFileBaseDir = "Views", $templateId = "page", $viewFileDir = null)
  {
    $this->page($viewFile, $viewData, $viewFileBaseDir, $templateId, $viewFileDir);
  }
  static function generateTemplatePath($viewFile, $templateId, $viewFileDirBaseProject, $viewFileDir = null)
  {
    if (!$viewFileDir) {
      $dir = F_APP_DIR;
    }
    if (strpos($viewFile, ".") === false) {
      return template($viewFile, implode("_", [
        F_APP_ID,
        $templateId
      ]), FileHelper::combinedFilePath($dir, $viewFileDirBaseProject));
    } else {
      return FileHelper::combinedFilePath($dir, $viewFileDirBaseProject, $viewFile);
    }
  }
  public function page($viewFile, $viewData, $viewFileDirBaseProject = "Views", $templateId = "page", $viewFileDir = null)
  {
    $this->viewFilePath = self::generateTemplatePath($viewFile, $templateId, $viewFileDirBaseProject, $viewFileDir);

    $this->templateId = $templateId;
    $this->viewFileBaseDir = "";

    $this->ResponseData = $viewData;
    return $this;
  }
  public function layout($layout = null, $viewData = [], $fileBaseDir = "Views/Layout", $templateId = "layout")
  {
    Store::set([
      "__View_LayoutRenderViewFile" => $this->viewFilePath,
      "__View_LayoutRenderViewData" => $this->ResponseData
    ]);

    $this->templateId = $templateId;
    $this->viewFilePath = template($layout, implode("_", [
      F_APP_ID,
      $templateId
    ]), FileHelper::combinedFilePath(F_APP_DIR, $fileBaseDir));

    $this->viewFileBaseDir = $fileBaseDir;
    $this->ResponseData = $viewData;

    return $this;
  }
  public static function render($viewFiles, $viewData = [], $returnName = null)
  {
    if (!is_array($viewFiles)) {
      $viewFiles = [$viewFiles];
    }

    foreach ($viewFiles as $file) {
      if (!\file_exists($file)) {
        throw new Exception("模板文件不存在（" . $file . "）", 500);
      }
    }

    if (!Arr::isAssoc($viewData)) {
      $viewData = [];
    }
    $DataKeys = implode(",", array_map(function ($item) {
      return "$" . $item . "=null";
    }, array_keys($viewData)));

    $TemplateIncludeCodes = [];
    foreach ($viewFiles as $file) {
      $code = <<<PHP
      include_once("$file")
PHP;
      $code = str_replace("\\", "\\\\", $code);
      array_push($TemplateIncludeCodes, trim($code));
    }

    if (count($TemplateIncludeCodes) === 0) return false;

    $returnCode = "return true";
    if (!empty($returnName)) {
      if (is_array($returnName)) {
        $items = [];
        foreach ($returnName as $name) {
          array_push($items, '"' . $name . '"=>$' . $name);
        }
        $items = implode(",", $items);
        $returnCode = 'return [' . $items . '];';
      } else {
        $returnCode = 'return $' . $returnName . ';';
      }
    }

    $TemplateIncludeCodes = implode("\n", $TemplateIncludeCodes);
    $CallFunctionCode = <<<EOT
return function($DataKeys)
{
  global \$_G;
  $TemplateIncludeCodes;
  $returnCode;
};
EOT;

    $fun = eval($CallFunctionCode);

    return call_user_func_array($fun, array_values($viewData));
  }
  static function renderAppPage($viewFiles, $viewFileBaseDir = "", $viewData = [], $templateId = "page", $returnName = null)
  {
    if (is_array($viewFiles)) {
      foreach ($viewFiles as &$fileItem) {
        $fileItem = self::generateTemplatePath($fileItem, $templateId, $viewFileBaseDir);
      }
    } else {
      $viewFiles = self::generateTemplatePath($viewFiles, $templateId, $viewFileBaseDir);
    }
    return self::render($viewFiles, $viewData, $returnName);
  }
  static function hook($viewFiles, $viewData, $returnName = "return")
  {
    return self::renderAppPage($viewFiles, "Views", $viewData, "hook", $returnName);
  }
}
