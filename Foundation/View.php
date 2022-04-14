<?php

namespace kernel\Foundation;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Str;
use kernel\Foundation\Response;

class View
{
  private static $viewData = [];
  private static $outputHeaderHTML = [];
  private static $outputFooterHTML = [];

  /**
   * 渲染模板文件
   * global渲染的数据，载入渲染的模板文件，并且删除掉$GLOBALS中渲染的数据
   *
   * @param string|array $fileName 模板的文件名称。可数组或单一字符串
   * @param string $fileDir 文件的路径或者渲染的数据。传入的如果是数组就是渲染的数据，否则就是模板路径。
   * @param array $viewData渲染的数据
   * @param string $templateId? 模板唯一标识符
   * @return boolean 一直都是 true
   */
  static private function renderPage($fileName, $fileDir = "", $viewData = [], $templateId = "")
  {
    $viewData = \array_merge(self::$viewData, $viewData);
    foreach ($viewData as $key => $value) {
      global ${$key};
    }

    $baseDir = F_APP_ROOT . "/Views/$fileDir";
    self::outputHeader();
    if (\is_array($fileName)) {
      if (Arr::isAssoc($fileName)) {
        foreach ($fileName as $dir => $name) {
          if (is_array($name)) {
            foreach ($name as $fileNameItem) {
              include_once $baseDir . "/$dir/$fileNameItem.php";
            }
          } else {
            include_once $baseDir . "/$dir/$name.php";
          }
        }
      } else {
        foreach ($fileName as $name) {
          include_once $baseDir . "/$name.php";
        }
      }
    } else {
      include_once $baseDir . "/$fileName.php";
    }

    foreach ($viewData as $key => $value) {
      unset($GLOBALS[$key]);
    }

    return true;
  }
  /**
   * 渲染前做的事情
   * 主要是检查模板文件是否存在以及把渲染的数据加入到全局($GLOBALS)里
   *
   * @param string|array $viewFile 模板的文件名称。可数组或单一字符串
   * @param string $viewDirOrViewData? 文件的路径或者渲染的数据。传入的如果是数组就是渲染的数据，否则就是模板路径。
   * @param array $viewData? 渲染的数据
   * @param string $templateId? 模板唯一标识符
   * @param boolean $hook?=false 是否是hook插槽
   * @return void
   */
  static function render($viewFile, $viewDirOrViewData = "", $viewData = [], $templateId = "", $hook = false)
  {
    if (is_array($viewDirOrViewData)) {
      $viewData = $viewDirOrViewData;
      $viewDirOrViewData = "";
    }

    $viewData = \array_merge(self::$viewData, $viewData);
    if (count($viewData) > 0) {
      foreach ($viewData as $key => $value) {
        $GLOBALS[$key] = $value;
      }
    }

    $baseDir = F_APP_ROOT . "/Views";
    if ($viewDirOrViewData) {
      $baseDir .= "/$viewDirOrViewData";
    }

    if (\is_array($viewFile)) {
      if (Arr::isAssoc($viewFile)) {
        foreach ($viewFile as $dir => $name) {
          if (is_array($name)) {
            foreach ($name as $fileNameItem) {
              if (!\file_exists("$baseDir/$dir/$fileNameItem.php")) {
                Response::error("VIEW_TEMPLATE_NOT_EXIST");
              }
            }
          } else if (!\file_exists("$baseDir/$dir/$name.php")) {
            Response::error("VIEW_TEMPLATE_NOT_EXIST");
          }
        }
      } else {
        foreach ($viewFile as $name) {
          if (!\file_exists("$baseDir/$name.php")) {
            Response::error("VIEW_TEMPLATE_NOT_EXIST");
          }
        }
      }
    } else {
      if (!\file_exists("$baseDir/$viewFile.php")) {
        Output::print("$baseDir/$viewFile.php");
        Response::error("VIEW_TEMPLATE_NOT_EXIST");
      }
    }

    return self::renderPage($viewFile, $viewDirOrViewData, $viewData, $templateId, $hook);
  }
  /**
   * 渲染页面
   *
   * @param [type] $viewFile $viewFile 模板的文件名称。可数组或单一字符串
   * @param string $viewDirOrViewData? 文件的路径或者渲染的数据。传入的如果是数组就是渲染的数据，否则就是模板路径。基于根路径也就是当前插件的根目录下的Views文件夹
   * @param array $viewData? 渲染的数据
   * @param string $templateId? 模板Id
   * @return void
   */
  static function page($viewFile, $viewDirOrViewData = "/", $viewData = [], $templateId = "page")
  {
    if (is_array($viewDirOrViewData)) {
      $viewData = $viewDirOrViewData;
      $viewDirOrViewData = "";
    }
    self::render($viewFile, $viewDirOrViewData, $viewData, $templateId);
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
  static function section($viewFile, $viewDirOrViewData = "/", $viewData = [], $templateId = "page")
  {
    if (is_array($viewDirOrViewData)) {
      $viewData = $viewDirOrViewData;
      $viewDirOrViewData = "";
    }
    return self::render($viewFile, $viewDirOrViewData, $viewData, $templateId);
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
  static function kernelPage($viewFile, $viewDirOrViewData = "", $viewData = [], $templateId = "system_page")
  {
    if (is_array($viewDirOrViewData)) {
      $viewData = $viewDirOrViewData;
      $viewDirOrViewData = "";
    }
    self::render($viewFile, $viewDirOrViewData, $viewData, $templateId);
    exit;
  }
  static function layout($layout = null, $viewFile = null, $viewDirOrViewData = "", $viewData = [], $templateId = "layout")
  {
    if ($layout || ($layout && !$viewFile) || ($layout && is_array($viewFile))) {
      if (is_array($viewDirOrViewData)) {
        $viewData = $viewDirOrViewData;
        $viewDirOrViewData = "";
      }
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
        self::render($layout, "Layout", $layoutData, $templateId);
        exit;
      } else {
        return self::page($layout, "Layout", $layoutData,  $templateId);
      }
    } else {
      return self::page($GLOBALS['__pageFile'], $GLOBALS['__pageData'], [], "page");
    }
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
}
