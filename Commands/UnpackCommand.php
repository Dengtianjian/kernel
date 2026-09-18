<?php

namespace kernel\Commands;

use kernel\Foundation\App;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\FileSystem\Zip;

/**
 * 解包命令（框架内置）：将应用与内核的 zip 解压回对应目录
 *
 * 用法：
 *   php isdtj/console unpack                    # 应用名取 App::id()，内核名取 App::kernelId()
 *   php kernel/console unpack isdtj             # 从内核控制台运行：位置参数指定应用名
 *   php kernel/console unpack isdtj kernel      # 同时指定应用名与内核名
 *   php kernel/console unpack --app=isdtj --kernel=kernel
 *
 * - 应用名默认取 `App::id()`，内核名默认取 `App::kernelId()`（默认 "kernel"）
 * - 两者均可由位置参数（[app] [kernel]）或选项（--app / --kernel）覆盖
 * - 读取项目根下的 {app}.zip / {kernel}.zip，解压到 {app} / {kernel} 目录
 * - 归档文件不存在时跳过该目标并告警，不中断其余目标
 */
class UnpackCommand
{
  /** @var string 命令名 */
  protected $name = "unpack";
  /** @var string 命令说明，用于帮助列表 */
  protected $description = "Unpack application and kernel zip archives into directories";

  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数：[0] 应用名（可选）、[1] 内核名（可选）
   * @param array $options 选项：app / kernel（可选）
   * @return integer 退出码，0 表示成功
   */
  public function handle($console, $args, $options): int
  {
    $projectRoot = Path::projectRoot();
    if ($projectRoot === null) {
      $console->error("Cannot resolve project root.");
      return 1;
    }

    //* 应用名：位置参数 > --app 选项 > App::id()
    $appName = trim((string) ($args[0] ?? $options["app"] ?? App::id()));
    //* 内核名：位置参数 > --kernel 选项 > App::kernelId()（默认 kernel）
    $kernelName = trim((string) ($args[1] ?? $options["kernel"] ?? (App::kernelId() ?: "kernel")));

    if ($appName === "") {
      $console->error("Cannot determine application name. Pass it as an argument: unpack <app> [kernel]");
      return 1;
    }
    if ($appName === $kernelName) {
      $console->error("Application and kernel resolve to the same name \"{$appName}\"; pass the application name explicitly (e.g. unpack isdtj).");
      return 1;
    }

    $zip = new Zip();

    $targets = [
      $appName . ".zip"    => $appName,
      $kernelName . ".zip" => $kernelName,
    ];

    $failed = false;
    foreach ($targets as $file => $dir) {
      $source = $projectRoot . "/" . $file;
      $dest = $projectRoot . "/" . $dir;

      if (!is_file($source)) {
        $console->warning("Skip: archive not found: {$source}");
        continue;
      }

      if ($zip->unzip($source, $dest)) {
        $console->success("Unpacked {$file} -> {$dir}");
      } else {
        $console->error("Failed to unpack {$file}: " . ($zip->lastError() ?? "unknown error"));
        $failed = true;
      }
    }

    return $failed ? 1 : 0;
  }
}
