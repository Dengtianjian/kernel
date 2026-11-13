<?php

namespace kernel\DiscuzX\Foundation\Storage;

use kernel\Foundation\Application\Applications;
use kernel\Foundation\FileSystem\FileSystem;
use kernel\Foundation\FileSystem\Path;
use kernel\Modules\Storage\Drivers\LocalStorage;
use kernel\Modules\Storage\StorageFile;
use kernel\DiscuzX\Foundation\DiscuzXPath;

/**
 * DiscuzX 本地存储（{@see LocalStorage} 的 DiscuzX 实现）
 *
 * 与父类 {@see LocalStorage} 的唯一分野在 {@see put()}：父类对 `$_FILES` 型入参
 * 直接经 {@see FileSystem::upload()} 以 `move_uploaded_file()` 落盘——无扩展名白名单，
 * 上架审核按「自写文件上传」记严重。本类把上传落盘改为**Discuz 官方上传类
 * `discuz_upload`**（`source/class/discuz/discuz_upload.php`，由 Discuz 的
 * `core::autoload` 按类名自动加载）：扩展名经 `discuz_upload::get_target_extension()`
 * 白名单过滤（不在白名单内一律落成 `.attach`），落盘用官方 `save()` / `save_to_local()`，
 * 图片走官方 `get_image_info()` 校验——审核口径为「经过官方上传机制」，合规。
 *
 * 字符串入参（CLI 临时文件、已下载到本地的文件等）不是「上传」，保留父类的
 * {@see FileSystem::upload()} `copy` 搬运语义，不做白名单处理。
 *
 * ## 两种存储位置归属（put 第三参 $location 二选一）
 *
 * | 归属 | 位置 | 落盘方式 | 与 get()/exists()/url() 的一致性 |
 * |---|---|---|---|
 * | {@see LOCATION_PLUGINDATA}（默认） | `{@see DiscuzXPath::storage()}`，即 `data/plugindata/{appId}/Storage/` | 官方类**静态方法**拼装：`fileext()` + `get_target_extension()`（白名单）+ `get_target_filename()`（随机名）+ `save_to_local()`（落盘）+ `get_image_info()`（图片校验）；目录与文件名拼装在本驱动内 | **一致**：落盘位置就在 {@see basePath()} 下，put 后 `get($saveFileName)` 可直接查到 |
 * | {@see LOCATION_ATTACHDIR} | Discuz 官方附件目录 `data/attachment/{type}/...`（`$attachType` 指定段，缺省取当前应用 ID） | 官方 `init()` + `save()` **全流程**（目录创建、白名单、落盘、图片校验全部官方） | **不一致**：落盘在 Discuz attachdir 体系内，不在 basePath 下，put 后 `get()` 查不到；返回的 StorageFile 信息完全来自 `$upload->attach`，filePath 指向 attachdir 下的绝对路径 |
 *
 * 选型指引：希望沿用磁盘抽象（put 后还能 get/delete/url）→ 用默认 plugindata；
 * 希望文件完全纳入 Discuz 附件体系（后台附件管理、与论坛附件同目录）→ 用 attachdir，
 * 但调用方要自己持有返回的 filePath，不能再用本驱动的 get()/delete() 管它。
 *
 * ## 使用前提
 *
 * `discuz_upload` 及其依赖（`getglobal`/`random`/`dstrlen`/`cutstr`/`dhtmlspecialchars`）
 * 都是 Discuz 运行环境函数，本类仅在 DiscuzX 上下文（`.inc.php` 经 {@see \kernel\DiscuzX\Foundation\DiscuzXApp}
 * 启动）中使用，不做环境判定——与 {@see \kernel\DiscuzX\Models\DiscuzXSystemModel} 同一口径。
 *
 * @see LocalStorage 父类（get/delete/exists/url 直接继承，均基于 basePath）
 * @see DiscuzXStorage DiscuzX 存储门面（多磁盘 + 签名鉴权）
 */
class DiscuzXLocalStorage extends LocalStorage
{
  /**
   * 存储位置归属：Discuz 官方附件目录
   *
   * 落 `data/attachment/{type}/...`，完全走官方 `init()` + `save()` 流程。
   * 代价：落盘位置不在 {@see basePath()} 下，put 后 {@see get()} 查不到。
   *
   * @var string
   */
  const LOCATION_ATTACHDIR = "attachdir";
  /**
   * 存储位置归属：插件数据目录（默认）
   *
   * 落 `{@see DiscuzXPath::storage()}`（`data/plugindata/{appId}/Storage/`），
   * 落盘用官方类的静态方法拼装（白名单/落盘/图片校验仍是官方实现）。
   * 与 {@see basePath()} 一致，put 后 {@see get()} 可查。
   *
   * @var string
   */
  const LOCATION_PLUGINDATA = "plugindata";

