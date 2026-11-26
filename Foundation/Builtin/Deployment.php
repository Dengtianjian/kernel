<?php

namespace kernel\Foundation\Builtin;

use kernel\Discuz\Foundation\DiscuzApp;
use kernel\Foundation\Application\Applications;
use kernel\Foundation\FileSystem\FileSystem;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 应用部署编排器：安装 / 更新 / 卸载（含回滚）
 *
 * 名字对应各应用根目录的 `Deployment/` 目录 —— 其中的迁移文件（`Migrations/`）由本类执行；
 * 安装 / 卸载用到的表、种子数据与钩子由各应用自己的部署类组织（如 `AppDeployment` 覆写 install / uninstall）。
 * 本类顺带管理 `.version`、`install.lock`、`install.key`；**Discuz 下 `.version` 不参与流程**：
 * 版本基线取自插件设置（`setting.plugins.version.{appId}`），安装状态由 Discuz 自己维护。
 *
 * 迁移机制：
 * - 扫描 migrationsDir 下的所有 PHP 文件，文件名不限
 * - 每个文件 return 一个实现 {@see Migration} 的对象，提供 id() / version() / up() / down()
 * - 执行状态写入 `Data/migrations.json`，不建数据库迁移表
 * - 首次没有记录文件时，按当前插件版本把历史迁移标记为已执行
 * - 升级调用 up()，回滚调用 down()
 *
 * 版本管理：
 * - .version 文件存储完整版本号（如 2.2.0.20260721.1746）
 * - 对比时自动提取前三段基础版本号（2.2.0）做迁移基线与目标版本判断
 *
 * 安装辅助：
 * - install() / lockInstall() / installLocked()：创建数据/存储目录、写入并检测安装锁文件（install.lock）
 * - verifiyInstallKey() / refreshInstallKey()：校验与重新生成安装密钥（install.key）
 * - updateVersion() / version()：把配置版本号写入 .version、读取 .version 原文
 * - resetVersion() / uninstall()：强制改写版本号、删除 .version 版本记录
 *
 * 查询与状态（只读，不执行升级/回滚）：
 * - parseSemver() / compare()：提取三段基础版本号、按基础版本号比较两个版本
 * - getPendingMigrations()：列出待执行迁移 ID
 * - getStatus()：返回 app_id / current_version / latest_version / migrations_dir / data_dir 快照
 *
 * 迁移文件格式：
 * ```php
 * return new class implements Migration {
 *     public function id()      { return '202611260001_add_user_profile'; }
 *     public function version() { return '1.4.0'; }
 *     public function up()      { ... }
 *     public function down()    { ... }
 * };
 * ```
 *
 * 使用示例：
 * ```php
 * $p = new Deployment();
 *
 * // 升级到目标版本（每次升级后自动持久化 .version）
 * $p->upgrade('2.0.0');
 *
 * // 回滚到指定版本（每次回滚后自动持久化 .version）
 * $p->rollback('1.0.0');
 *
 * // 查看待执行迁移
 * $pending = $p->getPendingMigrations('2.0.0');
 *
 * // 查询当前状态
 * $status = $p->getStatus();
 * ```
 */
class Deployment extends AbilityBaseObject
{
  /**
   * 完整版本号（.version 文件原文，如 2.2.0.20260721.1746）
   * @var string|null
   */
  protected $latestVersion = null;

  /**
   * 从完整版本号中提取的三段基础版本号（如 2.2.0），用于迁移基线与目标版本判断
   * @var string
   */
  protected $currentSemver = '0.0.0';

  /**
   * 迁移目录（null 时默认为 {插件目录}/Deployment/Migrations）
   *
   * @var string|null
   */
  protected $migrationsDir = null;

  /**
   * @param string|null $migrationsDir 迁移目录，null 时默认为 `{插件目录}/Deployment/Migrations`
   */
  public function __construct($migrationsDir = null)
  {
    if (DiscuzApp::exists()) {
      $app = Applications::default();

      //* 插件布局：迁移文件在插件目录下；版本真源是 Discuz 的插件设置，不读 `.version`
      $this->migrationsDir = $migrationsDir ?? Path::join(Path::root(), "Deployment", "Migrations");
      $this->latestVersion = $app ? \getglobal("setting/plugins/version/" . $app->id()) : null;
      $this->currentSemver = self::parseSemver((string) $this->latestVersion);

      //* 后台升级会带上当前版本，比插件设置更权威（换机、手工改版本号后仍能正确建立基线）
      if ($app && $app->request()->query->has("fromversion")) {
        $this->currentSemver = self::parseSemver($app->request()->query->get("fromversion"));
      }

      return;
    }

    $this->migrationsDir = $migrationsDir ?? Path::join(Path::root(), "Deployment", "Migrations");

    $versionFile = Path::join(Path::data(), ".version");
    if (file_exists($versionFile)) {
      $this->latestVersion = trim(file_get_contents($versionFile));
      $this->currentSemver = $this::parseSemver($this->latestVersion);
    }
  }

