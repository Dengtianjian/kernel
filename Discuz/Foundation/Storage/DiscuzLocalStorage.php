<?php

namespace kernel\Discuz\Foundation\Storage;

use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\FileSystem\FileSystem;
use kernel\Foundation\FileSystem\Path;
use kernel\Modules\Storage\Drivers\LocalStorage;
use kernel\Modules\Storage\StorageFile;

/**
 * Discuz 本地存储（{@see LocalStorage} 的 Discuz 实现）
 *
 * 与父类 {@see LocalStorage} 的分野只有两点：
 * - basePath 取 {@see Path::storage()}——用户文件放站点
 *   `data/plugindata/{appId}/Storage/` 下，不写进会被升级覆盖的插件目录；
 * - {@see put()} 在落盘前做适配层策略校验：危险扩展名拒绝
 *   （{@see FileHelper::hasDangerousExtension()}，服务端脚本/可执行/配置劫持类
 *   一律拒绝、含 `shell.php.jpg` 全段绕过；其余类型原样落盘、不做白名单改写）。
 *
 * 落盘本身走基础插件 {@see FileSystem::upload()}（文件名消毒 / `..` 消解 /
 * 回查名一致），**不依赖 Discuz 官方 `discuz_upload` 类**——CLI 与 Web 行为一致，
 * 不存在官方类 `IN_DISCUZ` 守卫在 CLI 下终止进程的问题（原 R3 依赖源）。
 *
 * 字符串入参（CLI 临时文件、已下载到本地的文件等）不是「上传」，保留
 * {@see FileSystem::upload()} 的 `copy` 搬运语义。
 *
 * ## 存储位置归属
 *
 * 统一落 `data/plugindata/{appId}/Storage/`，与 {@see basePath()} 一致——
 * put 后 `get($saveFileName)` / `delete()` / `url()` 可直接用。历史上的
 * `LOCATION_ATTACHDIR`（Discuz 官方附件目录）归属已移除：其落盘位置不在
 * basePath 下，put 后 `get()` 查不到（读写不对齐），且全库无调用方。
 *
 * @see LocalStorage 父类（get/delete/exists/url 直接继承，均基于 basePath）
 * @see DiscuzStorage Discuz 存储门面（多磁盘 + 签名鉴权）
 */
class DiscuzLocalStorage extends LocalStorage
{
  /**
   * 构造 Discuz 本地磁盘
   *
   * 磁盘名固定 `local`（与父类一致）；basePath 取 {@see Path::storage()}。
   */
  public function __construct()
  {
    $this->name = "local";
    $this->basePath = Path::storage();
  }

  /**
   * 保存/上传文件到本地磁盘
   *
   * 按入参类型分流：
   * - **字符串**：本地文件搬运（`copy` + 删源），非上传；
   * - **数组（`$_FILES` 单文件格式）**：真上传，经基础插件 {@see FileSystem::upload()}
   *   落盘（文件名消毒 / `..` 消解 / 可选扩展名白名单）。
   *
   * 两种入参落盘前都做适配层策略校验：危险扩展名拒绝（对显式保存名与原上传名
   * 双侧覆盖）；显式指定的保存文件名段含非法字符（`[\w.\-]` 之外）时拒绝——
   * 底层消毒会改名，改名会使落库 fileKey 与磁盘名不一致。
   *
   * @param array|string $file `$_FILES` 单文件数组（需含 `tmp_name`/`name`/`size`），或本地文件路径字符串
   * @param string|null $saveFileName 保存后的文件名称（可含相对目录，如 `2026/01/avatar`）；
   *                                   缺省时上传取源文件名（底层消毒后的实际落盘名见返回值 `name`）
   * @return false|StorageFile 成功返回文件信息；失败返回 {@see AbilityBaseObject::break()} 错误态
   */
  public function put($file, $saveFileName = null)
  {
    //* 适配层策略：危险扩展名拒绝——对显式保存名与原上传名双侧覆盖
    $checkNames = [];
    if (is_array($file)) {
      $checkNames[] = $saveFileName ?: ($file['name'] ?? "");
      $checkNames[] = $file['name'] ?? "";
    } else {
      $checkNames[] = $saveFileName ?: basename($file);
    }
    foreach ($checkNames as $checkName) {
      if ((string) $checkName !== "" && FileHelper::hasDangerousExtension((string) $checkName)) {
        return $this->break(400, "Storage:DZX:400110", "文件类型不允许");
      }
    }

    //* 显式保存文件名段含非法字符（[\w.\-]/u 之外）时拒绝：底层消毒会改名，
    //* 改名会使落库 fileKey 与磁盘名不一致（自动生成的 fileKey 不受影响）
    if (is_array($file) && $saveFileName !== null && $saveFileName !== "") {
      $fileNameOnly = pathinfo(trim(str_replace("\\", "/", (string) $saveFileName), "/"), PATHINFO_FILENAME);
      if ($fileNameOnly === "." || $fileNameOnly === ".." || preg_match("/[^\w.\-]/u", $fileNameOnly)) {
        return $this->break(400, "Storage:DZX:400111", "保存文件名含非法字符");
      }
    }

    //* 字符串入参 = 本地搬运，非上传
    if (is_string($file)) {
      return $this->moveIn($file, $saveFileName);
    }

    //* 数组入参 = 上传：落盘走底层能力（父类 put 内部即 FileSystem::upload + 穿越校验 + 回查一致）
    return parent::put($file, $saveFileName);
  }

  /**
   * 字符串入参的本地搬运（保留父类语义）
   *
   * 经 {@see FileSystem::upload()} 的字符串分支落盘（`copy` 成功后删源文件），
   * 落在 {@see basePath()} 下的 `$saveFileName` 相对位置，随后 {@see get()} 回读
   * 实际落盘名（底层消毒可能改名）。
   *
   * @param string $file 源文件绝对/相对路径
   * @param string|null $saveFileName 保存后的文件名（含相对目录）；缺省取源文件名
   * @return false|StorageFile
   */
  private function moveIn($file, $saveFileName)
  {
    $saveFileName = $saveFileName ?: basename($file);
    //* 穿越校验：字符串搬运分支绕过了 put() 的 .. 校验，这里补齐（对齐 put() 的拒绝口径）
    $normalized = str_replace("\\", "/", (string) $saveFileName);
    if (strpos($normalized, "..") !== false) {
      return $this->break(400, "Storage:DZX:400104", "非法保存路径");
    }
    $pathInfo = pathinfo($saveFileName);

    $fileInfo = FileSystem::upload($file, Path::join($this->basePath(), $pathInfo['dirname']), $pathInfo['basename']);
    if (!$fileInfo) {
      return $this->break(500, "Storage:DZX:500109", "文件保存失败");
    }

    //* 回查用实际落盘名（底层消毒可能改名），避免写读不一致
    $savedDir = ($pathInfo['dirname'] === "." || $pathInfo['dirname'] === "/") ? "" : $pathInfo['dirname'];
    return $this->get(Path::join($savedDir, $fileInfo['name']));
  }
}
