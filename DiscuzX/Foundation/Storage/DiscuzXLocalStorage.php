<?php

namespace kernel\DiscuzX\Foundation\Storage;

use kernel\Foundation\FileSystem\FileHelper;
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
 * `core::autoload` 按类名自动加载）：扩展名走「危险拒绝」策略（`FileHelper::hasDangerousExtension()`，
 * 服务端脚本/可执行/配置劫持类一律拒绝，其余类型**原样落盘**、不做白名单改写），落盘用官方
 * `save_to_local()`，图片走官方 `get_image_info()` 校验。
 *
 * 字符串入参（CLI 临时文件、已下载到本地的文件等）不是「上传」，保留父类的
 * {@see FileSystem::upload()} `copy` 搬运语义，不做白名单处理。
 *
 * ## 存储位置归属
 *
 * 统一落 `{@see DiscuzXPath::storage()}`，即 `data/plugindata/{appId}/Storage/`，
 * 与 {@see basePath()} 一致——put 后 `get($saveFileName)` / `delete()` / `url()` 可直接用。
 * 历史上的 `LOCATION_ATTACHDIR`（Discuz 官方附件目录）归属已移除：其落盘位置不在
 * basePath 下，put 后 `get()` 查不到（读写不对齐），且全库无调用方。
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
   * - **字符串**：本地文件搬运（`copy` + 删源），非上传，不做白名单处理；
   * - **数组（`$_FILES` 单文件格式）**：真上传，经 Discuz 官方 `discuz_upload`
   *   落盘（危险扩展名拒绝 + 官方落盘 + 图片校验）。
   *
   * @param array|string $file `$_FILES` 单文件数组（需含 `tmp_name`/`name`/`size`），或本地文件路径字符串
   * @param string|null $saveFileName 保存后的文件名称（可含相对目录，如 `2026/01/avatar`）；
   *                                   缺省时上传取官方随机名、搬运取源文件名
   * @return false|StorageFile 成功返回文件信息；失败返回 {@see AbilityBaseObject::break()} 错误态
   */
  public function put($file, $saveFileName = null)
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

    //* 保存名解析：剥出目录段、文件名段与扩展名段；目录穿越在此统一拒绝。
    //* 扩展名随 saveFileName 传递（与 StorageModule 落库 fileKey 同源，消除大小写/截断差异——F2）
    $dir = "";
    $fileNameOnly = null;
    $fileExtension = null;
    if ($saveFileName !== null && $saveFileName !== "") {
      $normalized = str_replace("\\", "/", (string) $saveFileName);
      if (strpos($normalized, "..") !== false) {
        return $this->break(400, "Storage:DZX:400103", "非法保存路径");
      }
      $info = pathinfo(trim($normalized, "/"));
      $dir = trim((string) $info['dirname'], "./");
      $fileNameOnly = (string) $info['filename'];
      $fileExtension = isset($info['extension']) ? (string) $info['extension'] : null;
    }

    return $this->putToPluginData($file, $dir, $fileNameOnly, $fileExtension);
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
    //* 穿越校验：字符串搬运分支绕过了 put() 的 .. 校验，这里补齐（对齐 put() 的拒绝口径）
    $normalized = str_replace("\\", "/", (string) $saveFileName);
    if (strpos($normalized, "..") !== false) {
      return $this->break(400, "Storage:DZX:400104", "非法保存路径");
    }
    //* 适配层策略：搬运目标名含危险扩展名段（.php/.htaccess 等，全段检查）时拒绝——搬运与上传同具落盘危害
    if (FileHelper::hasDangerousExtension($normalized)) {
      return $this->break(400, "Storage:DZX:400105", "文件类型不允许");
    }
    $pathInfo = pathinfo($saveFileName);

    $fileInfo = FileSystem::upload($file, Path::join($this->basePath(), $pathInfo['dirname']), $pathInfo['basename']);
    if (!$fileInfo) {
      return $this->break(500, "Storage:DZX:500109", "文件保存失败");
    }

    //* 回查用实际落盘名（upload 消毒可能改名），避免写读不一致
    $savedDir = ($pathInfo['dirname'] === "." || $pathInfo['dirname'] === "/") ? "" : $pathInfo['dirname'];
    return $this->get(Path::join($savedDir, $fileInfo['name']));
  }

  /**
   * 插件数据目录（`data/plugindata/{appId}/Storage/...`）
   *
   * 官方 `discuz_upload` 的落盘目标固定在其 attachdir 体系内，无法直接落插件数据目录，
   * 因此本方法用官方类的**静态方法**拼装（与 `save()` 内部同一套安全件）：
   * - `FileHelper::hasDangerousExtension()`：扩展名「危险拒绝」，其余类型**原样落盘**（不做白名单改写）；
   * - `get_target_filename()`：未指定文件名时的官方随机名（`date('His') + random(16)`）；
   * - `save_to_local()`：官方落盘（`is_upload_file` 校验 + `copy`/`move_uploaded_file`，
   *   成功后 `chmod 0644` 并删临时文件）；
   * - `get_image_info()`：图片/swf 内容校验，不通过即删文件并报错（与官方 `save()` 行为一致）。
   *
   * 目录与文件名拼装在本驱动内（`{basePath()}/{dir}/{name}.{ext}`），
   * 落盘位置就在 {@see basePath()} 下，put 后 {@see get($saveFileName)} 可直接查到。
   *
   * @param array $file `$_FILES` 单文件数组
   * @param string $dir 相对 basePath 的目录段（已在 {@see put()} 里做穿越校验，可为空串）
   * @param string|null $fileNameOnly 文件名段（不含扩展名）；null/空 用官方随机名
   * @param string|null $fileExtension 扩展名段（来自调用方 saveFileName，与落库 fileKey 同源）；
   *                                   null/空 时兜底取原上传文件名的扩展名
   * @return false|StorageFile
   */
  private function putToPluginData($file, $dir, $fileNameOnly, $fileExtension = null)
  {
    $basePath = $this->basePath();
    if ($basePath === null || $basePath === "") {
      return $this->break(500, "Storage:DZX:500106", "存储目录不可用（应用未初始化）");
    }

    //* 扩展名单一来源：优先取调用方 saveFileName 的扩展名段（与 StorageModule 落库 fileKey 完全
    //* 同源，消除 fileext() 的 strtolower/addslashes/截断差异——F2），未指定时兜底原上传名扩展名；
    //* 不再使用 get_target_extension 白名单改写（改写会把 pdf/docx 落成 .attach 致写读不一致——R1）
    $extension = $fileExtension !== null && $fileExtension !== ""
      ? $fileExtension
      : pathinfo($file['name'], PATHINFO_EXTENSION);

    //* 文件名段：调用方指定时仅接受字母数字/下划线/点/连字符与中文（拒绝式——消毒改名会使落库
    //* fileKey 与磁盘名不一致，F1）；未指定时用官方随机名
    $fileName = "";
    if ($fileNameOnly !== null && $fileNameOnly !== "") {
      if ($fileNameOnly === "." || $fileNameOnly === ".." || preg_match("/[^\w.\-]/u", $fileNameOnly)) {
        return $this->break(400, "Storage:DZX:400111", "保存文件名含非法字符");
      }
      $fileName = $fileNameOnly;
    }
    if ($fileName === "") {
      $fileName = \discuz_upload::get_target_filename("local");
    }

    //* 危险扩展名全段检查（拦 x.php.jpg 左解析类绕过）：对落盘全名与原上传名双侧覆盖
    $saveFullFileName = $extension !== "" ? "{$fileName}.{$extension}" : $fileName;
    if (FileHelper::hasDangerousExtension($saveFullFileName) || FileHelper::hasDangerousExtension($file['name'])) {
      return $this->break(400, "Storage:DZX:400110", "文件类型不允许");
    }

    $target = Path::join($basePath, $dir, $saveFullFileName);

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
      "name" => $saveFullFileName,
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
