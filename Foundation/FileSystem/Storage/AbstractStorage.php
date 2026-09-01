<?php

namespace kernel\Foundation\FileSystem\Storage;

use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 存储抽象基类
 *
 * 定义一套与具体存储后端无关的磁盘存储契约：本地磁盘、对象存储（OSS/S3 等）
 * 均继承本类并实现下列抽象方法，调用方只需面向 AbstractStorage 编程即可在
 * 不同存储之间无缝切换。
 *
 * 每个实例代表「一个磁盘」（通过 {@see name()} 标识），磁盘名称在构造时指定，
 * 默认名为 `local`。
 *
 * 子类约定：
 * - `get()`     读取文件元信息
 * - `put()`     写入/上传文件
 * - `delete()`  删除文件
 * - `exists()`  判断文件是否存在
 * - `url()`     返回文件可访问路径/URL
 *
 * 继承 {@see AbilityBaseObject}，因此具备 `setError()` / `return()` / `break()`
 * 等错误与中断处理能力（如 put 失败可通过 `break()` 返回错误态）。
 */
abstract class AbstractStorage extends AbilityBaseObject
{
  /**
   * 当前磁盘名称
   *
   * 用于区分不同的存储磁盘（如 `local`、对象存储桶名等）。
   *
   * @var string|null
   */
  protected $name = null;

  /**
   * 构造存储实例
   *
   * @param string $name 磁盘名称，默认 `local`
   */
  public function __construct($name = "local")
  {
    $this->name = $name;
  }

  /**
   * 获取当前磁盘名称
   *
   * @return string|null 构造时指定的磁盘名称
   */
  public function name()
  {
    return $this->name;
  }

  /**
   * 获取文件信息
   *
   * 返回指定文件的元信息数组；文件不存在时返回 false。
   *
   * @param string $fileName 文件名称（含相对路径，相对所属磁盘根目录）
   * @return false|array{name:string,disk:string,sourceFileName:string,path:string,extension:string,size:int,width:int|null,height:int|null,filePath:string} 文件信息数组，文件不存在时返回 false
   */
  abstract function get($fileName);

  /**
   * 写入/上传文件
   *
   * 子类负责将源文件保存到当前磁盘，并返回可访问的文件路径。
   * 失败时通常调用 {@see AbilityBaseObject::break()} 返回错误态。
   *
   * @param array $file 源文件描述数组（通常含 `name`/`tmp_name`/`type`/`size` 等，来自上传或本地数组）
   * @return string|mixed 成功时返回保存后的文件路径；失败时返回错误态（取决于子类实现）
   */
  abstract function put($file);

  /**
   * 删除文件
   *
   * @param string $fileName 文件名称（含相对路径）
   * @return boolean 删除结果（成功时通常为无错误的 return 态，失败语义由子类决定）
   */
  abstract function delete($fileName);

  /**
   * 判断文件是否存在
   *
   * @param string $fileName 文件名称（含相对路径）
   * @return bool 存在返回 true，否则 false
   */
  abstract function exists($fileName);

  /**
   * 获取文件可访问路径/URL
   *
   * @param string $fileName 文件名称（含相对路径）
   * @return string 文件在当前磁盘下的完整路径或可访问 URL
   */
  abstract function url($fileName);
}
