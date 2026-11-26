<?php

namespace kernel\Commands;

use kernel\Foundation\FileSystem\Path;

/**
 * 生成迁移文件命令
 *
 * 命令名 make:migration 在 Setup/Console/Bootstrap 中注册（Console::addCommand）。
 *
 * 用法：
 *   php kernel/console make:migration create_users
 *   php kernel/console make:migration add_avatar --version=1.3.3
 *   php kernel/console make:migration seed_roles --app=gstudio_super_app
 *   php kernel/console make:migration create_users --force
 *
 * 文件写入 `{应用根目录}/Deployment/Migrations/{时间戳}_{名称}.php`，
 * version() 默认取插件 manifest 的版本，可用 --version 覆盖。
 */
class MakeMigrationCommand
{
  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数：[0] 迁移名称（如 "create_users" 或 "CreateUsers"）
   * @param array $options 选项参数：app 目标应用、version 迁移版本、force 是否覆盖
   * @return integer 退出码，0 表示成功
   */
  public function handle($console, $args, $options): int
  {
    $name = $this->snake((string) ($args[0] ?? ""));
    if ($name === "") {
      $console->error("Usage: make:migration <name>");
      $console->info("Example: make:migration create_users");
      return 1;
    }

    $appId = trim((string) ($options["app"] ?? $console->appId()));
    if ($appId === "" || !preg_match("/^[A-Za-z][A-Za-z0-9_]*$/", $appId)) {
      $console->error("App id \"{$appId}\" is invalid.");
      return 1;
    }

    $root = Path::root($appId);
    if ($root === null || !is_dir($root)) {
      $console->error("App directory not found: {$appId}");
      return 1;
    }

    $version = trim((string) ($options["version"] ?? $this->manifestVersion($root, $appId)));

    $id = date("YmdHis") . "_" . $name;
    $dir = Path::join($root, "Deployment", "Migrations");
    $filePath = Path::join($dir, $id . ".php");

    if (file_exists($filePath) && empty($options["force"])) {
      $console->error("File already exists: {$filePath}");
      $console->info("Use --force to overwrite it.");
      return 1;
    }

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
      $console->error("Failed to create directory: {$dir}");
      return 1;
    }

    $content = <<<PHP
<?php

use kernel\\Foundation\\Builtin\\Migration;

return new class implements Migration
{
  public function id()
  {
    return "{$id}";
  }

  public function version()
  {
    return "{$version}";
  }

  public function up()
  {

  }

  public function down()
  {

  }
};
PHP;

    if (file_put_contents($filePath, $content) === false) {
      $console->error("Failed to write file: {$filePath}");
      return 1;
    }

    $console->success("Created: {$filePath}");
    $console->info("Migration ID: {$id}");
    return 0;
  }

  /**
   * 转为 snake_case
   *
   * "CreateUsers" / "create-users" / "create users" 均转为 "create_users"。
   *
   * @param string $name 原始名称
   * @return string
   */
  protected function snake(string $name): string
  {
    $name = preg_replace("/([a-z0-9])([A-Z])/", '$1_$2', trim($name));
    $name = strtolower((string) preg_replace("/[^A-Za-z0-9]+/", "_", (string) $name));

    return trim($name, "_");
  }

  /**
   * 读取插件 manifest 中的版本号
   *
   * 读不到时返回 0.0.0，保证 version() 始终有值。
   *
   * @param string $root 应用根目录
   * @param string $appId 应用 ID
   * @return string
   */
  protected function manifestVersion(string $root, string $appId): string
  {
    $file = Path::join($root, "discuz_plugin_" . $appId . ".json");
    if (!is_file($file)) {
      return "0.0.0";
    }

    $data = json_decode((string) file_get_contents($file), true);
    $version = $data["Data"]["plugin"]["version"] ?? $data["data"]["plugin"]["version"] ?? null;

    return is_string($version) && $version !== "" ? $version : "0.0.0";
  }
}