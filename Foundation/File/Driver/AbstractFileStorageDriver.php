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
   * @param string $BaseURL 基础地址
   */
  public function __construct($SignatureKey, $Record = TRUE, $RoutePrefix = "files", $BaseURL = F_BASE_URL)
  {
    parent::__construct($SignatureKey, $RoutePrefix, $BaseURL);

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
  /**
   * 删除相关类型&所属类型数据ID的文件
   *
   * @param string $BelongsId 所属ID
   * @param string $BelongsType 所属ID数据类型
   * @return int
   */
  public function deleteBelongsFile($BelongsId, $BelongsType)
  {
    return $this->filesModel->remove(
      true,
      null,
      $BelongsId,
      $BelongsType
    );
  }
  /**
   * 设置文件访问控制权限
   *
   * @param string $FileKey 文件名
   * @param string $AccessControlTag 文件控制权限标签
   * @return int
   */
  function setAccessControl($FileKey, $AccessControlTag)
  {
    return $this->filesModel->where("key", $FileKey)->update([
      "accessControl" => $AccessControlTag
    ]);
  }
}
