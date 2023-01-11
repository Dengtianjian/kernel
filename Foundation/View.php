<?php

namespace gstudio_kernel\Foundation;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Data\Str;

class View
{
  private static $viewData = [];
  private static $outputHeaderHTML = [];
  private static $outputFooterHTML = ["<script>const FORMHASH=\"" . FORMHASH . "\";</script>"];

  /**
   * 渲染模板文件
   *
   * @param string|array $viewFiles 模板的文件名称。可数组或单一字符串
   * @param array $viewData? 渲染的数据
   * @param string $templateId? 模板唯一标识符
   * @param string $templateDir? 模板所在目录
   * @param boolean|string $hook?=false 传入字符串就是hook模板返回的变量名称，否则就不是hook
   * @return void
   */
  static function render($viewFiles, $viewData = [], $templateId = "",  $templateDir = "", $hookName = false)
  {
    if (is_array($viewFiles)) {
      foreach ($viewFiles as $file) {
        $filePath = File::genPath($templateDir, "$file.htm");
        if (!\file_exists($filePath)) {
          Response::error("VIEW_TEMPLATE_NOT_EXIST");
        }
      }
    } else {
      $filePath = File::genPath($templateDir, "$viewFiles.htm");
      if (!\file_exists($filePath)) {
        Response::error("VIEW_TEMPLATE_NOT_EXIST");
      }
    }

    $viewData = \array_merge(self::$viewData, $viewData);

    foreach ($viewData as $key => $value) {
      $GLOBALS[$key] = $value;
      global ${$key};
    }
    $GLOBALS["View"] = self::class;
    global $View;
    global $_STORE;
    global $_G;

    self::outputHeader();
    if (\is_array($viewFiles)) {
      foreach ($viewFiles as $file) {
        include_once template($file, $templateId, $templateDir);
      }
    } else {
      include_once template($viewFiles, $templateId, $templateDir);
    }

    foreach ($viewData as $key => $value) {
      unset($GLOBALS[$key]);
    }

    if ($hookName) {
      return ${$hookName};
    }
    return true;
  }
  static private function renderAppPage($viewFile, $viewFileBaseDir = "", $viewData = [], $templateId = "page", $hookName = false)
  {
    if (is_array($viewFile)) {
      foreach ($viewFile as &$fileItem) {
        $fileItem = "$viewFileBaseDir/$fileItem";
      }
    } else {
      $viewFile = "$viewFileBaseDir/$viewFile";
    }

    return self::render($viewFile, $viewData, $templateId, File::genPath("source/plugin", F_APP_ID), $hookName);
  }
  /**
   * 渲染页面
   *
   * @param string $viewFile $viewFile 模板的文件名称。可数组或单一字符串
   * @param array $viewData? 渲染的数据
   * @param string $templateId? 模板Id
   * @return void
   */
  static function page($viewFile, $viewData = [], $templateId = "page")
  {
    self::renderAppPage($viewFile, "Views", $viewData, $templateId);
    exit;
  }
  /**
   * 渲染块
   *
   * @param [type] $viewFile $viewFile 模板的文件名称。可数组或单一字符串
   * @param string $viewDirOrViewData? 文件的路径或者渲染的数据。传入的如果是数组就是渲染的数据，否则就是模板路径。基于根路径也就是当前插件的根目录下的Views文件夹
   * @param array $viewData? 渲染的数据
   * @param string $templateId? 模板Id
   * @return void
   */
  static function section($viewFile, $viewData = [], $templateId = "section")
  {
    return self::renderAppPage($viewFile, "Views", $viewData, $templateId);
  }

