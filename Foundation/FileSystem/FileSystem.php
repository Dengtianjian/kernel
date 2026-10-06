<?php

namespace kernel\Foundation\FileSystem;

use kernel\Foundation\Exception\Error;

/**
 * 文件系统总管理
 *
 * 负责文件系统的基础文件动作：上传、创建、复制、移动、删除、读取，以及目录的克隆 / 清空 / 复制。
 *
 * 职责边界（本类只做「实际文件动作」，不含磁盘与鉴权）：
 * - 路径推导与拼接 → {@see Path}（root / data / storage / join / optimizedPath）
 * - 文件类型判断与目录扫描 → {@see FileHelper}（isImage、scandir 等）
 * - 带多磁盘、签名鉴权、元信息落库的存储门面 → {@see \kernel\Foundation\FileSystem\Storage\FileStorage}
 *
 * 约定：
 * - 全部方法均为**静态**，本类不持有任何状态（无静态属性、无缓存）；构造函数只做一件事：
 *   确保当前应用的 `Data` / `Storage` 目录存在，因此 App 在 `defineConstants()` 之后
 *   `new FileSystem()` 即可（未实例化 App 时会跳过，见 {@see ensureDirectories()}）；
 * - 路径参数会经 {@see Path::optimizedPath()} 规范化 —— 仅把 `/` 与 `\` 统一为当前系统的目录分隔符，
 *   **不解析** `..` / `.`、不做 realpath、也不校验存在性，故相对路径按「当前工作目录」解释；
 * - 失败处理有两种风格：多数方法用返回值表达失败（见各方法 `@return`），
 *   只有 {@see upload()} 直接抛 {@see Error}。
 *
 * @see Path 路径推导与规范化
 * @see FileHelper 文件类型判断与目录扫描
 */
final class FileSystem
{
  /**
   * 无参构造，由 App 在 defineConstants() 之后实例化；构造时确保 data/storage 目录存在
   *
   * 构造函数本身不保存任何状态，仅触发 {@see ensureDirectories()} 的目录准备，
   * 因此不存在「先 new 再调用」的时序要求 —— 所有方法都是静态的。
   */
  public function __construct()
  {
    self::ensureDirectories();
  }

  /**
   * 确保当前应用的 data/storage 目录存在，不存在则递归创建
   *
   * 未实例化 App（App::id() 为 null）时路径不可推导，直接跳过。
   */
  private static function ensureDirectories()
  {
    $appRoot = Path::root();
    if ($appRoot === null) {
      return;
    }
    self::ensureDirectory(Path::join($appRoot, "Data"));
    self::ensureDirectory(Path::join($appRoot, "Storage"));
  }

