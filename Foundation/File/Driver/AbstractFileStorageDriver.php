<?php

namespace kernel\Foundation\File\Driver;

use kernel\Model\FilesModel;

abstract class AbstractFileStorageDriver extends AbstractFileDriver
{
  /**
   * 文件表模型实例
   *
   * @var FilesModel
   */
  protected $filesModel = null;

  /**
   * 实例化文件存储类
   *
   * @param string $SignatureKey 本地存储签名秘钥
   * @param boolean $Record 文件信息是否存入数据库
   * @param string $RoutePrefix 路由前缀
   */
  public function __construct($SignatureKey, $Record = TRUE, $RoutePrefix = "files")
  {
    parent::__construct($SignatureKey, $RoutePrefix);

    if ($Record) {
      $this->filesModel = new FilesModel();
    }
  }

  /**
   * 设置文件所属
   *
   * @param string $FileKey 文件名
   * @param string $BelongsId 所属ID
   * @param string $BelongsType 所属ID数据类型
   * @return int
   */
  public function setFileBelongs($FileKey, $BelongsId, $BelongsType)
  {
    return $this->filesModel->updateBelongs(
      $BelongsId,
      $BelongsType,
      $FileKey
    );
  }
}
