<?php

namespace kernel\Foundation\File;

use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Object\DataObject;

/**
 * 文件信息
 * @property string $key 文件键
 * @property string $name 文件名称
 * @property string $path 文件路径
 * @property string $extension 文件扩展名
 * @property int $size 文件大小
 * @property string $filePath 文件保存路径
 * @property int $width 媒体文件宽度，非媒体文件该值为空
 * @property int $height 媒体文件高度，非媒体文件该值为空
 * @property boolean $remote 是否远程存储
 * @property string $previewURL 预览URL链接
 * @property string $downloadURL 下载URL链接
 */
class FileInfoData extends DataObject
{
  protected $key = NULL;
  protected $name = NULL;
  protected $path = NULL;
  protected $extension = NULL;
  protected $size = NULL;
  protected $filePath = NULL;
  protected $width = NULL;
  protected $height = NULL;
  protected $remote = FALSE;
  protected $previewURL = null;
  protected $downloadURL = null;
}
