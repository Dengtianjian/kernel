<?php

namespace kernel\Model\Admin;

use kernel\Foundation\Database\PDO\Model;

/**
 * Order 模型
 */
class OrderModel extends Model
{
  /** @var string 数据表名 */
  public $tableName = "order";

  /** @var string 建表 SQL，用于 Provisioner/Iuu 安装时创建表 */
  public $tableStructureSQL = "";

  public function __construct()
  {
    parent::__construct($this->tableName);
  }
}
