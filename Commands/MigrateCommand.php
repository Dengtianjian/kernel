<?php

namespace kernel\Commands;

use kernel\Discuz\Foundation\DiscuzApp;
use kernel\Discuz\Foundation\DiscuzCliDatabase;
use kernel\Foundation\Builtin\Deployment;
use kernel\Foundation\Builtin\Migrator;
use kernel\Foundation\Database\RDB\Drivers\Connections;
use kernel\Foundation\Database\RDB\Drivers\Mysqli\MysqliDriver;
use kernel\Foundation\FileSystem\Path;

/**
 * 执行迁移命令
 *
 * 命令名 migrate 在 Setup/Console/Bootstrap 中注册（Console::addCommand）。
 *
 * 用法：
 *   php kernel/console migrate
 *   php kernel/console migrate --app=gstudio_super_app
 *   php kernel/console migrate --to=1.3.3
 *
 * 执行应用 `Deployment/Migrations` 下尚未记录的迁移；首次没有记录文件时，
 * 按当前版本建立基线（Discuz 读插件设置，独立部署读 .version，最后回退 manifest）。
 */
class MigrateCommand
{
  /**
   * 命令处理器
   *
   * @param \kernel\Foundation\Console\Console $console 控制台实例
   * @param array $args 位置参数（未使用）
   * @param array $options 选项参数：app 目标应用、to 目标版本
   * @return integer 退出码，0 表示成功
   */
  public function handle($console, $args, $options): int
  {
    $this->bootstrapDatabase();

    $appId = trim((string) ($options["app"] ?? $console->appId()));
    if ($appId === "" || !preg_match("/^[A-Za-z][A-Za-z0-9_]*$/", $appId)) {
      $console->error("App id \"{$appId}\" is invalid.");
      return 1;
    }

    $root = Path::root($appId);
    $data = Path::data($appId);
    if ($root === null || !is_dir($root)) {
      $console->error("App directory not found: {$appId}");
      return 1;
    }
    if ($data === null) {
      $console->error("App data directory not found: {$appId}");
      return 1;
    }

    $migrationsDir = Path::join($root, "Deployment", "Migrations");
    if (!is_dir($migrationsDir)) {
      $console->error("Migration directory not found: {$migrationsDir}");
      return 1;
    }

    $to = trim((string) ($options["to"] ?? ""));
    $to = $to === "" ? null : $to;

    $ledgerFile = Path::join($data, "migrations.json");

    $migrator = new Migrator(
      $migrationsDir,
      $ledgerFile,
      $this->currentVersion($appId, $root, $data)
    );

    try {
      $pending = $migrator->pending($to);
      if (empty($pending)) {
        $console->success("Nothing to migrate.");
        return 0;
      }

      $console->info("Pending migrations:");
      foreach ($pending as $id) {
        $console->line("  - {$id}");
      }

      $executed = $migrator->run($to);
    } catch (\Throwable $th) {
      $console->error($th->getMessage());
      return 1;
    }

    foreach ($executed as $id) {
      $console->success("Applied: {$id}");
    }

    if (!DiscuzApp::exists() && $to !== null) {
      file_put_contents(Path::join($data, ".version"), $to);
    }

    $console->success("Migrated " . count($executed) . " migration(s).");
    return 0;
  }

  /**
   * 命令行下补数据库连接
   *
   * 存在站点配置时直接按 config_global.php 建连，并注册表前缀适配；已有驱动或独立部署时跳过。
   * 不启动 Discuz 应用：完整 class_core 初始化在 CLI 下可能静默退出，迁移只需要 DB 与表前缀。
   *
   * @return void
   */
  protected function bootstrapDatabase()
  {
    if (!empty(Connections::getDrivers()) || defined("DISCUZ_ROOT")) {
      return;
    }

    $projectRoot = dirname(Path::kernel(), 3);
    $configFile = $projectRoot . "/config/config_global.php";
    if (!is_file($configFile)) {
      return;
    }

    $_config = [];
    include $configFile;

    $db = $_config["db"][1] ?? null;
    if (!is_array($db) || empty($db["dbhost"]) || empty($db["dbname"])) {
      return;
    }

    $host = (string) $db["dbhost"];
    $port = 3306;
    if (strpos($host, ":") !== false) {
      [$host, $port] = explode(":", $host, 2);
    }

    try {
      Connections::addDriver(new MysqliDriver(
        $host,
        (string) ($db["dbuser"] ?? ""),
        (string) ($db["dbpw"] ?? ""),
        (string) $db["dbname"],
        (int) $port,
        (string) ($db["dbcharset"] ?? "utf8mb4")
      ), "default", true);
    } catch (\Throwable $th) {
      fwrite(STDERR, "Database bootstrap failed: " . $th->getMessage() . PHP_EOL);
      return;
    }

    if (!defined("IN_DISCUZ")) {
      define("IN_DISCUZ", true);
    }
    if (!defined("DISCUZ_ROOT")) {
      define("DISCUZ_ROOT", rtrim($projectRoot, "/\\") . "/");
    }
    if (!class_exists("DB", false)) {
      class_alias(DiscuzCliDatabase::class, "DB");
    }
    DiscuzCliDatabase::setTablepre((string) ($db["tablepre"] ?? "pre_"));

    //* 目标运行时是 PHP 8.0，8.4 的隐式 nullable 等弃用提示不阻断迁移
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
  }

  /**
   * 当前版本，用于首次建立迁移基线
   *
   * 优先级：Discuz 插件设置 → 独立部署的 .version → 插件 manifest → 0.0.0。
   *
   * @param string $appId 应用 ID
   * @param string $root 应用根目录
   * @param string $data 应用数据目录
   * @return string 三段基础版本号
   */
  protected function currentVersion(string $appId, string $root, string $data): string
  {
    if (DiscuzApp::exists() && function_exists("getglobal")) {
      $version = \getglobal("setting/plugins/version/" . $appId);
      if (is_string($version) && $version !== "") {
        return Deployment::parseSemver($version);
      }
    }

    $versionFile = Path::join($data, ".version");
    if (is_file($versionFile)) {
      $version = trim((string) file_get_contents($versionFile));
      if ($version !== "") {
        return Deployment::parseSemver($version);
      }
    }

    return Deployment::parseSemver($this->manifestVersion($root, $appId));
  }

  /**
   * 读取插件 manifest 中的版本号
   *
   * 读不到时返回 0.0.0。
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