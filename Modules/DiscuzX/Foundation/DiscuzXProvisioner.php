<?php

namespace kernel\Modules\DiscuzX\Foundation;

use kernel\Foundation\FileSystem\FileSystem;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\Provisioner;

//* 仅允许在 Discuz 后台（admincp）上下文中载入：本类会建目录 / 删目录，不能被 URL 直接访问触发
if (!defined("IN_DISCUZ") || !defined('IN_ADMINCP')) {
  exit('Access Denied');
}

/**
 * DiscuzX Provisioner: Install / Upgrade / Uninstall
 *
 * 安装 / 升级 / 卸载编排器 —— 内核 {@see Provisioner} 的 DiscuzX 实现。
 *
 * 调用方式（各插件包根目录的 install.php / uninstall.php / upgrade.php，
 * 由插件 XML 的 installfile / uninstallfile / upgradefile 声明）：
 * ```php
 * // 前置：内核自动加载器已注册，且当前应用已启动（new DiscuzXApp($pluginId)）
 * $Iuu = new DiscuzXProvisioner();          // install.php
 * $Iuu->install();
 *
 * $Iuu = new DiscuzXProvisioner();          // uninstall.php
 * $Iuu->uninstall();
 *
 * $Iuu = new DiscuzXProvisioner();          // upgrade.php：是否升级、升到哪个版本由 ?fromversion= 决定
 * $Iuu->upgrade()->cleanUpgrade();
 * ```
 *
 * 与父类的分工：
 * - 复用父类的版本与升级机制（`.version`、升级脚本扫描与执行、`install.key`、`install.lock`）；
 * - 叠加 DiscuzX 特有的三点：
 *   1. **应用与路径取自「当前应用」**：构造函数不接收参数，用 `getApp()` 取 `id()`，
 *      因此要求 App 已启动，`Path::root()` / `Path::data()` 才会指向本插件（详见构造函数的「前置条件」）；
 *   2. **升级基线可由 Discuz 的 `?fromversion=` 提供**：从 `$app->request()->query` 读取该参数，
 *      传入后覆盖父类从 `.version` 推导的基线（见构造函数）；
 *   3. **数据目录落到 Discuz 惯例位置**：`{projectRoot}/data/plugindata/{appId}`
 *      （见 {@see DiscuzXPath::data()}、{@see DiscuzXPath::storage()}）。
 *
 * 生命周期：
 * - `install()`   ：先调父类（创建内核侧 Data / Storage 目录），再补建 Discuz 侧 plugindata 目录；
 * - `upgrade()`   ：执行父类的增量升级，并**适配为可链式**（父类返回 bool，调用方写法是 `->cleanUpgrade()`）；
 * - `uninstall()` ：先调父类（删除 `.version`），再删除 Discuz 侧 plugindata 目录；
 * - `clean()`     ：`cleanInstall()` + `cleanUpgrade()`，并删除 `{appRoot}/Provisioner` 目录本身。
 *
 * 注意（改动前请先了解这四点）：
 * 1. **前置条件：App 必须已注册**。构造函数用 `getApp()` 取当前应用（`$app->id()`、
 *    `$app->request()->query`），未启动 App 时 `getApp()` 返回 null，`$app->id()` 会直接致命错误；
 *    同时 `CHARSET` 需已定义（`DiscuzXApp` 构造时会兜底定义为 utf-8）；
 * 2. 本文件顶部有 `IN_DISCUZ` + `IN_ADMINCP` 双重守卫，只能在 Discuz 后台上下文被载入，
 *    否则直接 `exit('Access Denied')`；
 * 3. `cleanInstall()` / `cleanUpgrade()` 删除的是 `{appRoot}/Provisioner/{Install,Upgrade}`
 *    （扩展安装包 `ExtensionProvisioner` 使用的布局），与插件自身的升级脚本目录并不重合 ——
 *    升级后调 `cleanUpgrade()` 不会清理掉实际执行过的脚本，如需清理请另行确认目标目录；
 * 4. **升级脚本目录固定为 `{appRoot}/Upgrades`**，而仓库内现有脚本实际放在
 *    `{appRoot}/Iuu/Upgrades`（super_app）与 `{appRoot}/Iuu/Upgrade`（community / employment）下。
 *    两者不一致时：父类 `scanUpgradeFiles()` 会扫不到脚本（视作无升级），
 *    且其 `buildUpgradeClassName()` 按「相对 `Path::root()` 的目录」推导类名，
 *    推出的 `{appId}\Upgrades\Upgrade_x_y_z` 也与现有脚本的命名空间不符。
 *    要让升级真正生效，需把脚本迁到 `Upgrades/`，或在构造时改传实际目录。
 *
 * @see Provisioner  父类：版本持久化、增量升级 / 回滚、安装锁与密钥
 * @see DiscuzXPath  DiscuzX 路径（data / storage 等）
 */
