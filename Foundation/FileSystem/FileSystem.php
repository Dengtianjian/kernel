<?php

namespace kernel\Foundation\FileSystem;

use kernel\Foundation\Error;

/**
 * 文件系统总管理
 *
 * 负责文件系统的文件操作：上传、创建、复制、移动、删除、读取等。
 * 所有路径操作均通过 FileHelper 进行规范化处理，确保跨平台兼容。
 * 目录路径推导已抽离到 Path 类，本类仅保留实际文件动作。
 *
 * 无参构造（构造时确保应用 data/storage 目录存在），无任何静态属性、无缓存；
 * App 在 defineConstants() 之后 `new FileSystem` 即可。
 */
final class FileSystem
{
  /**
   * 无参构造，由 App 在 defineConstants() 之后实例化；构造时确保 data/storage 目录存在
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
  private static function ensureDirectories(): void
  {
    $appRoot = Path::root();
    if ($appRoot === null) {
      return;
    }
    self::ensureDirectory(FileHelper::combinedFilePath($appRoot, "Data"));
    self::ensureDirectory(FileHelper::combinedFilePath($appRoot, "Storage"));
  }

  /**
   * 上传文件并保存到服务器
   *
   * 支持两种上传方式：
   * - 通过 $_FILES 数组上传（HTTP POST 文件上传）
   * - 通过本地文件路径上传（用于服务端已存在的文件）
   *
   * 文件默认保存到 Path::storage() 指定的存储根目录下，
   * 通过 $savePath 参数可指定相对子目录。
   *
   * 对于图片类型的文件，会自动获取宽高信息。
   *
   * 使用示例：
   * ```php
   * // 通过 $_FILES 上传
   * $fileInfo = FileSystem::upload($_FILES['avatar'], 'avatars', 'user_123.jpg');
   *
   * // 通过本地路径上传
   * $fileInfo = FileSystem::upload('/tmp/export.csv', 'exports');
   * ```
   *
   * @param array|string $file 上传的文件。可为 $_FILES 数组中的某一项（包含 error/tmp_name/name/size 键），或本地文件的完整路径
   * @param string $savePath 保存的路径，相对于 Path::storage()。传入 "." 或空字符串表示直接保存在存储根目录
   * @param string|null $fileName 自定义存储文件名（不含扩展名）。未传入时自动使用 uniqid() 生成，扩展名从源文件名获取
   * @return array{name:string,sourceFileName:string,path:string|null,extension:string,size:int,filePath:string,width:int,height:int} 文件信息数组
   * @throws Exception 上传失败时抛出异常（错误码前缀 FileUpload 或 FileSave）
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
      if (!isset($file['error']) || $file['error'] > 0) {
        throw new Error("文件保存失败", 400, "FileUpload:400002:", $file['error'] ?? null);
      }
      if (!isset($file['tmp_name']) || !isset($file['name'])) {
        throw new Error("文件保存失败", 400, "FileUpload:400003");
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
      $path = FileHelper::combinedFilePath($savePath, $saveFullFileName);
      $folderPath = FileHelper::combinedFilePath(Path::storage(), $savePath);
      if (!is_dir($folderPath)) {
        mkdir($folderPath, 0700, true);
      }
    }
    // 确保存储根目录存在
    if (!is_dir(Path::storage())) {
      mkdir(Path::storage(), 0700, true);
    }

    $saveFullPath = FileHelper::combinedFilePath(Path::storage(), $path);
    if (is_string($file)) {
      if (!file_exists($file)) {
        throw new Error("文件保存失败", 500, "FileUpload:500002");
      }
      $saveResult = copy($filePath, $saveFullPath);
      unlink($filePath);
    } else {
      $saveResult = \move_uploaded_file($filePath, $saveFullPath);
    }

    if (!$saveResult) {
      throw new Error("文件保存失败", 500, "FileSave:500003", [
        "saveFullPath" => $saveFullPath,
        "filePath" => $filePath,
      ]);
    }

    $fileInfo = [
      "name" => $saveFullFileName,
      "sourceFileName" => $fileSourceName,
      "path" => $savePath ? FileHelper::optimizedPath($savePath) : null,
      "extension" => $fileExtension,
      "size" => $fileSize,
      "width" => 0,
      "height" => 0,

      "filePath" => FileHelper::optimizedPath($saveFullPath)
    ];
    if (FileHelper::isImage($saveFullPath)) {
      $imageInfo = \getimagesize($saveFullPath);
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
   * 使用示例：
   * ```php
   * // 将 templates/default 克隆到 themes/newtheme
   * FileSystem::cloneDirectory('/path/to/templates/default', '/path/to/themes/newtheme');
   * ```
   *
   * @param string $sourcePath 被克隆的目录路径
   * @param string $destPath 克隆到的目标目录路径，不存在时自动创建
   * @return void
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
      $sourceItem = FileHelper::combinedFilePath($sourcePath, $handle);
      $destItem = FileHelper::combinedFilePath($destPath, $handle);
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
   * 在指定路径创建文件并写入内容。父目录不存在时会自动创建。
   * 当 $overwrite 为 false 且文件已存在时，不会覆盖已存在的文件。
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
   * @return boolean 创建成功返回 true，失败返回 false
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
      $itemPath = FileHelper::combinedFilePath($path, $item);
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
   * 使用示例：
   * ```php
   * // 清空缓存目录，但保留 index.html
   * FileSystem::clearFolder('/path/to/cache', ['/path/to/cache/index.html']);
   * ```
   *
   * @param string $targetPath 被清除的文件夹路径
   * @param array $whiteList 清除时跳过的白名单。数组元素必须是完整的文件/目录路径（包含 $targetPath 前缀），例如 $targetPath 为 "a/b" 时，白名单元素为 "a/b/c/d" 则会跳过路径为 a/b/c/d 的文件或目录
   * @return boolean 清除成功返回 true，文件夹不存在或部分删除失败返回 false
   */
  public static function clearFolder($targetPath, $whiteList = [])
  {
    if (!is_dir($targetPath)) return false;

    $files = FileHelper::scandir($targetPath);
    if ($files === false || count($files) === 0) return true;

    $result = true;
    foreach ($files as $fileItem) {
      $path = FileHelper::combinedFilePath($targetPath, $fileItem);
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
      $pathItem = FileHelper::combinedFilePath($targetPath, $fileItem);
      $destPathItem = FileHelper::combinedFilePath($destPath, $fileItem);
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
   * 使用示例：
   * ```php
   * $info = FileSystem::getFileInfo('/storage/avatars/user_123.jpg');
   * echo $info['size'];   // 文件大小（字节）
   * echo $info['width'];  // 图片宽度，非图片时为 null
   * ```
   *
   * @param string $filePath 文件完整路径
   * @return false|array{name:string,sourceFileName:string,path:string,extension:string,size:int,width:int|null,height:int|null,filePath:string} 文件信息数组，文件不存在时返回 false
   */
  public static function getFileInfo($filePath)
  {
    $filePath = FileHelper::optimizedPath($filePath);
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
   * 删除单个文件
   *
   * @param string $filePath 文件完整路径
   * @return boolean 文件不存在时返回 true（视为已删除），删除操作返回 unlink 的实际结果
   */
  public static function deleteFile($filePath)
  {
    $filePath = FileHelper::optimizedPath($filePath);
    if (file_exists($filePath)) {
      return unlink($filePath);
    }

    return true;
  }
  /**
   * 读取文件内容
   *
   * 使用 file_get_contents 读取文件的全部内容。
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
    $filePath = FileHelper::optimizedPath($filePath);
    if (!file_exists($filePath)) {
      return false;
    }
    return file_get_contents($filePath);
  }
  /**
   * 复制单个文件
   *
   * 将源文件复制到目标路径。目标目录不存在时会自动创建。
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
   */
  public static function copyFile($sourcePath, $destPath, $overwrite = false)
  {
    $sourcePath = FileHelper::optimizedPath($sourcePath);
    $destPath = FileHelper::optimizedPath($destPath);

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
   */
  public static function moveFile($sourcePath, $destPath, $overwrite = false)
  {
    $sourcePath = FileHelper::optimizedPath($sourcePath);
    $destPath = FileHelper::optimizedPath($destPath);

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
   * @param integer $permissions 目录权限（八进制），默认 0755
   * @return boolean 目录已存在或创建成功返回 true，创建失败返回 false
   */
  public static function ensureDirectory($path, $permissions = 0755)
  {
    $path = FileHelper::optimizedPath($path);
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
    $filePath = FileHelper::optimizedPath($filePath);
    if (!file_exists($filePath)) {
      return false;
    }
    return filesize($filePath);
  }
}
