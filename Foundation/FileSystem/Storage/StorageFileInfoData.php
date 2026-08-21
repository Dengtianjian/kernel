<?php

namespace kernel\Foundation\FileSystem\Storage;

use kernel\Foundation\FileSystem\FileHelper;
use kernel\Foundation\Object\DataObject;

/**
 * 文件信息
 * @property string $key 文件键
 * @property string $name 文件名称
 * @property string $sourceFileName 原文件名称
 * @property string $path 文件路径
 * @property string $extension 文件扩展名
 * @property int $size 文件大小
 * @property string $filePath 文件保存路径
 * @property int $width 媒体文件宽度，非媒体文件该值为空
 * @property int $height 媒体文件高度，非媒体文件该值为空
 * @property boolean $remote 是否远程存储
 * @property string $platform 存储平台
 * @property string $url 文件访问URL
 * @property string $previewURL 预览URL链接
 * @property string $downloadURL 下载URL链接
 * @property string $transferPreviewURL 中转预览URL链接
 * @property string $transferDownloadURL 中转下载URL链接
 * @property string $accessControl 访问权限控制
 * @property string $ownerId 所属用户标识
 */
class StorageFileInfoData extends DataObject
{
  protected $key = null;
  protected $name = null;
  protected $sourceFileName = null;
  protected $path = null;
  protected $extension = null;
  protected $size = null;
  protected $filePath = null;
  protected $width = null;
  protected $height = null;
  protected $remote = false;
  protected $platform = "local";
  protected $url = null;
  protected $previewURL = null;
  protected $downloadURL = null;
  protected $transferPreviewURL = null;
  protected $transferDownloadURL = null;
  protected $accessControl = false;
  protected $ownerId = false;

  public function __construct($data)
  {
    // 仅在 path 与 name 都提供、且未显式指定 filePath 时才自动拼接
    if (
      !(array_key_exists("filePath", $data) && $data['filePath'])
      && array_key_exists("path", $data)
      && array_key_exists("name", $data)
    ) {
      $data['filePath'] = FileHelper::combinedFilePath($data['path'], $data['name']);
    }

    parent::__construct($data);
  }
}
