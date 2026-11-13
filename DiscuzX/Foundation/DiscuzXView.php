<?php

namespace kernel\DiscuzX\Foundation;
use kernel\Foundation\FileSystem\Path;


use kernel\Foundation\Application\Applications;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\HTTP\Response\ResponseView;

/**
 * DiscuzX 视图响应类
 *
 * 负责把视图模板渲染为页面/片段，并把数据以局部变量形式注入视图。
 *
 * 安全约定（SEC-01）：视图渲染全程在「数据→视图」的变量注入语义下进行，不做 eval 拼接动态代码；
 * 渲染作用域通过闭包隔离，仅暴露传入的数据与 Discuz 全局 $_G，不影响其他文件。
 */
class DiscuzXView extends ResponseView
{
  /**
   * 响应视图类，构建函数渲染的是页面，相当于调用了page函数
   *
   * @param string $viewFile 渲染的视图文件，相对于$viewFileBaseDir目录
   * @param array $viewData 渲染的数据
   * @param string $viewFileBaseDir 视图文件所在的目录，相对于根目录
   * @param string $templateId 模板ID，用于缓存模板
   * @param string $viewFileDir 视图文件根目录，默认是基于Path::root()的，也就是当前项目的根目录，但是有时候可能需要渲染别的项目的视图文件，可通过该参数来修改
   */
  public function __construct($viewFile, $viewData = [], $viewFileBaseDir = "Views", $templateId = "page", $viewFileDir = null)
  {
    $this->page($viewFile, $viewData, $viewFileBaseDir, $templateId, $viewFileDir);
  }
  /**
   * 解析视图文件路径
   *
   * - $viewFile 不含 "."：视为模板标识，走 Discuz 原生 template() 编译缓存（按「应用ID_模板ID」命名）
   * - $viewFile 含 "."：视为相对路径，直接拼接为物理文件路径
   *
   * @param string $viewFile 视图标识或相对路径
   * @param string $templateId 模板ID，用于 template() 缓存命名
   * @param string $viewFileDirBaseProject 视图文件所在基础目录
   * @param string|null $viewFileDir 覆盖根目录（默认 Path::dir()）
   * @return string 解析后的物理/编译文件路径
   */
  static function generateTemplatePath($viewFile, $templateId, $viewFileDirBaseProject, $viewFileDir = null)
  {
    $dir = $viewFileDir ?: Path::dir();
    if (strpos($viewFile, ".") === false) {
      return template($viewFile, implode("_", [
        Applications::defaultId(),
        $templateId
      ]), Path::join($dir, $viewFileDirBaseProject));
    } else {
      return Path::join($dir, $viewFileDirBaseProject, $viewFile);
    }
  }
  /**
   * 设定要渲染的页面视图与数据（构造时即调用）
   *
   * 仅记录视图路径与数据到实例属性，真正的 include 渲染交由 render() 执行。
   *
   * @param string $viewFile 视图标识或相对路径
   * @param array $viewData 注入视图的数据
   * @param string $viewFileDirBaseProject 视图基础目录，默认 Views
   * @param string $templateId 模板ID，默认 page
   * @param string|null $viewFileDir 覆盖根目录
   * @return static
   */
  public function page($viewFile, $viewData, $viewFileDirBaseProject = "Views", $templateId = "page", $viewFileDir = null)
  {
    $this->viewFilePath = self::generateTemplatePath($viewFile, $templateId, $viewFileDirBaseProject, $viewFileDir);

    $this->templateId = $templateId;
    $this->viewFileBaseDir = "";

    $this->responseData = $viewData;
    return $this;
  }
  /**
   * 设定布局视图
   *
   * 把当前页面视图文件与数据暂存到全局 _STORE，再将视图路径切换为布局文件；
   * 布局文件内通过对应机制取回页面片段后再统一输出。
   *
   * @param string|null $layout 布局视图标识
   * @param array $viewData 布局级数据
   * @param string $fileBaseDir 布局目录，默认 Views/Layout
   * @param string $templateId 模板ID，默认 layout
   * @return static
   */
  public function layout($layout = null, $viewData = [], $fileBaseDir = "Views/Layout", $templateId = "layout")
  {
    $GLOBALS['_STORE']['__View_LayoutRenderViewFile'] = $this->viewFilePath;
    $GLOBALS['_STORE']['__View_LayoutRenderViewData'] = $this->responseData;

    $this->templateId = $templateId;
    $this->viewFilePath = template($layout, implode("_", [
      Applications::defaultId(),
      $templateId
    ]), Path::join(Path::dir(), $fileBaseDir));

    $this->viewFileBaseDir = $fileBaseDir;
    $this->responseData = $viewData;

    return $this;
  }
  /**
   * 渲染视图文件并注入数据
   *
   * 安全说明（SEC-01）：历史实现用 eval 把数据键名 / 视图路径 / 返回名拼进动态函数代码，存在代码注入面。
   * 现改为「闭包 + extract(EXTR_SKIP) + include」：
   *  - 数据键名先经正则 /^[a-zA-Z_][a-zA-Z0-9_]*$/ 白名单过滤，非法键名不会成为变量；
   *  - 渲染逻辑包在匿名函数内，视图只能访问传入的 $vars 与 global $_G，碰不到 render 内部变量，也不影响其他文件；
   *  - 视图路径仅用于 include 真实文件（已 file_exists 校验），不再进入代码字符串。
   *
   * @param string|array $viewFiles 单个或一组视图文件路径（应已含真实/编译路径，通常由 generateTemplatePath 产出）
   * @param array $viewData 注入视图的数据；键名需为合法 PHP 变量名，否则被忽略
   * @param string|array|null $returnName
   *    - null：渲染并直接输出，返回 true
   *    - string：返回名为该值的视图变量
   *    - array：返回 {name => 对应视图变量} 映射（常用于 hook 取回 $return 片段）
   * @return bool|mixed|array
   * @throws Error 视图文件不存在时抛 500
   */
  public static function render($viewFiles, $viewData = [], $returnName = null)
  {
    if (!is_array($viewFiles)) {
      $viewFiles = [$viewFiles];
    }

    foreach ($viewFiles as $file) {
      if (!\file_exists($file)) {
        throw new Error("模板文件不存在（" . $file . "）", 500);
      }
    }

    if (!Arr::isAssoc($viewData)) {
      $viewData = [];
    }

    // 仅合法 PHP 变量名可成为视图变量，杜绝 extract 注入/告警
    $safeVars = [];
    foreach ($viewData as $k => $v) {
      if (is_string($k) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) {
        $safeVars[$k] = $v;
      }
    }

    // 闭包隔离：视图只能访问 $safeVars + global $_G，碰不到 render 内部变量，也不影响别的文件
    $render = function (array $files, array $vars, $returnName) {
      global $_G;
      extract($vars, EXTR_SKIP);          // EXTR_SKIP：视图数据不会覆盖 $returnName 等闭包参数
      foreach ($files as $file) {
        include $file;                    // 去 include_once，避免同文件二次渲染失效
      }
      if (!empty($returnName)) {
        if (is_array($returnName)) {
          $items = [];
          foreach ($returnName as $name) {
            $items[$name] = (isset($$name) ? $$name : null);
          }
          return $items;
        }
        return (isset($$returnName) ? $$returnName : null);
      }
      return true;
    };

    return $render($viewFiles, $safeVars, $returnName);
  }
  /**
   * 应用页面渲染入口：先把视图标识解析为真实路径，再交给 render()
   *
   * @param string|array $viewFiles 视图标识或相对路径
   * @param string $viewFileBaseDir 视图基础目录
   * @param array $viewData 注入数据
   * @param string $templateId 模板ID，默认 page
   * @param string|array|null $returnName 见 render() 说明
   * @return bool|mixed|array
   */
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
  /**
   * 渲染 hook 视图（模板钩子片段）
   *
   * 默认以 "return" 作为返回变量名，取回视图内定义的 $return 片段内容。
   *
   * @param string|array $viewFiles 视图标识或相对路径
   * @param array $viewData 注入数据
   * @param string $returnName 返回变量名，默认 return
   * @return bool|mixed|array
   */
  static function hook($viewFiles, $viewData, $returnName = "return")
  {
    return self::renderAppPage($viewFiles, "Views", $viewData, "hook", $returnName);
  }
}
