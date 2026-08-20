<?php

namespace kernel\Foundation;
use kernel\Foundation\FileSystem\Path;

use kernel\Foundation\FileSystem\FileHelper;


/**
 * 应用生命周期编排器：安装 / 增量升级 / 回滚 / 卸载
 *
 * 升级机制：
 * - 扫描 upgradesDir 目录下 Upgrade_x_y_z.php 文件，从文件名提取版本号按序执行
 * - 每个升级文件定义一个同名类，类有 upgrade() 方法则调用，否则构造器即升级逻辑
 * - 类可选定义 rollback() 方法以支持回滚
 *
 * 版本管理：
 * - .version 文件存储完整版本号（如 2.2.0.20260721.1746）
 * - 对比时自动提取前三段基础版本号（2.2.0）与升级脚本版本号做比较
 *
 * 升级脚本文件格式：
 * ```php
 * // 基础写法：构造器即升级逻辑
 * class Upgrade_1_1_0 { public function __construct() { ... } }
 *
 * // 推荐写法：显式 upgrade/rollback 方法
 * class Upgrade_1_1_0 {
 *     public function upgrade()  { ... }
 *     public function rollback() { ... }
 * }
 * ```
 *
 * 使用示例：
 * ```php
 * $p = new Provisioner();
 *
 * // 升级到目标版本（每次升级后自动持久化 .version）
 * $p->upgrade('2.0.0');
 *
 * // 回滚到指定版本（每次回滚后自动持久化 .version）
 * $p->rollback('1.0.0');
 *
 * // 查看待升级版本
 * $pending = $p->getPendingUpgrades('2.0.0');
 *
 * // 查询当前状态
 * $status = $p->getStatus();
 * ```
 */
class Provisioner
{
  /**
   * 完整版本号（.version 文件原文，如 2.2.0.20260721.1746）
   * @var string|null
   */
  protected ?string $latestVersion = null;

  /**
   * 从完整版本号中提取的三段基础版本号（如 2.2.0），用于与升级脚本版本号做 version_compare
   * @var string
   */
  protected string $currentSemver = '0.0.0';

