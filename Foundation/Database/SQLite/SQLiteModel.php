<?php

namespace kernel\Foundation\Database\SQLite;

use kernel\Foundation\BaseObject;

class SQLiteModel extends BaseObject
{
  /**
   * 表名称
   *
   * @var string
   */
  protected $tableName = "";
  /**
   * 表文件名，包含路径。相对于F_APP_ROOT的路径地址
   *
   * @var string
   */
  protected $tableFileName = "";

  /**
   * SQLite实例
   *
   * @var SQLite
   */
  protected $SQLite = null;
  /**
   * 实例化模型
   *
   * @param int $flags 可选的 flag，用于确定如何打开 SQLite 数据库。默认使用 SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE 打开。  
   * - SQLITE3_OPEN_READONLY：以只读方式打开数据库。  
   * - SQLITE3_OPEN_READWRITE：以读写方式打开数据库。  
   * - SQLITE3_OPEN_CREATE：如果数据库不存在，则创建数据库。  
   * @param string $encryptionKey 加密和解密 SQLite 数据库时使用的可选加密密钥。如果未安装 SQLite 加密模块，则此参数无效。
   */
  public function __construct($flags = SQLITE3_OPEN_READWRITE, $encryptionKey = null)
  {
    if (!$this->SQLite) {
      $this->SQLite = new SQLite($this->tableFileName, $flags, $encryptionKey);
    }
  }
  public function fetchAll($sql, $mode = SQLITE3_ASSOC)
  {
    return $this->SQLite->fetchAll($sql, $mode);
  }
  public function fetch($sql, $mode = SQLITE3_ASSOC)
  {
    return $this->SQLite->fetch($sql, $mode);
  }
  public function fetchOne($sql, $mode = SQLITE3_ASSOC)
  {
    return $this->SQLite->fetchOne($sql, $mode);
  }
  public function query($sql)
  {
    return $this->SQLite->query($sql);
  }
}
