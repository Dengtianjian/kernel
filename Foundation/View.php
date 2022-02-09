<?php

namespace kernel\Foundation;

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
   * @param boolean $hook?=false 是否是hook插槽
   * @return boolean 一直都是 true
   */
  static private function renderPage($fileName, $fileDir = "", $viewData = [], $templateId = "", $hook = false)
  {
    global $_GG;
    $View = self::class;

    $viewData = \array_merge(self::$viewData, $viewData);
    foreach ($viewData as $key => $value) {
      global ${$key};
    }

    $blocks = [];

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
          if ($hook) {
            $blocks[$name] = ${$blocks};
          }
        }
      } else {
        foreach ($fileName as $name) {
          include_once $baseDir . "/$name.php";
          if ($hook) {
            $blocks[$name] = ${$blocks};
          }
        }
      }
    } else {
      include_once $baseDir . "/$fileName.php";
      $blocks = ${$fileName};
    }

    foreach ($viewData as $key => $value) {
      unset($GLOBALS[$key]);
    }

    if ($hook) {
      return $blocks;
    }

    return true;
  }
  /**
   * 渲染前做的事情
   * 主要是检查模板文件是否存在以及把渲染的数据加入到全局($GLOBALS)里
   *
   * @param string|array $viewFile 模板的文件名称。可数组或单一字符串
   * @param string $viewDirOfViewData? 文件的路径或者渲染的数据。传入的如果是数组就是渲染的数据，否则就是模板路径。
   * @param array $viewData? 渲染的数据
   * @param string $templateId? 模板唯一标识符
   * @param boolean $hook?=false 是否是hook插槽
   * @return void
   */
  static function render($viewFile, $viewDirOfViewData = "", $viewData = [], $templateId = "", $hook = false)
  {
    if (is_array($viewDirOfViewData)) {
      $viewData = $viewDirOfViewData;
      $viewDirOfViewData = "";
    }

    $viewData = \array_merge(self::$viewData, $viewData);
    if (count($viewData) > 0) {
      foreach ($viewData as $key => $value) {
        $GLOBALS[$key] = $value;
      }
    }

    $baseDir = F_APP_ROOT . "/Views/$viewDirOfViewData";
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

    return self::renderPage($viewFile, $viewDirOfViewData, $viewData, $templateId, $hook);
  }
  /**
   * 渲染页面
   *
   * @param [type] $viewFile $viewFile 模板的文件名称。可数组或单一字符串
   * @param string $viewDirOfViewData? 文件的路径或者渲染的数据。传入的如果是数组就是渲染的数据，否则就是模板路径。基于根路径也就是当前插件的根目录下的Views文件夹
   * @param array $viewData? 渲染的数据
   * @param string $templateId? 模板Id
   * @return void
   */
  static function page($viewFile, $viewDirOfViewData = "/", $viewData = [], $templateId = "page")
  {
    if (is_array($viewDirOfViewData)) {
      $viewData = $viewDirOfViewData;
      $viewDirOfViewData = "/";
    }
    // $viewDirOfViewData = \str_replace(F_APP_ROOT . "/Views", "", $viewDirOfViewData);
    // $viewDirOfViewData = F_APP_ROOT . "/Views/$viewDirOfViewData";
    return self::render($viewFile, $viewDirOfViewData, $viewData, $templateId);
  }

  /**
   * 渲染系统(kernel)页面
   *
   * @param string|array $viewFile 模板的文件名称。可数组或单一字符串
   * @param string $viewDirOfViewData? 文件的路径或者渲染的数据。传入的如果是数组就是渲染的数据，否则就是模板路径。基于根路径也就是核心插件的根目录下的Views文件夹
   * @param array $viewData? 渲染的数据
   * @param string $templateId? 模板Id
   * @return void
   */
  static function systemPage($viewFile, $viewDirOfViewData = "", $viewData = [], $templateId = "system_page")
  {
    if (is_array($viewDirOfViewData)) {
      $viewData = $viewDirOfViewData;
      $viewDirOfViewData = "";
    }
    // $viewDirOfViewData = \str_replace(GlobalVariables::get("_GG/kernel/root") . "/Views/", "", $viewDirOfViewData);
    // $viewDirOfViewData = GlobalVariables::get("_GG/kernel/root") . "/Views/$viewDirOfViewData";
    return self::render($viewFile, $viewDirOfViewData, $viewData, $templateId);
  }
  /**
   * 渲染hook插槽块
   *
   * @param string|array $viewFile 模板文件名称。可字符串，可字符串数组
   * @param string $viewDirOfViewData 渲染的渲染文件路径。传入是关联数组就是渲染的数据
   * @param array $viewData 渲染的数据
   * @return array|string 如果传入的viewFile是数组就会返回关联数组，元素的是加载的块html代码，否则就是单个块html代码
   */
  static function hook($viewFile, $viewDirOfViewData = "", $viewData = [])
  {
    if (is_array($viewDirOfViewData)) {
      $viewData = $viewDirOfViewData;
      $viewDirOfViewData = "";
    }
    // $viewDirOfViewData = \str_replace(GlobalVariables::get("_GG/addon/root") . "/Views", "", $viewDirOfViewData);
    // $viewDirOfViewData = GlobalVariables::get("_GG/addon/root") . "/Views/$viewDirOfViewData";
    return self::render($viewFile, $viewDirOfViewData, $viewData, "hook", true);
  }
  /**
   * 渲染后台模板页面
   *
   * @param string|array $viewFile 模板的文件名称。可数组或单一字符串
   * @param string $viewDirOfViewData? 文件的路径或者渲染的数据。传入的如果是数组就是渲染的数据，否则就是模板路径。基于根路径也就是当前插件的根目录的Views文件夹
   * @param array $viewData? 渲染模板的数据
   * @return void
   */
  static function dashboard($viewFile, $viewDirOfViewData = "Dashboard", $viewData = [])
  {
    if (is_array($viewDirOfViewData)) {
      $viewData = $viewDirOfViewData;
      $viewDirOfViewData = "Dashboard";
    }
    $realTemplateDir = F_APP_ROOT . "/Views/" . $viewDirOfViewData;
    // $viewDirOfViewData = GlobalVariables::get("_GG/kernel/root") . "/Views/Dashboard";
    return self::render("container", $viewDirOfViewData, [
      "_fileName" => $viewFile,
      "_templateDir" => $realTemplateDir,
      "_viewData" => $viewData
    ], "dashboard");
  }
  /**
   * 渲染kernel后台模板systemPage
   *
   * @param string|array $viewFile 渲染文件名称 可/分割目录
   * @param string|array $viewDirOfViewData 渲染的目录或者渲染的数据
   * @param array $viewData 渲染的数据
   * @return void
   */
  static function systemDashboard($viewFile, $viewDirOfViewData = "dashboDashboardard", $viewData = [])
  {
    if (is_array($viewDirOfViewData)) {
      $viewData = $viewDirOfViewData;
      $viewDirOfViewData = "Dashboard";
    }
    $realTemplateDir = F_APP_ROOT . "/Views/" . $viewDirOfViewData;
    // $viewDirOfViewData = \str_replace(GlobalVariables::get("_GG/kernel/root"), "", $viewDirOfViewData);
    // $viewDirOfViewData = GlobalVariables::get("_GG/kernel/root") . "/Views/Dashboard";
    return self::render("systemContainer", $viewDirOfViewData, [
      "_fileName" => $viewFile,
      "_templateDir" => $realTemplateDir,
      "_viewData" => $viewData
    ], "system_dashboard");
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
      "navtitle" => Str::replaceParams($titleSourceString, $params),
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
  static function outputHeader()
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
