<?php

namespace kernel\Foundation\Database\SQLite;

use Exception;
use kernel\Foundation\File;
use SQLite3;

class SQLite extends SQLite3
{
  protected $SQLiteInstance;
  /**
   * 连接SQLite数据库
   *
   * @param string $DatabaseFilePath SQLite 数据库的路径，或 :memory: 使用内存数据库。如果 filename 是空字符串，那么将创建私有的临时磁盘数据库。一旦数据库连接关闭，这个私有数据库就会自动删除。
   * @param int $flags 可选的 flag，用于确定如何打开 SQLite 数据库。默认使用 SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE 打开。  
   * - SQLITE3_OPEN_READONLY：以只读方式打开数据库。  
   * - SQLITE3_OPEN_READWRITE：以读写方式打开数据库。  
   * - SQLITE3_OPEN_CREATE：如果数据库不存在，则创建数据库。  
   * @param string $encryptionKey 加密和解密 SQLite 数据库时使用的可选加密密钥。如果未安装 SQLite 加密模块，则此参数无效。
   */
  public function __construct($tableFileName, $flags = SQLITE3_OPEN_READWRITE, $encryptionKey = null)
  {
    $tableFileName = File::genPath(F_APP_ROOT, $tableFileName);
    if (!file_exists($tableFileName)) {
      throw new Exception("SQLite数据库文件不存在", 500);
    }
    $this->open($tableFileName, $flags, $encryptionKey);
    if ($this->lastErrorCode()) {
      throw new Exception("SQLite数据库连接失败", 500);
    }
  }
  public function fetchAll($sql, $mode = SQLITE3_ASSOC)
  {
    $q = $this->query($sql);
    if ($q === false) return false;

    $list = [];
    while ($item =  $q->fetchArray($mode)) {
      array_push($list, $item);
    }

    return $list;
  }
  public function fetch($sql, $mode = SQLITE3_ASSOC)
  {
    $q = $this->query($sql);
    if ($q === false) return false;

    if ($q->numColumns() === 0) return null;
    return $q->fetchArray($mode);
  }
  public function fetchOne($sql, $mode = SQLITE3_ASSOC)
  {
    $q = $this->query($sql);
    if ($q === false) return false;

    if ($q->numColumns() === 0) return null;
    $item = $q->fetchArray($mode);
    $q->finalize();

    return $item;
  }
}
