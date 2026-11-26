<?php

namespace kernel\Foundation\Builtin;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\Path;

/**
 * 文件式迁移执行器
 *
 * 扫描 Migrations 目录下的 PHP 文件；每个文件返回一个实现 {@see Migration} 的对象：
 * - id(): string        稳定且可排序的迁移 ID
 * - up(): void          升级
 * - down(): void        降级
 * - version(): string   发布版本，用于按版本执行和首次启用时的基线判断
 *
 * 执行状态写入 Data/migrations.json，不建数据库迁移表。目录下文件名可任意，
 * 排序由 id() 决定；建议 ID 使用 `202611260001_xxx` 这类可排序格式。
 */
class Migrator
{
  /**
   * 迁移目录
   *
   * @var string
   */
  private $migrationsDir;
  /**
   * 执行记录文件
   *
   * @var string
   */
  private $ledgerFile;
  /**
   * 当前插件版本，用于首次建立记录时跳过历史迁移
   *
   * @var string
   */
  private $currentVersion;
  /**
   * 扫描结果缓存
   *
   * @var array|null
   */
  private $migrations = null;

  public function __construct($migrationsDir, $ledgerFile, $currentVersion = "0.0.0")
  {
    $this->migrationsDir = $migrationsDir;
    $this->ledgerFile = $ledgerFile;
    $this->currentVersion = (string) $currentVersion;
  }

  /**
   * 所有迁移，按 ID 升序
   *
   * @return array<string, array{object:Migration, file:string, version:string, checksum:string}>
   */
  public function migrations()
  {
    if ($this->migrations !== null) {
      return $this->migrations;
    }

    if (!is_dir($this->migrationsDir)) {
      return $this->migrations = [];
    }

    $migrations = [];
    foreach (glob(Path::join($this->migrationsDir, "*.php")) ?: [] as $file) {
      $migration = include($file);
      if (!($migration instanceof Migration)) {
        throw new Error("迁移加载失败", 500, 500, "{$file} 必须 return 一个实现 Migration 接口的对象");
      }

      $id = (string) $migration->id();
      if ($id === "") {
        throw new Error("迁移加载失败", 500, 500, "{$file} 的 id() 不能为空");
      }
      if (isset($migrations[$id])) {
        throw new Error("迁移加载失败", 500, 500, "迁移 ID 重复：{$id}");
      }

      $migrations[$id] = [
        "object" => $migration,
        "file" => $file,
        "version" => (string) $migration->version(),
        "checksum" => hash_file("sha256", $file) ?: "",
      ];
    }

    ksort($migrations);
    return $this->migrations = $migrations;
  }

  /**
   * 把当前所有迁移标记为已执行（安装基线）
   *
   * 仅在记录文件不存在时生效；已有记录时不覆盖。
   *
   * @return bool true=已写入基线，false=已有记录
   */
  public function markAllApplied()
  {
    if ($this->loadLedger() !== null) {
      return false;
    }

    $ledger = [
      "applied" => [],
      "last_batch" => 0,
    ];
    foreach ($this->migrations() as $id => $migration) {
      $ledger["applied"][$id] = [
        "batch" => 0,
        "at" => time(),
        "version" => $migration["version"],
        "checksum" => $migration["checksum"],
      ];
    }
    $this->saveLedger($ledger);

    return true;
  }

  /**
   * 待执行迁移
   *
   * @param string|null $targetVersion 目标版本，null 表示全部
   * @return string[]
   */
  public function pending($targetVersion = null)
  {
    $migrations = $this->migrations();
    $ledger = $this->ensureLedger($migrations);
    $targetVersion = $this->parseSemver((string) $targetVersion);

    $pending = [];
    foreach ($migrations as $id => $migration) {
      if (isset($ledger["applied"][$id])) {
        continue;
      }
      if ($targetVersion !== ""
        && version_compare($this->parseSemver($migration["version"]), $targetVersion, ">")) {
        continue;
      }
      $pending[] = $id;
    }

    return $pending;
  }

  /**
   * 执行待迁移
   *
   * @param string|null $targetVersion 目标版本
   * @return string[] 已执行的迁移 ID
   */
  public function run($targetVersion = null)
  {
    $migrations = $this->migrations();
    $ledger = $this->ensureLedger($migrations);
    $pending = $this->pending($targetVersion);
    if (empty($pending)) {
      return [];
    }

    $batch = (int) ($ledger["last_batch"] ?? 0) + 1;
    $ledger["last_batch"] = $batch;
    $executed = [];

    foreach ($pending as $id) {
      $migration = $migrations[$id];
      try {
        $migration["object"]->up();
      } catch (\Throwable $th) {
        throw new Error("迁移 {$id} 失败", 500, 500, $th->getMessage());
      }

      $ledger["applied"][$id] = [
        "batch" => $batch,
        "at" => time(),
        "version" => $migration["version"],
        "checksum" => $migration["checksum"],
      ];
      $this->saveLedger($ledger);
      $executed[] = $id;
    }

    return $executed;
  }