  /**
   * 构造 DiscuzX 本地磁盘
   *
   * 磁盘名固定 `local`（与父类一致）；basePath 取 {@see DiscuzXPath::storage()}
   * ——用户文件放站点 `data/plugindata/` 下，不写进会被升级覆盖的插件目录。
   */
  public function __construct()
  {
    $this->name = "local";
    $this->basePath = DiscuzXPath::storage();
  }

  /**
   * 保存/上传文件到本地磁盘
   *
   * 按入参类型分流：
   * - **字符串**：本地文件搬运（`copy` + 删源），非上传，不经白名单；
   * - **数组（`$_FILES` 单文件格式）**：真上传，经 Discuz 官方 `discuz_upload`
   *   落盘（扩展名白名单 + 官方落盘 + 图片校验），归属由 `$location` 决定。
   *
   * @param array|string $file `$_FILES` 单文件数组（需含 `tmp_name`/`name`/`size`），或本地文件路径字符串
   * @param string|null $saveFileName 保存后的文件名称（可含相对目录，如 `2026/01/avatar`）；
   *                                   缺省时上传取官方随机名、搬运取源文件名。
   *                                   注意 `attachdir` 归属只取其中的文件名段（官方校验
   *                                   `^[a-z0-9_]+$`，不合法自动改随机名），目录段会被忽略
   * @param string $location 存储位置归属：{@see LOCATION_PLUGINDATA}（默认）或 {@see LOCATION_ATTACHDIR}
   * @param string|null $attachType 仅 `attachdir` 归属有效：`data/attachment/` 下的目录段；
   *                                缺省取当前应用 ID（不合法时官方 `check_dir_type()` 兜底为 `temp`）
   * @return false|StorageFile 成功返回文件信息；失败返回 {@see AbilityBaseObject::break()} 错误态
   */
  public function put($file, $saveFileName = null, $location = self::LOCATION_PLUGINDATA, $attachType = null)
  {
    //* 字符串入参 = 本地搬运（CLI 临时文件、已落地的下载文件等），非上传
    if (is_string($file)) {
      return $this->moveIn($file, $saveFileName);
    }

    //* 上传入参先做统一前置校验
    if (!class_exists("discuz_upload")) {
      return $this->break(500, "Storage:DZX:500101", "Discuz 上传类不可用（discuz_upload）");
    }
    if (!is_array($file) || empty($file['tmp_name']) || empty($file['name'])) {
      return $this->break(400, "Storage:DZX:400102", "文件信息错误");
    }
    if (is_null($saveFileName) && is_string($file['name'])) {
      $saveFileName = basename($file['name']);
    }

    //* 保存名解析：剥出目录段与文件名段；目录穿越在此统一拒绝
    $dir = "";
    $fileNameOnly = null;
    if ($saveFileName !== null && $saveFileName !== "") {
      $normalized = str_replace("\\", "/", (string) $saveFileName);
      if (strpos($normalized, "..") !== false) {
        return $this->break(400, "Storage:DZX:400103", "非法保存路径");
      }
      $info = pathinfo(trim($normalized, "/"));
      $dir = trim((string) $info['dirname'], "./");
      $fileNameOnly = (string) $info['filename'];
    }

    if ($location === self::LOCATION_ATTACHDIR) {
      return $this->putToAttachDir($file, $fileNameOnly, $attachType);
    }

    return $this->putToPluginData($file, $dir, $fileNameOnly);
  }

  /**
   * 字符串入参的本地搬运（保留父类语义）
   *
   * 经 {@see FileSystem::upload()} 的字符串分支落盘（`copy` 成功后删源文件），
   * 落在 {@see basePath()} 下的 `$saveFileName` 相对位置，随后 {@see get()} 回读。
   *
   * @param string $file 源文件绝对/相对路径
   * @param string|null $saveFileName 保存后的文件名（含相对目录）；缺省取源文件名
   * @return false|StorageFile
   */
  private function moveIn($file, $saveFileName)
  {
    $saveFileName = $saveFileName ?: basename($file);
    $pathInfo = pathinfo($saveFileName);

    FileSystem::upload($file, Path::join($this->basePath(), $pathInfo['dirname']), $pathInfo['basename']);

    return $this->get($saveFileName);
  }

