<?php

namespace kernel\Foundation\FileSystem\Storage;

use kernel\Foundation\FileSystem\FileSystem;
use kernel\Foundation\FileSystem\Path;

use kernel\Foundation\FileSystem\FileHelper;

/**
 * 本地磁盘存储
 *
 * {@see AbstractStorage} 的本地实现：文件存储在由 {@see Path::storage()} 指向的
 * 本地存储目录下，路径经 {@see FileHelper::combinedFilePath()} 安全拼接，避免
 * 目录穿越。
 *
 * 磁盘名称固定为 `local`。
 */
class LocalStorage extends AbstractStorage
{
  /**
   * 构造本地存储实例
   *
   * 固定将磁盘名称设为 `local`。
   */
  public function __construct()
  {
    $this->name = "local";
  }

  /**
   * 获取本地文件信息
   *
   * 基于 {@see Path::storage()} 拼接出完整路径后，委托 {@see FileSystem::getFileInfo()}
   * 读取元信息，并补充 `disk = "local"` 字段。
   *
   * @param string $fileName 文件名称（相对 storage 根目录的路径）
   * @return false|array{name:string,disk:string,sourceFileName:string,path:string,extension:string,size:int,width:int|null,height:int|null,filePath:string} 文件信息数组，文件不存在时返回 false
   */
  public function get($fileName)
  {
    $filePath = FileHelper::combinedFilePath(Path::storage(), $fileName);

    $fileInfo = FileSystem::getFileInfo($filePath);
    if ($fileInfo) {
      $fileInfo['disk'] = "local";
    }

    return $fileInfo;
  }

  /**
   * 保存/上传文件到本地磁盘
   *
   * 通过 {@see FileSystem::upload()} 将源文件写入目标位置。目标文件名
   * 由 `$saveFileName` 决定（未传则使用源文件的 `name`）。失败时调用
   * {@see AbilityBaseObject::break()} 返回 500 错误态。
   *
   * @param array  $file         源文件描述数组（含 `name` 等上传信息）
   * @param string|null $saveFileName 保存后的文件名称（含相对路径）；为 null 时使用 `$file['name']`
   * @return false|array{name:string,disk:string,sourceFileName:string,path:string,extension:string,size:int,width:int|null,height:int|null,filePath:string} 成功时返回文件信息；失败时返回 false
   */
  public function put($file, $saveFileName = null)
  {
    $pathInfo = pathinfo($saveFileName ?: $file['name']);

    $fileInfo = FileSystem::upload($file, $pathInfo['dirname'], $pathInfo['basename']);
    if (!$fileInfo) {
      return $this->break(500, 500, "文件上传失败", TRUE);
    }

    return $this->get($fileInfo['filePath']);
  }

  /**
   * 删除本地文件
   *
   * 先经 {@see exists()} 确认文件存在（不存在返回 404 错误态），
   * 再以 {@see url()} 得到的完整路径委托 {@see FileSystem::deleteFile()} 执行物理删除。
   *
   * @param string $fileName 文件名称（相对 storage 根目录的路径）
   * @return mixed 成功返回 FileSystem::deleteFile 的结果（删除成功为 true）；文件不存在时返回 break 错误态
   */
  public function delete($fileName)
  {
    if (!$this->exists($fileName)) return $this->break(404, 404, "文件不存在");

    return FileSystem::deleteFile($this->url($fileName));
  }

  /**
   * 判断本地文件是否存在
   *
   * 以 {@see url()} 得到的完整路径进行 `file_exists` 判定。
   *
   * @param string $fileName 文件名称（相对 storage 根目录的路径）
   * @return bool 存在返回 true，否则 false
   */
  public function exists($fileName)
  {
    return file_exists($this->url($fileName));
  }

  /**
   * 获取本地文件的完整路径
   *
   * 将 {@see Path::storage()} 与 `$fileName` 经
   * {@see FileHelper::combinedFilePath()} 安全拼接。
   *
   * @param string $fileName 文件名称（相对 storage 根目录的路径）
   * @return string 文件在本地磁盘上的完整路径
   */
  public function url($fileName)
  {
    return FileHelper::combinedFilePath(Path::storage(), $fileName);
  }
}
