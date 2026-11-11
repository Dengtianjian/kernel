<?php

namespace kernel\Foundation\Application;

use kernel\DiscuzX\Foundation\DiscuzXApp;

/**
 * 应用身份解析
 *
 * 把「对象 / 类名 / 命名空间字符串」解析成应用 ID（等于应用目录名）。
 * 本类只做解析、不持有状态：规则是取命名空间第一段；DiscuzX 宿主下目录名统一带
 * `gstudio_` 前缀而命名空间不带，按 {@see DiscuzXApp::exists()}（宿主常量 `IN_DISCUZ`）判断后补前缀。
 * 判断只看宿主常量、不依赖是否已建应用实例，早期自动加载阶段也能得到正确结果。
 *
 * @package kernel\Foundation\Application
 */
class Identity
{
  /**
   * 通过类名 / 对象 / 命名空间字符串解析所属应用的 ID（appId）
   *
   * 入参支持三种形态，统一归一为「完整类名」后取命名空间第一段作为 appId：
   * - 对象：`get_class()` 取完整类名；
   * - 类名字符串（含命名空间，如 `gstudio_download\Services\DownloadFileService`）；
   * - 纯命名空间字符串（如 `gstudio_super_app\Model`）。
   *
   * 命名空间第一段即应用 ID（目录名）：
   * - `gstudio_download\Services\DownloadFileService` → `gstudio_download`
   * - `gstudio_super_app\Models\SuperAppFilesModel`  → `gstudio_super_app`
   * - `kernel\Foundation\...`                        → `kernel`（由 DiscuzX 环境补前缀）
   *
   * 用途：子系统复用主系统的类时，kernel 内部需要按「某个类所属的应用」而非「默认应用」
   * 来决定表名前缀、签名密钥、URL 的 id 参数等。
   *
   * @param object|string $class 对象、完整类名或命名空间字符串
   * @return string|null 命名空间第一段（应用 ID）；入参无法解析时返回 null
   */
  public static function fromNamespace($class)
  {
    //* 对象 → 取完整类名；字符串原样使用
    $target = ltrim(is_object($class) ? get_class($class) : (string)$class, "\\");

    if ($target === "") {
      return null;
    }

    $appId = explode("\\", $target)[0];

    //* DiscuzX 下目录名带 gstudio_ 前缀；已带前缀（如 gstudio_download）时原样返回
    $prefix = DiscuzXApp::exists() ? "gstudio_" : "";
    if ($prefix === "" || strpos($appId, $prefix) === 0) {
      return $appId;
    }

    return $prefix . $appId;
  }
}