  /**
   * 上传（保存）文件
   *
   * 支持两种来源：
   * 1. **`$_FILES` 单文件数组**（需含 `error` / `tmp_name` / `name`，走 `move_uploaded_file()` 落盘）；
   * 2. **本地文件路径字符串**（CLI 临时文件、已下载到本地的文件等，走 `copy()` 落盘，
   *    并在复制成功后 **unlink 源文件** —— 是「搬运」语义，源文件会消失，别把还想保留的文件传进来）。
   *
   * 落盘位置：`$savePath` 有值时落到 `Path::join($savePath, 保存文件名)`，否则落到当前工作目录；
   * 保存文件名取 `$fileName`（缺省为 `uniqid()`）；**显式传入 `$fileName` 时扩展名以它为准**
   * （传入的文件名没带扩展名，保存后的文件也就没有扩展名），未传时沿用源文件名的扩展名。
   *
   * 返回值（保存后的名字 / 路径 + 原始信息；图片额外带宽高）：
   * ```php
   * [
   *   "name"         => "65f2c1a8b9.png",              // 保存后的文件名（不含目录）
   *   "sourceFileName" => "avatar.png",                // 原始文件名
   *   "path"         => "upload/2026",                 // 保存目录（经 optimizedPath）；未指定 $savePath 时为 null
   *   "extension"    => "png",
   *   "size"         => 20480,                         // 字节
   *   "width"        => 128,                           // 非图片为 0（注意不是 null）
   *   "height"       => 128,
   *   "filePath"     => "upload/2026/65f2c1a8b9.png",  // 完整文件路径
   * ]
   * ```
   *
   * 使用示例：
   * ```php
   * // 处理表单上传
   * $fileInfo = FileSystem::upload($_FILES['file'], Path::storage("upload/2026"));
   * echo $fileInfo['filePath'];   // 取得完整路径后交给存储层/模型
   *
   * // 搬运本地临时文件（源文件会被删除）
   * FileSystem::upload('/tmp/export.csv', 'export/2026', 'users.csv');
   * ```
   *
   * @param array|string $file `$_FILES` 单文件数组，或已存在的本地文件路径字符串
   * @param string $savePath 保存目录；目录不存在时按 **0757** 递归创建。
   *                         传 `"."` 等价于不传（落当前工作目录）
   * @param string|null $fileName 指定保存文件名（可含扩展名，扩展名以它为准）；不传则用 `uniqid()`
   * @return array 文件信息数组，键见上方示例
   *
   * @throws Error 以下情况会抛出（第三参为业务错误码）：
   *               - `$file` 为空 → `400` `FileUpload:400001`
   *               - 字符串入参取不到体积 → `500` `FileUpload:500001`
   *               - 上传出错（`$file['error'] > 0`）→ `400` `FileUpload:400002:`（原文如此，多一个冒号）
   *               - 数组缺少 `tmp_name` / `name` → `400` `FileUpload:400003`
   *               - 字符串入参的源文件不存在 → `500` `FileUpload:500002`
   *               - `move_uploaded_file()` / `copy()` 落盘失败 → `500` `FileSave:500003`
   *
   * 实现现状（与期望不一致，改动前留意）：
   * 1. `$savePath` 为空（含传 `"."`）时仍会执行 `is_dir(null)` 判断并 `mkdir(null, 0757, true)`：
   *    PHP 7 会告警 `mkdir(): Invalid path`，PHP 8 还会追加 `Passing null to parameter` 的 deprecation（实测）；
   * 2. 非图片时 `width` / `height` 返回 `0`，而 {@see getFileInfo()} 返回 `null`，两个方法的返回结构并不一致；
   * 3. 落盘权限用的是 `0757`（含 other 的执行位），与其它方法的 `0755` 不统一；
   * 4. 仅按 `FileHelper::isImage()`（MIME）判断，判定为图片后才调用 `getimagesize()`，
   *    但后者仍可能返回 false（损坏图片），此时 `$imageInfo[0]` 会告警。
   */
  public static function upload($file, $savePath, $fileName = null)
  {
    if (!$file) {
      throw new Error("请上传文件", 400, "FileUpload:400001");
    }
    $filePath = "";
    $fileSize = 0;
    $fileSourceName = "";

    if ($savePath === ".") {
      $savePath = null;
    }

    if (is_string($file)) {
      $filePath = $file;
      $fileSize = filesize($filePath);
      if ($fileSize === false) {
        throw new Error("文件保存失败", 500, "FileUpload:500001");
      }
      $fileSourceName = basename($filePath);
    } else {
      if (!isset($file['error']) && !isset($file['name']) && !isset($file['tmp_name'])) {
        throw new Error("文件信息错误", 400, "FileUpload:400002");
      }
      if (!isset($file['error']) || $file['error'] > 0) {
        throw new Error("文件保存失败", 400, "FileUpload:400003", $file['error'] ?? null);
      }
      if (!isset($file['tmp_name']) || !isset($file['name'])) {
        throw new Error("文件保存失败", 400, "FileUpload:400004");
      }
      $fileSourceName = basename($file['name']);
      $fileSize = $file['size'];
      $filePath = $file['tmp_name'];
    }

    $fileExtension = \pathinfo($fileSourceName, \PATHINFO_EXTENSION);
    if ($fileName) {
      $fileNameInfo = pathinfo($fileName);
      $fileName = $fileNameInfo['filename'] ?? '';
      $fileExtension = $fileNameInfo['extension'] ?? '';
    } else {
      $fileName = uniqid();
    }

    $saveFullFileName = $fileExtension ? "{$fileName}.{$fileExtension}" : $fileName;
    $path = $saveFullFileName;
    if ($savePath) {
      $path = Path::join($savePath, $saveFullFileName);
    }

    if (!is_dir($savePath)) {
      mkdir($savePath, 0757, true);
    }

    if (is_string($file)) {
      if (!file_exists($file)) {
        throw new Error("文件保存失败", 500, "FileUpload:500002");
      }
      $saveResult = copy($filePath, $path);
      unlink($filePath);
    } else {
      $saveResult = \move_uploaded_file($filePath, $path);
    }

    if (!$saveResult) {
      throw new Error("文件保存失败", 500, "FileSave:500003", [
        "saveFullPath" => $path,
        "filePath" => $filePath,
      ]);
    }

    $fileInfo = [
      "name" => $saveFullFileName,
      "sourceFileName" => $fileSourceName,
      "path" => $savePath ? Path::optimizedPath($savePath) : null,
      "extension" => $fileExtension,
      "size" => $fileSize,
      "width" => 0,
      "height" => 0,

      "filePath" => Path::optimizedPath($path)
    ];
    if (FileHelper::isImage($path)) {
      $imageInfo = \getimagesize($path);
      $fileInfo['width'] = $imageInfo[0];
      $fileInfo['height'] = $imageInfo[1];
    }

    return $fileInfo;
  }
  /**
   * 克隆目录
   *
   * 将源目录下的所有文件和子目录递归复制到目标目录。
   * 目标目录不存在时会自动创建。
   *
   * 与 {@see copyFolder()} 的区别：本方法更「轻」——**没有**白名单、**没有**失败回滚，
   * 且单个文件复制失败不会中断整体流程（`copy()` 的结果未被检查，也不返回成功与否）。
   * 需要「跳过指定文件」或「失败即回滚」时请用 {@see copyFolder()}。
   *
   * 使用示例：
   * ```php
   * // 将 templates/default 克隆到 themes/newtheme
   * FileSystem::cloneDirectory('/path/to/templates/default', '/path/to/themes/newtheme');
   * ```
   *
   * @param string $sourcePath 被克隆的目录路径（不存在时静默返回，不报错）
   * @param string $destPath 克隆到的目标目录路径，不存在时自动创建（权限 0755）
   * @return void 不返回结果，失败需自行检查目标目录内容
   * @see FileSystem::copyFolder() 带白名单和失败回滚的目录复制
   */
  public static function cloneDirectory($sourcePath, $destPath)
  {
    if (!is_dir($sourcePath)) {
      return;
    }
    if (!is_dir($destPath)) {
      mkdir($destPath, 0755, true);
    }

    $source = \opendir($sourcePath);
    if (!$source) {
      return;
    }
    while ($handle = \readdir($source)) {
      if ($handle == "." || $handle == "..") {
        continue;
      }
      $sourceItem = Path::join($sourcePath, $handle);
      $destItem = Path::join($destPath, $handle);
      if (is_dir($sourceItem)) {
        self::cloneDirectory($sourceItem, $destItem);
      } else {
        copy($sourceItem, $destItem);
      }
    }
    closedir($source);
  }
  /**
   * 创建文件
   *
   * 在指定路径创建文件并写入内容。父目录不存在时会自动创建（权限 0755）。
   *
   * 覆盖策略（`$overwrite`）：
   * - `false`（默认）：文件已存在时**直接返回 true**，不写入、不改动原文件
   *   （判定用的是 `file_exists()`，所以同名目录也会命中并返回 true）；
   * - `true`：以 `w+` 打开并覆盖写入（内容被截断后重写）。
   *
   * 使用示例：
   * ```php
   * // 创建新文件
   * FileSystem::createFile('/path/to/newfile.txt', 'Hello World');
   *
   * // 覆盖已存在的文件
   * FileSystem::createFile('/path/to/existing.txt', 'New Content', true);
   * ```
   *
   * @param string $filePath 文件完整路径（包含文件名和扩展名）
   * @param string $fileContent 写入的文件内容，默认为空字符串
   * @param boolean $overwrite 是否覆盖已存在的文件。true=覆盖，false=跳过（当文件已存在时直接返回 true）
   * @return boolean 创建成功返回 true，失败返回 false（`touch()` 失败、或 `fopen()` 打不开时为 false）
   *
   * 实现现状：`fwrite()` 的返回值未被检查（磁盘写满等只写入了部分内容时仍返回 true）。
   */
  public static function createFile($filePath, $fileContent = "", $overwrite = false)
  {
    if ($overwrite === false && \file_exists($filePath)) {
      return true;
    }
    $dirPath = \dirname($filePath);
    if (!is_dir($dirPath)) {
      mkdir($dirPath, 0755, true);
    }
    $touchResult = \touch($filePath);
    if ($touchResult) {
      $file = \fopen($filePath, "w+");
      if ($file === false) {
        return false;
      }
      \fwrite($file, $fileContent);
      \fclose($file);
      return true;
    } else {
      return false;
    }
  }
  /**
   * 删除目录及其所有子文件和子目录
   *
   * 递归删除指定目录下的所有内容，最后删除目录本身。
   * **注意：删除后无法恢复，请谨慎使用。**
   *
   * 删除过程中单个文件/目录的失败（`unlink()` / `rmdir()`）用 `@` 抑制错误、不中断流程；
   * 返回值以「最终目标目录是否还在」为准，因此部分删除失败时也可能返回 false 但目录已被清空。
   *
   * 使用示例：
   * ```php
   * // 删除临时目录
   * FileSystem::deleteDirectory('/path/to/temp');
   * ```
   *
   * @param string $path 要删除的目录路径
   * @return boolean 删除成功返回 true，目录不存在或删除失败返回 false
   */
  public static function deleteDirectory($path)
  {
    if (!is_dir($path)) {
      return false;
    }
    $items = @\scandir($path);
    if ($items === false) {
      return false;
    }
    foreach ($items as $item) {
      if ($item === "." || $item === "..") {
        continue;
      }
      $itemPath = Path::join($path, $item);
      if (is_dir($itemPath)) {
        self::deleteDirectory($itemPath);
      } else {
        @unlink($itemPath);
      }
    }
    @rmdir($path);
    return !is_dir($path);
  }
  /**
   * 清空文件夹内的所有内容（保留文件夹本身）
   *
   * 递归删除指定文件夹内的所有文件和子文件夹，但保留该文件夹本身。
   * 可通过 $whiteList 参数指定不删除的路径。
   *
   * 白名单按**完整路径精确匹配**（`in_array()` 全等比较），并在递归中继续生效：
   * 命中白名单的文件/目录会被整体跳过（目录不会被清空、也不会被删除）。
   * 子目录清空且 `rmdir()` 成功后会被删除，只有 `$targetPath` 本身始终保留。
   *
   * 使用示例：
   * ```php
   * // 清空缓存目录，但保留 index.html
   * FileSystem::clearFolder('/path/to/cache', ['/path/to/cache/index.html']);
   * ```
   *
   * @param string $targetPath 被清除的文件夹路径
   * @param array $whiteList 清除时跳过的白名单。数组元素必须是完整的文件/目录路径（包含 $targetPath 前缀），例如 $targetPath 为 "a/b" 时，白名单元素为 "a/b/c/d" 则会跳过路径为 a/b/c/d 的文件或目录
   * @return boolean 清除成功返回 true，文件夹不存在或部分删除失败返回 false
   *
   * 实现现状：子目录递归清理失败时**不会**再执行 `rmdir()`（保留该子目录），但整体流程继续，最终返回 false。
   */
  public static function clearFolder($targetPath, $whiteList = [])
  {
    if (!is_dir($targetPath)) return false;

    $files = FileHelper::scandir($targetPath);
    if ($files === false || count($files) === 0) return true;

    $result = true;
    foreach ($files as $fileItem) {
      $path = Path::join($targetPath, $fileItem);
      if (in_array($path, $whiteList)) continue;

      if (is_dir($path)) {
        if (!self::clearFolder($path, $whiteList) || !@rmdir($path)) {
          $result = false;
        }
      } else {
        if (!@unlink($path)) {
          $result = false;
        }
      }
    }

    return $result;
  }
  /**
   * 复制文件夹到目标目录
   *
   * 将指定目录下的所有文件和子目录复制到目标目录。
   * 目标目录不存在时会自动创建。复制过程中任一文件失败时，
   * 会自动回滚（删除已复制的内容）。
   *
   * 使用示例：
   * ```php
   * // 复制主题文件夹，跳过配置文件
   * $whiteList = ['/themes/newtheme/config.php'];
   * FileSystem::copyFolder('/themes/default', '/themes/newtheme', $whiteList);
   * ```
   *
   * @param string $targetPath 被复制的目录路径
   * @param string $destPath 复制到的目标目录路径
   * @param array $whiteList 路径白名单。数组元素必须是完整的文件/目录路径（包含 $destPath 前缀），在白名单中的路径会被跳过不复制
   * @return boolean 复制成功返回 true，失败返回 false（失败时会自动清理已复制的部分内容）
   *
   * 实现现状（两处与直觉不一致，改动前务必留意）：
   * 1. **白名单只在最外层生效**：递归调用 `self::copyFolder($pathItem, $destPathItem)` 未传递 `$whiteList`，
   *    因此子目录内部的路径无法被跳过；
   * 2. **回滚动作是 `deleteDirectory($destPath)`**：会删掉整个目标目录，
   *    包括本次复制之前就已经存在于 `$destPath` 中的内容（若目标目录是复用的，请先自行备份）。
   */
  public static function copyFolder($targetPath, $destPath, $whiteList = [])
  {
    if (!is_dir($targetPath)) {
      return false;
    }
    if (!is_dir($destPath)) {
      mkdir($destPath, 0755, true);
    }

    $files = FileHelper::scandir($targetPath);
    if ($files === false) return false;

    $result = true;
    foreach ($files as $fileItem) {
      $pathItem = Path::join($targetPath, $fileItem);
      $destPathItem = Path::join($destPath, $fileItem);
      if (in_array($destPathItem, $whiteList)) continue;

      if (is_dir($pathItem)) {
        $operationResult = self::copyFolder($pathItem, $destPathItem);
      } else {
        $operationResult = copy($pathItem, $destPathItem);
      }
      if (!$operationResult) {
        $result = false;
        break;
      }
    }

    if (!$result) {
      self::deleteDirectory($destPath);
    }

    return $result;
  }
  /**
   * 获取文件信息
   *
   * 返回文件的综合信息，包括名称、路径、扩展名、大小以及图片的宽高等。
   * 对于图片文件，会自动获取宽高尺寸。
   *
   * 与 {@see upload()} 的区别：入参必须是**已存在的本地文件路径**（否则返回 false），
   * 且不做任何移动/复制；返回结构中非图片的 `width` / `height` 为 `null`（`upload()` 里是 `0`）。
   *
   * 使用示例：
   * ```php
   * $info = FileSystem::getFileInfo('/storage/avatars/user_123.jpg');
   * echo $info['size'];   // 文件大小（字节）
   * echo $info['width'];  // 图片宽度，非图片时为 null
   * ```
   *
   * @param string $filePath 文件完整路径
   * @return false|array{name:string,sourceFileName:string,path:string,extension:string,size:int,width:int|null,height:int|null,filePath:string} 文件信息数组，文件不存在时返回 false
   *
   * 说明：`name` 与 `sourceFileName` 都取文件名的 basename；`path` 是 `dirname()` 的结果
   * （传入相对路径时也是相对路径）；图片判定基于文件内容（`mime_content_type`），
   * 但损坏图片下 `getimagesize()` 仍可能返回 false 并告警。
   */
  public static function getFileInfo($filePath)
  {
    $filePath = Path::optimizedPath($filePath);
    if (!file_exists($filePath)) {
      return false;
    }

    $fileInfo = pathinfo($filePath);
    $file = [
      "name" => $fileInfo['basename'],
      "sourceFileName" => $fileInfo['basename'],
      "path" => $fileInfo['dirname'],
      "extension" => $fileInfo['extension'] ?? '',
      "size" => filesize($filePath) ?: 0,
      "width" => null,
      "height" => null,

      "filePath" => $filePath
    ];
    if (FileHelper::isImage($filePath)) {
      $imageInfo = \getimagesize($filePath);
      $file['width'] = $imageInfo[0];
      $file['height'] = $imageInfo[1];
    }

    return $file;
  }
  /**
   * 判断文件是否存在
   *
   * 对传入路径做规范化后，调用 PHP 内置 file_exists 判断文件是否存在。
   * 与 {@see getFileInfo()}/{@see readFile()}/{@see fileSize()} 不同，本方法只关心存在性，
   * 不读取内容、不获取元信息，失败原因单一（不存在即返回 false）。
   *
   * 使用示例：
   * ```php
   * if (FileSystem::exists('/storage/avatars/user_123.jpg')) {
   *     // 文件存在，执行后续逻辑
   * }
   * ```
   *
   * @param string $filePath 文件完整路径
   * @return boolean 文件存在返回 true，不存在（或路径非法）返回 false
   *
   * 注意：`file_exists()` 对**目录**同样返回 true，本方法不区分文件与目录。
   */
  public static function exists($filePath)
  {
    $filePath = Path::optimizedPath($filePath);
    return file_exists($filePath);
  }
  /**
   * 删除单个文件
   *
   * @param string $filePath 文件完整路径
   * @return boolean 文件不存在时返回 true（视为已删除），删除操作返回 unlink 的实际结果
   */
  public static function deleteFile($filePath)
  {
    $filePath = Path::optimizedPath($filePath);
    if (file_exists($filePath)) {
      return unlink($filePath);
    }

    return true;
  }
  /**
   * 读取文件内容
   *
   * 使用 file_get_contents 读取文件的全部内容（一次性读入内存，适合配置/模板等小文件；
   * 大文件请自行分块读取，本方法不加文件锁）。
   *
   * 使用示例：
   * ```php
   * $content = FileSystem::readFile('/path/to/config.json');
   * if ($content !== false) {
   *     $config = json_decode($content, true);
   * }
   * ```
   *
   * @param string $filePath 文件完整路径
   * @return string|false 文件内容字符串，文件不存在时返回 false
   */
  public static function readFile($filePath)
  {
    $filePath = Path::optimizedPath($filePath);
    if (!file_exists($filePath)) {
      return false;
    }
    return file_get_contents($filePath);
  }
  /**
   * 复制单个文件
   *
   * 将源文件复制到目标路径。目标目录不存在时会自动创建（权限 0755）。
   * 当 $overwrite 为 false 且目标文件已存在时，不会覆盖。
   *
   * 使用示例：
   * ```php
   * // 复制文件（不覆盖已存在的目标文件）
   * FileSystem::copyFile('/path/to/source.txt', '/path/to/dest.txt');
   *
   * // 复制并覆盖目标文件
   * FileSystem::copyFile('/path/to/source.txt', '/path/to/dest.txt', true);
   * ```
   *
   * @param string $sourcePath 源文件完整路径
   * @param string $destPath 目标文件完整路径
   * @param boolean $overwrite 是否覆盖已存在的目标文件。true=覆盖，false=目标存在时返回 false
   * @return boolean 复制成功返回 true，失败返回 false
   * @see FileSystem::copyFolder() 复制整个目录
   *
   * 注意：目标已存在且 `$overwrite=false` 时返回 **false**（与 {@see createFile()} 同样情况下返回 true 不同），
   * 便于调用方区分「已跳过」与「已复制」。
   */
  public static function copyFile($sourcePath, $destPath, $overwrite = false)
  {
    $sourcePath = Path::optimizedPath($sourcePath);
    $destPath = Path::optimizedPath($destPath);

    if (!file_exists($sourcePath)) {
      return false;
    }
    if (file_exists($destPath) && !$overwrite) {
      return false;
    }

    $destDir = \dirname($destPath);
    if (!is_dir($destDir)) {
      mkdir($destDir, 0755, true);
    }

    return copy($sourcePath, $destPath);
  }
  /**
   * 移动或重命名文件
   *
   * 将源文件移动到目标路径。目标目录不存在时会自动创建。
   * 移动操作等同于重命名操作——源文件在操作成功后不再存在。
   * 当 $overwrite 为 true 且目标文件已存在时，会先删除目标文件再移动。
   *
   * 使用示例：
   * ```php
   * // 重命名文件
   * FileSystem::moveFile('/path/to/oldname.txt', '/path/to/newname.txt');
   *
   * // 移动文件到另一个目录
   * FileSystem::moveFile('/path/to/file.txt', '/another/path/file.txt');
   *
   * // 移动并覆盖目标文件
   * FileSystem::moveFile('/path/to/source.txt', '/path/to/dest.txt', true);
   * ```
   *
   * @param string $sourcePath 源文件完整路径
   * @param string $destPath 目标文件完整路径
   * @param boolean $overwrite 是否覆盖已存在的目标文件。true=先删除目标再移动，false=目标存在时返回 false
   * @return boolean 移动成功返回 true，失败返回 false
   *
   * 注意：底层用 `rename()`，**跨文件系统（不同挂载点/分区、部分云盘挂载）会失败并告警**，
   * 本方法不做「复制 + 删除」的降级处理；跨设备搬运请改用 {@see copyFile()} + {@see deleteFile()}。
   */
  public static function moveFile($sourcePath, $destPath, $overwrite = false)
  {
    $sourcePath = Path::optimizedPath($sourcePath);
    $destPath = Path::optimizedPath($destPath);

    if (!file_exists($sourcePath)) {
      return false;
    }
    if (file_exists($destPath)) {
      if (!$overwrite) {
        return false;
      }
      unlink($destPath);
    }

    $destDir = \dirname($destPath);
    if (!is_dir($destDir)) {
      mkdir($destDir, 0755, true);
    }

    return rename($sourcePath, $destPath);
  }
  /**
   * 确保目录存在
   *
   * 如果目录不存在，则递归创建目录。如果目录已存在，直接返回 true。
   *
   * 使用示例：
   * ```php
   * // 确保日志目录存在
   * FileSystem::ensureDirectory('/var/log/myapp');
   *
   * // 指定目录权限
   * FileSystem::ensureDirectory('/data/uploads', 0775);
   * ```
   *
   * @param string $path 目录完整路径
   * @param integer $permissions 目录权限（八进制），默认 0755；**仅在新建时生效**，
   *                             目录已存在时不会修改其权限（实际权限还会被系统 umask 影响）
   * @return boolean 目录已存在或创建成功返回 true，创建失败返回 false
   *
   * 注意：同名路径已存在**且是文件**时，`is_dir()` 为 false、`mkdir()` 会失败并告警，最终返回 false。
   */
  public static function ensureDirectory($path, $permissions = 0755)
  {
    $path = Path::optimizedPath($path);
    if (is_dir($path)) {
      return true;
    }
    return mkdir($path, $permissions, true);
  }
  /**
   * 获取文件大小（带错误处理）
   *
   * 先检查文件是否存在，再获取大小。相比直接调用 PHP 内置的 filesize()，
   * 此方法提供了存在性检查和路径规范化。
   *
   * 使用示例：
   * ```php
   * $size = FileSystem::fileSize('/path/to/file.txt');
   * if ($size !== false) {
   *     echo '文件大小: ' . FileHelper::humanReadableSize($size);
   * }
   * ```
   *
   * @param string $filePath 文件完整路径
   * @return integer|false 文件大小（字节），文件不存在或读取失败时返回 false
   * @see FileHelper::humanReadableSize() 将字节数转为可读格式
   */
  public static function fileSize($filePath)
  {
    $filePath = Path::optimizedPath($filePath);
    if (!file_exists($filePath)) {
      return false;
    }
    return filesize($filePath);
  }
}
