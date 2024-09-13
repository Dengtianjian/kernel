<?php

namespace kernel\Foundation\Storage;

use kernel\Foundation\File\FileHelper;
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
  protected $key = NULL;
  protected $name = NULL;
  protected $sourceFileName = NULL;
  protected $path = NULL;
  protected $extension = NULL;
  protected $size = NULL;
  protected $filePath = NULL;
  protected $width = NULL;
  protected $height = NULL;
  protected $remote = FALSE;
  protected $platform = "local";
  protected $url = null;
  protected $previewURL = null;
  protected $downloadURL = null;
  protected $transferPreviewURL = null;
  protected $transferDownloadURL = null;
  protected $accessControl = FALSE;
  protected $ownerId = FALSE;

  public function __construct($data)
  {
    if (!array_key_exists("filePath", $data) || $data['filePath']) {
      $data['filePath'] = FileHelper::combinedFilePath($data['path'], $data['name']);
    }

    parent::__construct($data);
  }
}