  /**
   * 归属一：官方附件目录（`data/attachment/{type}/...`）
   *
   * 全程走官方 `discuz_upload::init()` + `save()`：目录创建（`check_dir_exists`）、
   * 扩展名白名单（`get_target_extension`）、随机/指定文件名、落盘（`save_to_local`）、
   * 图片校验（`get_image_info`）均为官方实现，本方法只做入参适配与结果转译。
   *
   * 官方 `init()` 第五/六/七参（`$subdir`/`$dirtype`/`$filename`）中仅 `$filename`
   * 由调用方的 `$saveFileName` 文件名段映射（官方校验 `^[a-z0-9_]+$`，不合法自动改随机名），
   * 目录结构（`{Ym}/{d}/`）由官方固定，不接受调用方目录段。
   *
   * 注意：落盘位置在 Discuz attachdir 体系内、不在 {@see basePath()} 下，
   * put 后 {@see get()} 查不到；返回的 StorageFile 的 filePath 为 attachdir 下的绝对路径。
   *
   * @param array $file `$_FILES` 单文件数组
   * @param string|null $fileNameOnly 保存文件名段（已剥目录）；null 用官方随机名
   * @param string|null $attachType attachdir 下的目录段；null 取当前应用 ID
   * @return false|StorageFile
   */
  private function putToAttachDir($file, $fileNameOnly, $attachType)
  {
    $uploader = new \discuz_upload();

    $type = $attachType;
    if ($type === null || $type === "") {
      $type = (string) Applications::defaultId();
      if ($type === "") {
        $type = "temp";
      }
    }

    if (!$uploader->init($file, $type, 0, "", "", 1, (string) $fileNameOnly)) {
      return $this->break(400, "Storage:DZX:400104", "文件校验失败", [
        "errorCode" => $uploader->error()
      ]);
    }
    if (!$uploader->save()) {
      return $this->break(500, "Storage:DZX:500105", "文件保存失败", [
        "errorCode" => $uploader->error()
      ]);
    }

    $attach = $uploader->attach;
    $imageInfo = $attach['imageinfo'] ?? null;

    return new StorageFile([
      "name" => basename($attach['attachment']),
      "source_file_name" => $attach['name'],
      "path" => dirname($attach['attachment']),
      "extension" => $attach['extension'],
      "size" => $attach['size'],
      "width" => is_array($imageInfo) ? $imageInfo[0] : 0,
      "height" => is_array($imageInfo) ? $imageInfo[1] : 0,
      "filePath" => $attach['target']
    ]);
  }

  /**
   * 归属二：插件数据目录（`data/plugindata/{appId}/Storage/...`，默认）
   *
   * 官方 `discuz_upload` 的落盘目标固定在其 attachdir 体系内，无法直接落插件数据目录，
   * 因此本方法用官方类的**静态方法**拼装（与 `save()` 内部同一套安全件）：
   * - `fileext()` + `get_target_extension()`：扩展名白名单，不在白名单内一律 `.attach`；
   * - `get_target_filename()`：未指定文件名时的官方随机名（`date('His') + random(16)`）；
   * - `save_to_local()`：官方落盘（`is_upload_file` 校验 + `copy`/`move_uploaded_file`，
   *   成功后 `chmod 0644` 并删临时文件）；
   * - `get_image_info()`：图片/swf 内容校验，不通过即删文件并报错（与官方 `save()` 行为一致）。
   *
   * 与 {@see putToAttachDir()} 的差异：目录与文件名拼装在本驱动内（`{basePath()}/{dir}/{name}.{ext}`），
   * 落盘位置就在 {@see basePath()} 下，put 后 {@see get($saveFileName)} 可直接查到。
   *
   * @param array $file `$_FILES` 单文件数组
   * @param string $dir 相对 basePath 的目录段（已在 {@see put()} 里做穿越校验，可为空串）
   * @param string|null $fileNameOnly 文件名段（不含扩展名）；null/空 用官方随机名
   * @return false|StorageFile
   */
  private function putToPluginData($file, $dir, $fileNameOnly)
  {
    $basePath = $this->basePath();
    if ($basePath === null || $basePath === "") {
      return $this->break(500, "Storage:DZX:500106", "存储目录不可用（应用未初始化）");
    }

    //* 扩展名白名单：官方口径，不在白名单内的扩展名一律落成 .attach
    $extension = \discuz_upload::get_target_extension(\discuz_upload::fileext($file['name']));

    //* 文件名段：调用方指定时做安全过滤，否则用官方随机名
    $fileName = "";
    if ($fileNameOnly !== null && $fileNameOnly !== "") {
      $fileName = preg_replace("/[^\w.\-]/", "", $fileNameOnly);
    }
    if ($fileName === "") {
      $fileName = \discuz_upload::get_target_filename("local");
    }

    $target = Path::join($basePath, $dir, "{$fileName}.{$extension}");

    $uploader = new \discuz_upload();
    if (!$uploader->save_to_local($file['tmp_name'], $target)) {
      return $this->break(500, "Storage:DZX:500107", "文件保存失败");
    }

    //* 图片/swf 内容校验（与官方 save() 同口径）：不通过删文件并报错
    $imageInfo = null;
    if (\discuz_upload::is_image_ext($extension) || $extension === "swf") {
      $imageInfo = \discuz_upload::get_image_info($target, true);
      if (!$imageInfo) {
        @unlink($target);
        return $this->break(400, "Storage:DZX:400108", "图片文件校验失败");
      }
    }

    return new StorageFile([
      "name" => "{$fileName}.{$extension}",
      "source_file_name" => (string) $file['name'],
      "path" => $dir,
      "extension" => $extension,
      "size" => filesize($target),
      "width" => is_array($imageInfo) ? $imageInfo[0] : 0,
      "height" => is_array($imageInfo) ? $imageInfo[1] : 0,
      "filePath" => $target
    ]);
  }
}
