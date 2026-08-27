<?php

namespace kernel\Foundation\Router;

use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\HTTP\URL;

/**
 * 路由匹配实例
 *
 * 由 App 实例化并持有（`App::$router`），负责：
 *
 * - 构造时加载 Routes 文件（kernel/Routes 与 App/Routes 下的 PHP 文件，
 *   文件内通过新 Route 门面（Route::get/post/group/same/domain/fallback）注册路由）
 * - 按请求匹配路由（委托静态容器 Routes::match）
 *
 * 路由定义 DSL 已沉淀到新 Route 门面（Route::get/post/.../group/same/domain），
 * 本类仅保留运行期匹配职责，不再承载静态注册方法。
 */
class Router
{
  /**
   * 构造：加载路由
   *
   * 由 App 实例化并持有（`App::$router`）。匹配所需的请求实例通过 `getApp()->request()`
   * 获取，无需在构造时传入。
   */
  public function __construct()
  {
    $this->load();
  }

  /**
   * 加载路由文件
   *
   * 扫描 kernel/Routes 与 App/Routes 下所有 PHP 文件并 include_once，
   * 文件内通过 Route 门面注册 URI 路由。
   *
   * @return bool
   */
  protected function load()
  {
    $localRouteFiles = [];
    $kernelRoutesDir = FileHelper::combinedFilePath(Path::kernelRoot(), "Routes");
    if (is_dir($kernelRoutesDir)) {
      $kernelRouteFiles = FileHelper::recursionScanDir($kernelRoutesDir, null, true);
      if (count($kernelRouteFiles)) {
        $localRouteFiles = array_merge($localRouteFiles, $kernelRouteFiles);
      }
    }

    $appRoutesDir = FileHelper::combinedFilePath(Path::root(), "Routes");
    if (is_dir($appRoutesDir)) {
      $appRouteFiles = FileHelper::recursionScanDir($appRoutesDir, null, true);
      if (count($appRouteFiles)) {
        $localRouteFiles = array_merge($localRouteFiles, $appRouteFiles);
      }
    }
    foreach ($localRouteFiles as $fileItem) {
      if (!is_dir($fileItem)) {
        include_once($fileItem);
      }
    }

    return true;
  }

  /**
   * 获取匹配的路由
   *
   * 由 App::run() 调用，命中参数经 `$request->params->fill($route['params'])` 注入。
   *
   * @return array|null 匹配到的路由（含 params / parameters / methodName / middlewares），未匹配返回 null
   */
  public function route()
  {
    $request = getApp()->request();

    $uri = $request->uri();
    if ($uri !== "/") {
      //* 尾斜杠容忍：/users/ 与 /users 视为同一路径（根路径 "/" 保留）
      $uri = trim($uri, "/");
    }

    $domain = $request->host();
    if ($domain !== null && $domain !== "") {
      //* 归一化：Host 头可携带端口与括号（IPv6 [::1]:8080）且按 RFC 不区分大小写，
      //   域名路由按无端口、无括号的小写域名注册，故先归一（不剥则非标准端口必 miss）
      $domain = URL::normalizeDomain($domain);
    }
    if (!$domain) {
      $domain = null;
    }

    return Routes::match($request->method(), $uri, $domain);
  }

  /**
   * 获取全部已注册路由
   *
   * 委托 Routes::tables() 导出完整路由定义表（静态 + 动态）。
   *
   * 结构：domain => method => uri => 路由定义（三层索引）。domain 键为路由生效
   * 域名（全局路由为 "*"），method 键照注册方法名（get/post/...），any 用 "*"。
   * 每项含：name / method / uri（已拼组前缀）/ controller / middlewares /
   * parameters / where / params（动态参数正则）/ append / domain。
   *
   * @return array[] domain => method => uri => 路由定义
   */
  public function routes()
  {
    return Routes::tables();
  }
}
