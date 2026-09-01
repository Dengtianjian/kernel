<?php

namespace kernel\Foundation\FileSystem\Storage;

use kernel\Foundation\Object\DataObject;

/**
 * 文件信息数据对象
 *
 * 存储单个文件的元数据信息，由磁盘驱动的 getFile() 返回。
 * 属性与文件模型（files 表）字段对应。
 *
 * @property int|null $id 文件记录 ID
 * @property string|null $key 文件键（文件路径 + 文件名称）
 * @property string|null $name 保存后文件名称
 * @property string|null $source_file_name 原文件名称
 * @property string|null $path 保存的文件路径（相对存储根目录）
 * @property string|null $extension 文件扩展名
 * @property float|null $size 文件尺寸（字节）
 * @property string|null $filePath 文件完整路径（含文件名）
 * @property float|null $width 宽度（媒体文件才有该值）
 * @property float|null $height 高度（媒体文件才有该值）
 * @property string|null $disk 存储磁盘名称
 * @property string|null $access_control 访问控制权限（PRIVATE/PUBLIC_READ 等）
 * @property string|null $owner_id 所属用户 ID
 * @property string|null $mime_type 文件 MIME 类型
 * @property string|null $ref 引用的 ID
 * @property string|null $type 引用的业务
 */
class StorageFile extends DataObject
{
  /**
   * 文件记录 ID
   *
   * @var int|null
   */
  protected $id = null;
  /**
   * 文件键（文件路径 + 文件名称）
   *
   * @var string|null
   */
  protected $key = null;
  /**
   * 保存后文件名称
   *
   * @var string|null
   */
  protected $name = null;
  /**
   * 原文件名称
   *
   * @var string|null
   */
  protected $source_file_name = null;
  /**
   * 保存的文件路径（相对存储根目录）
   *
   * @var string|null
   */
  protected $path = null;
  /**
   * 文件扩展名
   *
   * @var string|null
   */
  protected $extension = null;
  /**
   * 文件尺寸（字节）
   *
   * @var float|null
   */
  protected $size = null;
  /**
   * 文件完整路径（含文件名）
   *
   * @var string|null
   */
  protected $filePath = null;
  /**
   * 宽度（媒体文件才有该值）
   *
   * @var float|null
   */
  protected $width = null;
  /**
   * 高度（媒体文件才有该值）
   *
   * @var float|null
   */
  protected $height = null;
  /**
   * 存储磁盘名称
   *
   * @var string|null
   */
  protected $disk = null;
  /**
   * 访问控制权限（PRIVATE/PUBLIC_READ 等）
   *
   * @var string|null
   */
  protected $access_control = null;
  /**
   * 所属用户 ID
   *
   * @var string|null
   */
  protected $owner_id = null;
  /**
   * 文件 MIME 类型
   *
   * @var string|null
   */
  protected $mime_type = null;
  /**
   * 引用的 ID
   *
   * @var string|null
   */
  protected $ref = null;
  /**
   * 引用的业务
   *
   * @var string|null
   */
  protected $type = null;
}
