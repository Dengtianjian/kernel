<?php

namespace kernel\Commands;

use kernel\Foundation\App;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\FileSystem\Zip;

/**
 * 打包命令（框架内置）：将业务应用与内核目录分别打包为 zip
 *
 * 用法：
 *   php isdtj/console package                    # 应用名取 App::id()，内核名取 App::kernelId()
 *   php kernel/console package isdtj             # 从内核控制台运行：位置参数指定应用名
 *   php kernel/console package isdtj kernel      # 同时指定应用名与内核名
 *   php kernel/console package --app=isdtj --kernel=kernel
 *
 * - 应用名默认取 `App::id()`（业务应用目录名，如 isdtj）
 * - 内核名默认取 `App::kernelId()`（内核目录名，默认 "kernel"）
 * - 两者均可由位置参数（[app] [kernel]）或选项（--app / --kernel）覆盖
 * - 输出到项目根：{app}.zip / {kernel}.zip；打包前删除旧 zip
 * - 按内置忽略名单排除 .git、vendor、Data、Storage、Secrets 等
 */
class PackageCommand
{
  /** @var string 命令名 */
  protected $name = "package";
  /** @var string 命令说明，用于帮助列表 */
  protected $description = "Package the application and kernel into zip archives";

  /**
   * 打包忽略名单（追加进 Zip 的 ignore 列表）
   * @var array<int,string>
   */
  private const IGNORE = [
    ".git",
    ".github",
    ".gitattributes",
    ".gitignore",
    "Config.local.php",
    "Config.development.php",
    "/vendor",
    "/Data",
    "/Storage",
    "/Secrets",
    "404.html",
  ];

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
      $console->error("Cannot determine application name. Pass it as an argument: package <app> [kernel]");
      return 1;
    }
    if ($appName === $kernelName) {
      $console->error("Application and kernel resolve to the same name \"{$appName}\"; pass the application name explicitly (e.g. package isdtj).");
      return 1;
    }

    $zip = new Zip();
    $zip->exclude(self::IGNORE);

    $targets = [
      $appName    => $appName . ".zip",
      $kernelName => $kernelName . ".zip",
    ];

    $failed = false;
    foreach ($targets as $dir => $out) {
      $source = $projectRoot . "/" . $dir;
      $output = $projectRoot . "/" . $out;

      if (!is_dir($source)) {
        $console->warning("Skip: directory not found: {$source}");
        continue;
      }
      if (is_file($output)) {
        unlink($output);
      }

      if ($zip->zipDirectory($source, $output)) {
        $console->success("Packed {$dir} -> {$out}");
      } else {
        $console->error("Failed to pack {$dir}: " . ($zip->lastError() ?? "unknown error"));
        $failed = true;
      }
    }

    return $failed ? 1 : 0;
  }
}
