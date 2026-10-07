<?php

namespace kernel\Modules\DiscuzX\Foundation\Storage;

use kernel\Foundation\FileSystem\Storage\Drivers\FTPStorage;
use kernel\Modules\DiscuzX\Foundation\DiscuzXPath;

/**
 * DiscuzX 侧的 FTP 磁盘
 *
 * 与基类 {@see FTPStorage} 的唯一差别是**上传中转用的本地临时目录**：这里落在 Discuz 的
 * `data/plugindata/{appId}/storage/ftp_temp`（{@see DiscuzXPath::storage()}），而不是插件目录下。
 *
 * 其余能力（上传预检、失败取证、元信息、删除、URL/取回地址、连接复用）**全部继承基类**——
 * 刻意不再复制一份 `put()`，否则基类每次改进都要在两处同步，很容易漂移。
 *
 * @package kernel\Modules\DiscuzX\Foundation\Storage
 */
class DiscuzXFTPStorage extends FTPStorage
{
  /**
   * FTP 上传中转用的本地临时目录（覆写基类：落在 Discuz 的 data/plugindata 下）
   *
   * @return string
   */
  protected function tempDirectory()
  {
    return DiscuzXPath::join(DiscuzXPath::storage(), "ftp_temp");
  }
}