  /**
   * 回滚迁移
   *
   * 传 targetVersion 时回滚版本高于目标的迁移；不传时回滚最后一个 batch。
   *
   * @param string|null $targetVersion 目标版本
   * @return string[] 已回滚的迁移 ID
   */
  public function rollback($targetVersion = null)
  {
    $migrations = $this->migrations();
    $ledger = $this->ensureLedger($migrations);
    $applied = $ledger["applied"];

    if ($targetVersion !== null && (string) $targetVersion !== "") {
      $targetVersion = $this->parseSemver((string) $targetVersion);
      $ids = [];
      foreach ($applied as $id => $item) {
        if ($item["version"] !== null
          && version_compare($this->parseSemver($item["version"]), $targetVersion, ">")) {
          $ids[] = $id;
        }
      }
    } else {
      $batch = (int) ($ledger["last_batch"] ?? 0);
      $ids = [];
      foreach ($applied as $id => $item) {
        if ((int) ($item["batch"] ?? 0) === $batch && $batch > 0) {
          $ids[] = $id;
        }
      }
    }

    rsort($ids);
    $rolledBack = [];
    foreach ($ids as $id) {
      if (!isset($migrations[$id])) {
        throw new Error("迁移回滚失败", 500, 500, "迁移 {$id} 的文件已不存在");
      }
      try {
        $migrations[$id]["object"]->down();
      } catch (\Throwable $th) {
        throw new Error("迁移 {$id} 回滚失败", 500, 500, $th->getMessage());
      }

      unset($ledger["applied"][$id]);
      $this->saveLedger($ledger);
      $rolledBack[] = $id;
    }

    return $rolledBack;
  }

  /**
   * 迁移状态
   *
   * @return array{applied:array,pending:string[],last_batch:int}
   */
  public function status()
  {
    $migrations = $this->migrations();
    $ledger = $this->ensureLedger($migrations);

    return [
      "applied" => $ledger["applied"],
      "pending" => $this->pending(),
      "last_batch" => (int) ($ledger["last_batch"] ?? 0),
    ];
  }

  /**
   * 加载记录；文件不存在时按当前版本建立基线
   *
   * @param array $migrations
   * @return array
   */
  private function ensureLedger(array $migrations)
  {
    $ledger = $this->loadLedger();
    if ($ledger !== null) {
      return $ledger;
    }

    $ledger = [
      "applied" => [],
      "last_batch" => 0,
    ];

    $current = $this->parseSemver($this->currentVersion);
    if ($current !== "") {
      foreach ($migrations as $id => $migration) {
        if ($migration["version"] !== null
          && version_compare($this->parseSemver($migration["version"]), $current, "<=")) {
          $ledger["applied"][$id] = [
            "batch" => 0,
            "at" => time(),
            "version" => $migration["version"],
            "checksum" => $migration["checksum"],
          ];
        }
      }
    }

    $this->saveLedger($ledger);
    return $ledger;
  }

  /**
   * @return array|null
   */
  private function loadLedger()
  {
    if (!file_exists($this->ledgerFile)) {
      return null;
    }

    $content = file_get_contents($this->ledgerFile);
    $ledger = json_decode((string) $content, true);
    if (!is_array($ledger) || !isset($ledger["applied"]) || !is_array($ledger["applied"])) {
      throw new Error("迁移记录损坏", 500, 500, $this->ledgerFile);
    }

    $ledger["last_batch"] = (int) ($ledger["last_batch"] ?? 0);
    return $ledger;
  }

  private function saveLedger(array $ledger)
  {
    $directory = dirname($this->ledgerFile);
    if (!is_dir($directory)) {
      mkdir($directory, 0755, true);
    }

    $json = json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $temporary = $this->ledgerFile . ".tmp";
    if (file_put_contents($temporary, $json) === false || !rename($temporary, $this->ledgerFile)) {
      throw new Error("迁移记录写入失败", 500, 500, $this->ledgerFile);
    }
  }

  private function parseSemver($version)
  {
    $parts = explode(".", (string) $version);
    return count($parts) >= 3 ? implode(".", array_slice($parts, 0, 3)) : (string) $version;
  }
}