class DiscuzXProvisioner extends Provisioner
{
  /**
   * 构造（无参数，全部从「当前应用」推导）
   *
   * 顺序：
   *   1. `parent::__construct({appRoot}/Upgrades)` —— 与父类签名对齐：
   *      父类第一个形参是**升级脚本目录**（`Provisioner::__construct($upgradesDir = null)`）；
   *   2. 取 Discuz 侧已安装版本（插件设置 `setting.plugins.version.{appId}`）作为 `latestVersion`；
   *   3. 若请求带 `fromversion`，用它覆盖父类从 `.version` 推导出的升级基线 `currentSemver`
   *      —— Discuz 后台升级带上的是当前实际版本，比 `.version` 更权威
   *      （换机、手工改版本号后仍能正确计算增量）。
   *
   * 前置条件（重要）：调用前必须已经注册当前应用（例如先 `new DiscuzXApp($pluginId)`），
   * 否则 `getApp()` 返回 null → `$app->id()` 致命错误；同时 `Path::root()` 会因 `App::id()` 为空而失效。
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct(DiscuzXPath::join(DiscuzXPath::root(), "Provisioner", "Upgrades"));

    $app = getApp();

    $this->latestVersion = \getglobal("setting/plugins/version/" . $app->id());

    //* Discuz 后台升级会带上当前版本，比 .version 更权威（换机、手工改版本后仍能正确计算增量）
    if ($app->request()->query->has("fromversion")) {
      $this->currentSemver = self::parseSemver($app->request()->query->get("fromversion"));
    }
  }
  /**
   * 执行增量升级
   *
   * 逻辑完全交给父类（扫描升级目录 → 按版本升序执行 `Upgrade_x_y_z.php` → 逐个持久化版本号），
   * 这里只做一件事：**适配返回值** —— 父类返回 bool（成功 / 失败），
   * 而调用方写法是 `$Iuu->upgrade()->cleanUpgrade()`，因此统一返回 `$this` 以支持链式调用。
   *
   * 失败信息不会丢失：父类内部经 `break()` 记录在实例的错误状态上，
   * 调用方可用 `isError()` / `getErrorMessage()` 判断，或直接 `return()` 抛出。
   *
   * @param string|null $targetVersion 目标版本号，null 表示升级到脚本中可用的最高版本
   * @return static $this（便于链式调用 `->cleanUpgrade()`）
   */
  public function upgrade($targetVersion = null)
  {
    parent::upgrade($targetVersion);
    return $this;
  }
  /**
   * 安装
   *
   * 父类负责创建内核侧目录（`Path::data()`、`Path::storage()`），
   * 这里再补建 Discuz 惯例的数据目录 `{projectRoot}/data/plugindata/{appId}`
   * （已存在则跳过，0777 递归创建）。
   *
   * @return static $this，便于链式调用
   */
  public function install()
  {
    parent::install();
    $dataPluginDir = DiscuzXPath::data();
    if (!is_dir($dataPluginDir)) {
      mkdir($dataPluginDir, 0777, true);
    }
    return $this;
  }
  /**
   * 卸载
   *
   * 父类负责删除版本记录（`Path::data()/.version`），
   * 这里再删除 Discuz 侧数据目录 `{projectRoot}/data/plugindata/{appId}` 及其全部内容。
   *
   * 注意：不删除内核侧的 Data / Storage 目录（与 `install()` 不对称），
   * 需要彻底清理时请另行处理或调用 `clean()`。
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();
    FileSystem::deleteDirectory(DiscuzXPath::data());
  }
  /**
   * 清理安装 / 升级脚本目录
   *
   * 等价于 `cleanInstall()` + `cleanUpgrade()`，随后再删除 `{appRoot}/Provisioner` 目录本身
   * （安装或升级完成后释放脚本占用的目录）。
   *
   * @return bool 删除 `{appRoot}/Provisioner` 的结果（目录不存在时以底层删除结果为准）
   */
  public function clean()
  {
    $this->cleanInstall();
    $this->cleanUpgrade();
    return FileSystem::deleteDirectory(Path::join(Path::root(), "Provisioner"));
  }
  /**
   * 清理安装脚本目录
   *
   * 删除 `{appRoot}/Provisioner/Install`。
   *
   * @return bool 是否删除成功
   */
  public function cleanInstall()
  {
    return FileSystem::deleteDirectory(Path::join(Path::root(), "Provisioner", "Install"));
  }
  /**
   * 清理升级脚本目录
   *
   * 删除 `{appRoot}/Provisioner/Upgrade`。
   * 注意该目录并非本类实际使用的升级脚本目录（构造时用的是 `{appRoot}/Upgrades`，
   * 而现有脚本又在 `Iuu/Upgrade(s)` 下），因此升级后调用本方法不会清掉刚执行过的脚本。
   *
   * @return bool 是否删除成功
   */
  public function cleanUpgrade()
  {
    return FileSystem::deleteDirectory(Path::join(Path::root(), "Provisioner", "Upgrades"));
  }
}