  /**
   * 从完整版本号中提取三段基础版本号
   *
   * 2.2.0.20260721.1746 → 2.2.0
   * 1.0 → 1.0（不足三段原样返回）
   *
   * @param string $fullVersion 完整版本号
   * @return string 基础版本号
   */
  public static function parseSemver(string $fullVersion): string
  {
    $parts = explode('.', $fullVersion);
    if (count($parts) >= 3) {
      return implode('.', array_slice($parts, 0, 3));
    }
    return $fullVersion;
  }
  /**
   * 比较两个版本号
   *
   * 先各自提取三段基础版本号（见 parseSemver），再交给 version_compare 比较。
   * 传 $operator 返回 bool（如 ">="、"<"）；不传返回 int（-1 / 0 / 1）。
   *
   * @param string $version1 版本号 1
   * @param string $version2 版本号 2
   * @param string|null $operator 比较运算符（>、>=、<、<=、==、!= 等），null 时返回 int
   * @return int|bool 无运算符时返回 -1/0/1；有运算符时返回 bool
   */
  public static function compare($version1, $version2, $operator = null)
  {
    $version1 = self::parseSemver($version1);
    $version2 = self::parseSemver($version2);

    return version_compare($version1, $version2, $operator);
  }
  /**
   * 首次安装：创建应用数据和存储目录
   *
   * @return $this
   */
  public function install()
  {
    if (!is_dir(Path::data())) {
      mkdir(Path::data(), 0755, true);
    }
    if (!is_dir(Path::storage())) {
      mkdir(Path::storage(), 0755, true);
    }
    return $this;
  }
  /**
   * 判断安装是否已锁定
   *
   * 检测数据目录下的 install.lock 文件是否存在，存在即视为已完成安装。
   *
   * @return bool 已锁定返回 true，否则 false
   */
  public function installLocked()
  {
    return file_exists(Path::join(Path::data(), "install.lock"));
  }
  /**
   * 写入安装锁文件，标记安装完成
   *
   * 在 data 目录下创建 install.lock，内容为当前时间戳。
   *
   * @return int|false 写入的字节数，失败返回 false
   */
  public function lockInstall()
  {
    return file_put_contents(Path::join(Path::data(), "install.lock"), time());
  }
  /**
   * 校验安装密钥
   *
   * 读取 install.key 内容并与传入的 $key 比对。
   * $key 为空时直接返回 false；install.key 不存在（无内容）时也返回 false。
   *
   * 注：方法名 "verifiy" 为历史拼写（正确应为 "verify"），为保持向后兼容未改名。
   *
   * @param string $key 待校验的安装密钥
   * @return bool 密钥一致返回 true，否则 false
   */
  public function verifiyInstallKey($key)
  {
    if (!$key)
      return false;
    $fileContent = file_get_contents(Path::join(Path::data(), "install.key"));
    return $fileContent ? $fileContent === $key : false;
  }
  /**
   * 重新生成并写入安装密钥
   *
   * 在 data 目录下写入一个新的随机安装密钥：`random_bytes` 取 16 字节密码学安全随机
   * 再转 32 位十六进制串。安装密钥用于 {@see verifiyInstallKey()} 的门禁比对，
   * 必须不可预测，故不用基于时间戳的 `uniqid()`（无熵、可枚举）。
   *
   * @return int|false 写入的字节数，失败返回 false
   */
  public function refreshInstallKey()
  {
    return file_put_contents(Path::join(Path::data(), "install.key"), bin2hex(\random_bytes(16)));
  }
  /**
   * 执行迁移
   *
   * 扫描 migrationsDir，执行 Data/migrations.json 中尚未记录的迁移。
   * 首次没有记录文件时，按当前插件版本把历史迁移标记为已执行，避免重复跑。
   *
   * @param string|null $targetVersion 目标版本号，null 表示执行全部待执行迁移
   * @return bool true=升级完成，false=升级失败，有错误
   */
  public function upgrade($targetVersion = null)
  {
    try {
      $this->migrator()->run($targetVersion);
    } catch (\Throwable $th) {
      return $this->break(500, 500, "升级失败: " . $th->getMessage(), $th);
    }

    $version = $targetVersion ?: ($this->app() ? $this->app()->config()->get("version") : null);
    if ($version) {
      $this->persistVersion($version);
    }

    return true;
  }
  /**
   * 将配置中的版本号持久化到 .version 文件
   *
   * 读取应用配置中的目标版本号（`version`）并写入 data 目录下的 .version。
   *
   * @return int|false 写入的字节数，失败返回 false
   */
  function updateVersion()
  {
    return file_put_contents(Path::join(Path::data(), ".version"), $this->app()->config()->get("version"));
  }
  /**
   * 读取当前持久化的完整版本号
   *
   * 返回 data 目录下 .version 文件的原文（如 2.2.0.20260721.1746）；
   * 文件不存在时返回 null。
   *
   * @return string|null
   */
  function version()
  {
    return file_get_contents(Path::join(Path::data(), ".version")) ?: null;
  }