  /**
   * 渲染系统(kernel)页面
   *
   * @param string|array $viewFile 模板的文件名称。可数组或单一字符串
   * @param string $viewDirOrViewData? 文件的路径或者渲染的数据。传入的如果是数组就是渲染的数据，否则就是模板路径。基于根路径也就是核心插件的根目录下的Views文件夹
   * @param array $viewData? 渲染的数据
   * @param string $templateId? 模板Id
   * @return void
   */
  static function kernelPage($viewFile, $viewData = [], $templateId = "kernel_page")
  {
    self::render("Views/$viewFile", $viewData, $templateId, F_KERNEL_ROOT);
    exit;
  }
  /**
   * 渲染布局
   *
   * @param [string] $layout 布局文件名称
   * @param [string|array] $viewFile 页面文件名称或者布局文件所需要渲染的数据
   * @param string $viewDirOrViewData 
   * @param array $viewData 页面文件所需要渲染的数据
   * @param string $templateId 模板ID
   * @return void
   */
  static function layout($layout = null, $viewFile = null, $viewData = [], $fileBaseDir = "Views/Layout", $templateId = "layout")
  {
    if (is_array($viewFile)) {
      $viewData = $viewFile;
      $viewFile = null;
    }

    $layoutData = $viewData;
    if ($viewFile) {
      $layoutData = [
        "__pageFile" => $viewFile,
        "__pageData" => $viewData
      ];
      self::renderAppPage($layout, $fileBaseDir, $layoutData, $templateId);
    } else {
      self::renderAppPage($layout, $fileBaseDir, $layoutData, $templateId);
    }
    exit;
  }
  /**
   * 注入页面到layout里
   * 用在layout的文件里，当该文件是被layout所使用，就需要注入页面文件到layou里
   *
   * @return void
   */
  static function inject()
  {
    $pageFile = $GLOBALS['__pageFile'];
    $viewData = $GLOBALS['__pageData'];
    unset($GLOBALS['__pageFile']);
    unset($GLOBALS['__pageData']);
    return self::renderAppPage($pageFile, "Views",  $viewData, "inject");
  }
  /**
   * 添加渲染的数据到渲染的模板中
   *
   * @param array $data 关联索引的数组
   * @return void
   */
  static function addData($data)
  {
    self::$viewData = \array_merge(self::$viewData, $data);
  }
  /**
   * 设置页面标题
   *
   * @param string $titleSourceString 页面标题字符串。例如：{bbname}- - 首页 - {$keyword}
   * @param array $params? 替换字符串中的参数
   * @return void
   */
  static function title($titleSourceString, $params = [])
  {
    self::addData([
      "navTitle" => Str::replaceParams($titleSourceString, $params),
      "pageTitle" => Str::replaceParams($titleSourceString, $params),
    ]);
  }
  /**
   * 设置页面的keywords
   *
   * @param string $keywordSourceString 页面mate关键词值。例如：{bbname},Discuzx,{$keyword1}
   * @param array $params 替换字符串中的参数
   * @return void
   */
  static function keyword($keywordSourceString, $params = [])
  {
    self::addData([
      "metakeywords" => Str::replaceParams($keywordSourceString, $params),
      "pageKeyword" => Str::replaceParams($keywordSourceString, $params),
    ]);
  }
  /**
   * 设置页面的描述
   *
   * @param string $descriptionSourceString 描述字符串。例如：{bbname}是专业的DZX应用开发者，应用列表：{addonsUrl}
   * @param array $params 替换字符串中的参数
   * @return void
   */
  static function description($descriptionSourceString, $params = [])
  {
    self::addData([
      "metadescription" => Str::replaceParams($descriptionSourceString, $params),
      "pageDescription" => Str::replaceParams($descriptionSourceString, $params),
    ]);
  }
  /**
   * 模板的头部HTML
   *
   * @param string $html HTML代码片段
   * @return void
   */
  static function header($html = null)
  {
    \array_push(self::$outputHeaderHTML, $html);
  }
  static private function outputHeader()
  {
    if (count(self::$outputHeaderHTML)) {
      $outputHeader = \implode("\n", self::$outputHeaderHTML);
      self::$outputHeaderHTML = [];
      print_r($outputHeader);
    }
  }
  /**
   * 模板的头部HTML
   *
   * @param string $html HTML代码片段
   * @return void
   */
  static function footer($html = null)
  {
    \array_push(self::$outputFooterHTML, $html);
  }
  static function outputFooter()
  {
    if (count(self::$outputFooterHTML)) {
      $outputFooter = \implode("\n", self::$outputFooterHTML);
      self::$outputFooterHTML = [];
      print_r($outputFooter);
    }
  }
  static function hook($viewFile, $viewData = [], $hookName = "return")
  {
    return self::renderAppPage($viewFile, "Views", $viewData, "hook", $hookName);
  }
}