  /**
   * @param ?string $upgradesDir 升级脚本目录，null 时默认为 {Path::root()}/Upgrades
   */
  public function __construct(
    protected ?string $upgradesDir = null,
  ) {
    $versionFile = FileHelper::combinedFilePath(Path::data(), ".version");
    if (file_exists($versionFile)) {
      $this->latestVersion = trim(file_get_contents($versionFile));
      $this->currentSemver = $this->parseSemver($this->latestVersion);
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
  private function parseSemver(string $fullVersion): string
  {
    $parts = explode('.', $fullVersion);
    if (count($parts) >= 3) {
      return implode('.', array_slice($parts, 0, 3));
    }
    return $fullVersion;
  }
  /**
   * 首次安装：创建应用数据和存储目录
   *
   * @return $this
   */
  public function install()
  {
    if (!is_dir(Path::data())) {
      mkdir(Path::data(), 0777, true);
    }
    if (!is_dir(Path::storage())) {
      mkdir(Path::storage(), 0777, true);
    }
    return $this;
  }
  /**
   * 执行增量升级
   *
   * 扫描 upgradesDir 目录下的 Upgrade_x_y_z.php 文件，从文件名提取版本号
   * （如 Upgrade_1_2_3.php → 1.2.3），过滤出 > currentSemver 且 ≤ targetVersion
   * 的脚本，按版本升序逐个执行。
   *
   * 每个升级脚本是一个类，执行逻辑：
   * 1. include 文件，从文件路径推导完全限定类名并实例化
   * 2. 若类有 upgrade() 方法则调用之，否则构造器本身即升级逻辑（向后兼容）
   *
   * @param string|null $targetVersion 目标版本号，null 表示升级到最新
   * @return $this|true 无升级文件或无需升级时返回 true，否则返回 $this
   * @throws \RuntimeException 升级脚本执行失败时抛出
   */
  public function upgrade($targetVersion = null): bool|Provisioner
  {
    $upgradeList = $this->scanUpgradeFiles();
    if (empty($upgradeList)) {
      return true;
    }

    ksort($upgradeList);

    $currentVersion = $this->currentSemver;
    foreach ($upgradeList as $version => $filePath) {
      if ($targetVersion && version_compare($version, $targetVersion, ">") === true) {
        break;
      }
      if (version_compare($currentVersion, $version, ">=") === true) {
        continue;
      }

      $this->runUpgrade($filePath, $version);
      $currentVersion = $version;
      $this->persistVersion($currentVersion);
    }

    return $this;
  }

  /**
   * 执行增量回滚
   *
   * 从当前版本降级到 $targetVersion，按版本降序执行回滚脚本。
   * 过滤出 ≤ currentSemver 且 > targetVersion 的脚本，逐个执行 rollback()。
   * 仅定义了 rollback() 方法的升级类参与回滚，无该方法则跳过。
   *
   * @param string $targetVersion 回滚目标版本号
   * @return $this|true 无升级目录或无需回滚时返回 true
   * @throws \RuntimeException 回滚脚本执行失败时抛出
   */
  public function rollback($targetVersion): bool|Provisioner
  {
    $upgradeList = $this->scanUpgradeFiles();
    if (empty($upgradeList)) {
      return true;
    }

    // 降序：从高版本向低版本回滚
    krsort($upgradeList);

    $currentVersion = $this->currentSemver;
    foreach ($upgradeList as $version => $filePath) {
      // 不高于目标版本，后续版本均不高于目标，无需继续
      if (version_compare($version, $targetVersion, "<=") === true) {
        break;
      }
      // 高于当前版本，无对应回滚脚本
      if (version_compare($version, $currentVersion, ">") === true) {
        continue;
      }

      $this->runRollback($filePath, $version);
      $currentVersion = $version;
      $this->persistVersion($version);
    }

    return $this;
  }

  /** 获取升级目录路径，未设置时返回默认路径 {Path::root()}/Upgrades */
  private function upgradesDir(): string
  {
    return $this->upgradesDir ?? FileHelper::combinedFilePath(Path::root(), "Upgrades");
  }

  /**
   * 扫描 upgradesDir 目录，匹配 Upgrade_x_y_z.php 文件并提取版本号
   *
   * @return array<string, string> 关联数组 [version => filePath]，如 ['1.1.0' => '/path/to/Upgrade_1_1_0.php']
   */
  private function scanUpgradeFiles(): array
  {
    $upgradesDir = $this->upgradesDir();
    if (!is_dir($upgradesDir)) {
      return [];
    }

    $upgradeList = [];
    $files = scandir($upgradesDir);

    foreach ($files as $file) {
      if (!preg_match('/^Upgrade_(\d+_\d+_\d+)\.php$/', $file, $matches)) {
        continue;
      }

      $version = str_replace('_', '.', $matches[1]);
      $upgradeList[$version] = FileHelper::combinedFilePath($upgradesDir, $file);
    }

    return $upgradeList;
  }

  /**
   * 从升级目录路径和文件名构建类的完全限定名
   *
   * 推导逻辑：upgradesDir 相对 Path::root() 的路径 → 目录分隔符转命名空间分隔符 → 拼接类短名
   * 例：upgradesDir=/app/Controller/Iuu/Upgrades/List, file=Upgrade_1_1_0.php
   *     → Controller\Iuu\Upgrades\List\Upgrade_1_1_0
   *
   * @param string $filePath 升级脚本完整路径
   * @return string 完全限定类名
   */
  private function buildUpgradeClassName(string $filePath): string
  {
    $shortName = pathinfo($filePath, PATHINFO_FILENAME);
    $relativePath = ltrim(str_replace(Path::root(), '', $this->upgradesDir()), '/');
    $namespace = str_replace('/', '\\', $relativePath);
    return $namespace . '\\' . $shortName;
  }

  /**
   * 执行单个升级脚本
   *
   * include 脚本文件 → 实例化类 → 若类定义了 upgrade() 方法则调用，
   * 否则仅实例化（构造器包含升级逻辑，向后兼容）。
   *
   * @param string $filePath 升级脚本文件路径
   * @param string $version  对应版本号
   * @throws \RuntimeException 脚本执行失败时抛出
   */
  private function runUpgrade(string $filePath, string $version): void
  {
    include($filePath);
    $className = $this->buildUpgradeClassName($filePath);

    if (!class_exists($className)) {
      throw new \RuntimeException("升级到 {$version} 失败: 类 {$className} 不存在，请检查 {($filePath)} 中的命名空间和类名是否正确");
    }

    try {
      $instance = new $className();
      if (method_exists($instance, 'upgrade')) {
        $instance->upgrade();
      }
    } catch (\Throwable $th) {
      throw new \RuntimeException("升级到 {$version} 失败: " . $th->getMessage(), 0, $th);
    }
  }

  /**
   * 执行单个回滚脚本
   *
   * include 脚本文件 → 实例化类 → 调用 rollback() 方法。
   * 若类未定义 rollback() 方法则跳过（不抛异常）。
   *
   * @param string $filePath 升级脚本文件路径
   * @param string $version  对应版本号
   * @throws \RuntimeException 回滚脚本执行失败时抛出
   */
  private function runRollback(string $filePath, string $version): void
  {
    include($filePath);
    $className = $this->buildUpgradeClassName($filePath);

    if (!class_exists($className)) {
      throw new \RuntimeException("回滚 {$version} 失败: 类 {$className} 不存在，请检查 {($filePath)} 中的命名空间和类名是否正确");
    }

    try {
      $instance = new $className();
      if (method_exists($instance, 'rollback')) {
        $instance->rollback();
      }
    } catch (\Throwable $th) {
      throw new \RuntimeException("回滚 {$version} 失败: " . $th->getMessage(), 0, $th);
    }
  }
  /**
   * 持久化当前版本号到 .version 文件
   *
   * @param string $version 要写入的版本号
   */
  private function persistVersion(string $version): void
  {
    $versionFile = FileHelper::combinedFilePath(Path::data(), ".version");
    file_put_contents($versionFile, $version);
    $this->latestVersion = $version;
    $this->currentSemver = $this->parseSemver($version);
  }
  /**
   * 卸载：删除 .version 文件
   *
   * @return void
   */
  public function uninstall()
  {
    $versionFile = FileHelper::combinedFilePath(Path::data(), ".version");
    if (file_exists($versionFile)) {
      unlink($versionFile);
    }
  }

  /**
   * 获取应用当前状态
   *
   * @return array 包含 appId, currentVersion, latestVersion, upgradeDir 等信息
   */
  public function getStatus(): array
  {
    return [
      'app_id' => App::id(),
      'current_version' => $this->currentSemver,
      'latest_version' => $this->latestVersion,
      'upgrade_dir' => $this->upgradesDir(),
      'data_dir' => Path::data(),
    ];
  }

  /**
   * 获取待升级的版本列表
   *
   * @param string|null $targetVersion 目标版本
   * @return array 待升级的版本号列表，按升序排列
   */
  public function getPendingUpgrades(?string $targetVersion = null): array
  {
    $upgradeList = $this->scanUpgradeFiles();
    if (empty($upgradeList)) {
      return [];
    }
    ksort($upgradeList);

    $pending = [];
    foreach ($upgradeList as $version => $_) {
      if ($targetVersion && version_compare($version, $targetVersion, ">") === true) {
        break;
      }
      if (version_compare($this->currentSemver, $version, ">=") === true) {
        continue;
      }
      $pending[] = $version;
    }
    return $pending;
  }

  /**
   * 强制重置当前版本号
   *
   * 直接修改内存状态和 .version 文件，不执行任何升级或回滚逻辑。
   * 适用于手动修正版本号、初始化新环境的基准版本等场景。
   *
   * @param string $version 要设置的目标版本号
   * @return Provisioner
   */
  public function resetVersion(string $version): Provisioner
  {
    $this->persistVersion($version);
    return $this;
  }
}
