<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\Output;
use kernel\Foundation\Store;

class ResponseView extends Response
{
  protected $viewFilePath = "";
  protected $viewFileBaseDir = "";
  protected $templateId = "";
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
  /**
   * 渲染页面
   *
   * @param string $viewFile 渲染的视图文件，相对于$viewFileBaseDir目录
   * @param array $viewData 渲染的数据
   * @param string $viewFileBaseDir 视图文件所在的目录，相对于当前项目的根目录
   * @param string $templateId 模板ID，用于缓存模板
   * @param string $viewFileDir 视图文件根目录，默认是基于F_APP_ROOT的，也就是当前项目的根目录，但是有时候可能需要渲染别的项目的视图文件，可通过该参数来修改
   * @return ResponseView
   */
  public function page($viewFile, $viewData, $viewFileDirBaseProject = "Views", $templateId = "page", $viewFileDir = null)
  {
    if (!$viewFileDir) {
      $viewFileDir = F_APP_ROOT;
    }
    $extension = ".php";
    if (strpos($viewFile, ".") !== false) {
      $extension = "";
    }
    $this->viewFilePath = File::genPath($viewFileDir, $viewFileDirBaseProject, $viewFile . $extension);
    if (!file_exists($this->viewFilePath)) {
      throw new Exception("模板文件不存在 - " . $this->viewFilePath, 500, 500, $this->viewFilePath);
    }
    $this->templateId = $templateId;
    $this->viewFileBaseDir = $viewFileDirBaseProject;

    $this->ResponseData = $viewData;

    return $this;
  }
  /**
   * 布局渲染
   *
   * @param string $layout 渲染的布局文件，相对于$viewFileBaseDir目录
   * @param array $viewData 渲染的数据
   * @param string $fileBaseDir 布局文件所在的目录，相对于根目录
   * @param string $templateId 模板ID，用于缓存模板
   * @return ResponseView
   */
  public function layout($layout = null, $viewData = [], $fileBaseDir = "Views/Layout", $templateId = "layout")
  {
    Store::set([
      "__View_LayoutRenderViewFile" => $this->viewFilePath,
      "__View_LayoutRenderViewData" => $this->ResponseData
    ]);

    $this->templateId = $templateId;
    $this->viewFilePath = File::genPath(F_APP_ROOT, $fileBaseDir, $layout . ".php");
    $this->viewFileBaseDir = $fileBaseDir;
    $this->ResponseData = $viewData;

    return $this;
  }
  /**
   * 获取渲染的配置信息
   *
   * @return array
   */
  public function getBody()
  {
    return [
      "filePath" => $this->viewFilePath,
      "baseDir" => $this->viewFileBaseDir,
      "templateId" => $this->templateId,
      "data" => $this->ResponseData
    ];
  }
  public function output()
  {
    foreach ($this->ResponseHeaders as $Header) {
      header($Header['key'] . ":" . $Header['value'], $Header['replace']);
    }
    http_response_code($this->ResponseStatusCode);
    $CallClass = get_called_class();
    return $CallClass::render($this->viewFilePath, $this->ResponseData, $this->templateId);
    // exit;
  }
  /**
   * 渲染模板
   *
   * @param string|string[] $viewFiles 渲染的模板文件绝对路径，或者字符串数组，里面存在渲染的模板文件绝对路径
   * @param array $viewData 渲染的数据
   * @return void
   */
  public static function render($viewFiles, $viewData = [])
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
    $TemplateIncludeCodes = implode("\n", $TemplateIncludeCodes);
    $CallFunctionCode = <<<PHP
return function($DataKeys)
{
  $TemplateIncludeCodes;
  return true;
};
PHP;

    $fun = eval($CallFunctionCode);

    return call_user_func_array($fun, array_values($viewData));
  }
  /**
   * 渲染App的模板，模板文件相对于当前运行的应用根目录
   *
   * @param string|string[] $viewFiles 渲染的模板文件名称（相对于$viewFileBaseDir），或者字符串数组，里面存在渲染的模板文件文件名称，都相对于$viewFileBaseDir值
   * @param string $viewFileBaseDir 文件所在的文件夹，相对于当前运行的应用根目录
   * @param array $viewData 渲染的数据
   * @param string $templateId 模板ID
   * @return bool
   */
  static function renderAppPage($viewFiles, $viewFileBaseDir = "", $viewData = [], $templateId = "page")
  {
    if (is_array($viewFiles)) {
      foreach ($viewFiles as &$fileItem) {
        $fileItem = File::genPath(F_APP_ROOT, $viewFileBaseDir, "$fileItem.php");
      }
    } else {
      $viewFiles = File::genPath(F_APP_ROOT, $viewFileBaseDir, "$viewFiles.php");
    }
    return static::render($viewFiles, $viewData, $templateId);
  }
  /**
   * 注入到布局模板内
   * 用于layout方法使用的模板文件内，用layout方法使用的模板内直接调用该方法即可
   *
   * @return bool
   */
  public static function inject()
  {
    $PageFilePath = Store::get("__View_LayoutRenderViewFile");
    $RenderData = Store::get("__View_LayoutRenderViewData");
    Store::remove("__View_LayoutRenderViewFile");
    Store::remove("__View_LayoutRenderViewData");

    return static::render($PageFilePath, $RenderData, "inject");
  }
  /**
   * 渲染模板组件
   *
   * @param string|string[] $viewFiles 组件文件名（相对于$viewFileBaseDir值）或者组件文件名数组，也是相对于$viewFileBaseDir
   * @param array $viewData 渲染组件所需数据
   * @param string $viewFileBaseDir 组件所在的文件夹，相对于项目根目录
   * @param string $templateId 模板ID
   * @return bool
   */
  public static function section($viewFiles, $viewData = [], $viewFileBaseDir = "Views", $templateId = "section")
  {
    return static::renderAppPage($viewFiles, $viewFileBaseDir, $viewData, $templateId);
  }
}