  /**
   * 执行迁移回滚
   *
   * 传 targetVersion 时回滚版本高于目标的迁移；不传时回滚最后一个 batch。
   *
   * @param string|null $targetVersion 回滚目标版本号；null 表示回滚最后一个 batch
   * @return bool true=回滚完成，false=回滚失败，有错误
   */
  public function rollback($targetVersion = null)
  {
    try {
      $this->migrator()->rollback($targetVersion);
    } catch (\Throwable $th) {
      return $this->break(500, 500, "回滚失败: " . $th->getMessage(), $th);
    }

    if ($targetVersion) {
      $this->persistVersion($targetVersion);
    }

    return true;
  }

  /**
   * 迁移执行器
   *
   * @return Migrator
   */
  private function migrator()
  {
    return new Migrator(
      $this->migrationsDir(),
      Path::join(Path::data(), "migrations.json"),
      $this->currentSemver
    );
  }

  /**
   * 安装完成后把当前迁移标记为已执行
   *
   * 新装环境直接按当前模型建表，无需再跑历史迁移；已有记录时不覆盖。
   *
   * @return bool
   */
  public function markMigrationsApplied()
  {
    try {
      return $this->migrator()->markAllApplied();
    } catch (\Throwable $th) {
      return $this->break(500, 500, "写入迁移基线失败: " . $th->getMessage(), $th);
    }
  }

  /**
   * 获取迁移目录路径
   *
   * @return string 迁移目录（绝对路径）
   */
  private function migrationsDir(): string
  {
    return $this->migrationsDir ?? Path::join(Path::root(), "Deployment", "Migrations");
  }
  /**
   * 持久化当前版本号到 .version 文件
   *
   * @param string $version 要写入的版本号
   */
  private function persistVersion($version)
  {
    $versionFile = Path::join(Path::data(), ".version");
    file_put_contents($versionFile, $version);
    $this->latestVersion = $version;
    $this->currentSemver = $this::parseSemver($version);
  }
  /**
   * 卸载：删除 .version 文件
   *
   * @return void
   */
  public function uninstall()
  {
    $versionFile = Path::join(Path::data(), ".version");
    if (file_exists($versionFile)) {
      unlink($versionFile);
    }

    if (DiscuzApp::exists()) {
      foreach ([Path::data()] as $directory) {
        if ($directory && is_dir($directory)) {
          FileSystem::deleteDirectory($directory);
        }
      }
    }
  }

  /**
   * 获取应用当前状态快照
   *
   * @return array{app_id: string|null, current_version: string, latest_version: string|null, migrations_dir: string, data_dir: string|null}
   */
  public function getStatus(): array
  {
    return [
      'app_id' => Applications::defaultId(),
      'current_version' => $this->currentSemver,
      'latest_version' => $this->latestVersion,
      'migrations_dir' => $this->migrationsDir(),
      'data_dir' => Path::data(),
    ];
  }

  /**
   * 获取待执行迁移 ID
   *
   * @param string|null $targetVersion 目标版本
   * @return array 迁移 ID，按升序排列
   */
  public function getPendingMigrations($targetVersion = null): array
  {
    return $this->migrator()->pending($targetVersion);
  }

  /**
   * getPendingMigrations() 的兼容别名
   *
   * @param string|null $targetVersion 目标版本
   * @return array
   */
  public function getPendingUpgrades($targetVersion = null): array
  {
    return $this->getPendingMigrations($targetVersion);
  }

  /**
   * 强制重置当前版本号
   *
   * 直接修改内存状态和 .version 文件，不执行任何升级或回滚逻辑。
   * 适用于手动修正版本号、初始化新环境的基准版本等场景。
   *
   * @param string $version 要设置的目标版本号
   * @return $this
   */
  public function resetVersion(string $version)
  {
    $this->persistVersion($version);
    return $this;
  }
}
