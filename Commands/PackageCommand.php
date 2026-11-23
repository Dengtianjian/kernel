<?php

namespace kernel\Commands;

use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\FileSystem\Zip;

/**
 * 打包命令（基础插件内置）：将业务应用与内核目录分别打包为 zip
 *
 * 用法：
 *   php isdtj/console package                    # 应用名取 $console->appId()，内核名取 $console->kernelId()
 *   php kernel/console package isdtj             # 从内核控制台运行：位置参数指定应用名
 *   php kernel/console package isdtj kernel      # 同时指定应用名与内核名
 *   php kernel/console package --app=isdtj --kernel=kernel
 *
 * - 应用名默认取 `$console->appId()`（业务应用目录名，如 isdtj）
 * - 内核名默认取 `$console->kernelId()`（内核目录名，默认 "kernel"）
 * - 两者均可由位置参数（[app] [kernel]）或选项（--app / --kernel）覆盖
 * - `--profile`：打包形态（默认 `x5`）。`x5` = Discuz! X5.0 插件版（manifest version
 *   自动声明为 X5.0）；`framework` = 纯框架版（剔除 DiscuzX/ 与 X5 品类适配目录 oss/、pay/
 *   及两份插件 manifest，version 独立管理）
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
   * 打包形态（profile）→ 追加忽略名单 / manifest version 覆写
   *
   * - x5：Discuz! X5.0 插件版（默认）——全量内容，manifest version 声明为 X5.0
   * - framework：纯框架版——剔除 DiscuzX/、X5 品类适配目录（oss/、pay/）与插件 manifest
   *
   * @var array<string,array{ignore:string[],manifests:array<string,string>}>
   */
  const PROFILES = [
    "x5" => [
      "ignore" => [],
      "manifests" => [
        "discuz_plugin_gstudio_kernel.json" => "X5.0"
      ]
    ],
    "framework" => [
      "ignore" => [
        "/DiscuzX",
        "/oss",
        "/pay",
        "discuz_plugin_gstudio_kernel.json"
      ],
      "manifests" => []
    ]
  ];

  /**
   * 打包忽略名单（追加进 Zip 的 ignore 列表）
   * @var array<int,string>
   */
  const IGNORE = [
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
    "composer.lock"
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
    $project = Path::project();
    if ($project === null) {
      $console->error("Cannot resolve project root.");
      return 1;
    }

    //* 应用名：位置参数 > --app 选项 > $console->appId()
    $appName = trim((string) ($args[0] ?? $options["app"] ?? $console->appId()));
    //* 内核名：位置参数 > --kernel 选项 > $console->kernelId()（默认 kernel）
    $kernelName = trim((string) ($args[1] ?? $options["kernel"] ?? ($console->kernelId() ?: "kernel")));

    if ($appName === "") {
      $console->error("Cannot determine application name. Pass it as an argument: package <app> [kernel]");
      return 1;
    }
    if ($appName === $kernelName) {
      $console->error("Application and kernel resolve to the same name \"{$appName}\"; pass the application name explicitly (e.g. package isdtj).");
      return 1;
    }

    //* 打包形态：--profile=x5（默认）/ framework
    $profile = strtolower((string) ($options["profile"] ?? "x5"));
    if (!isset(self::PROFILES[$profile])) {
      $console->error("Unknown profile \"{$profile}\". Available: " . implode(", ", array_keys(self::PROFILES)));
      return 1;
    }
    $profileIgnore = self::PROFILES[$profile]["ignore"];
    $manifestVersions = self::PROFILES[$profile]["manifests"];

    $zip = new Zip();
    $zip->exclude(array_merge(self::IGNORE, $profileIgnore));

    $targets = [
      $appName    => $appName . ".zip",
      $kernelName => $kernelName . ".zip",
    ];

    $failed = false;
    foreach ($targets as $dir => $out) {
      $source = $project . "/" . $dir;
      $output = $project . "/" . $out;

      if (!is_dir($source)) {
        $console->warning("Skip: directory not found: {$source}");
        continue;
      }
      if (is_file($output)) {
        unlink($output);
      }

      //* profile 的 manifest version 覆写（仅 kernel 打包；x5 形态声明 X5.0，幂等）
      foreach ($manifestVersions as $manifestFile => $version) {
        $manifestPath = $source . "/" . $manifestFile;
        if (is_file($manifestPath)) {
          $manifest = json_decode((string) file_get_contents($manifestPath), true);
          if (is_array($manifest) && (($manifest["Data"]["version"] ?? null) !== $version)) {
            $manifest["Data"]["version"] = $version;
            file_put_contents(
              $manifestPath,
              json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $console->success("Manifest version set to {$version}: {$manifestFile}");
          }
        }
      }

      if ($zip->zipDirectory($source, $output)) {
        $console->success("Packed {$dir} -> {$out} (profile: {$profile})");
      } else {
        $console->error("Failed to pack {$dir}: " . ($zip->lastError() ?? "unknown error"));
        $failed = true;
      }
    }

    return $failed ? 1 : 0;
  }
}
