<?php

namespace kernel\Foundation;

/**
 * 基于文件的缓存
 *
 * 以 PHP 序列化格式将缓存内容写入应用 Data/Cache/ 目录，
 * 支持过期时间与合并写入，进程内维护已读取缓存避免重复读文件。
 *
 * 特性：
 * - 过期时间以"天"为单位，支持小数（如 1/24 表示 1 小时），<=0 或 null 表示永不过期
 * - write() 对数组内容做合并（一层，新键覆盖旧键），overwrite() 完全替换，clear() 清空内容
 * - has()/read() 均自动跳过已过期缓存，过期文件在读取时顺手清理
 * - remember() 一键"读-生成-写"，get() 支持默认值
 * - increment()/decrement() 基于文件锁的原子计数器
 * - flush() 清空全部缓存，gc() 清理过期缓存（运维方法）
 *
 * 安全：
 * - 缓存 ID 中的路径分隔符与空字节会被替换，防止目录穿越
 * - 文件写入使用 LOCK_EX，避免并发写坏缓存
 *
 * 注意：
 * - read() 返回 false 表示缓存不存在，null 表示已过期，其他值为缓存内容
 * - 内容为 0 / "" / false 等 falsy 值时同样会被正确缓存与返回
 */
class Cache
{
  /**
   * 缓存存储目录
   *
   * @var string
   */
  static private $saveBasePath = F_APP_DATA . "/Cache";
  /**
   * 已经读取的缓存内容
   * 键是缓存 ID，值是缓存内容
   *
   * @var array
   */
  static private $readCaches = [];
  /**
   * 已经读取的缓存元数据
   * 键是缓存 ID，值是缓存元数据
   *
   * @var array
   */
  static private $readCacheMetas = [];
  /**
   * 一天的秒数
   *
   * @var int
   */
  static private $daySeconds = 60 * 60 * 24;

  /**
   * 指定缓存是否存在（可用性语义：存在且未过期）
   *
   * @param string $id 缓存 ID
   * @return bool
   */
  static function has($id)
  {
    $result = self::read($id);
    return $result !== false && $result !== null;
  }

  /**
   * 读取缓存
   *
   * @param string $id 缓存 ID
   * @return mixed|bool|null
   *   - 缓存内容：读取成功且未过期
   *   - null：缓存已过期（会顺手删除过期文件）
   *   - false：缓存不存在或文件损坏
   */
  static function read($id)
  {
    //* 进程内已读取过：需校验内存缓存是否过期
    if (array_key_exists($id, self::$readCaches)) {
      $expiredAt = self::$readCacheMetas[$id]["expiredAt"] ?? 0;
      if ($expiredAt === null || $expiredAt <= 0 || $expiredAt >= time()) {
        return self::$readCaches[$id];
      }
      //* 内存缓存已过期：清内存并删除文件
      unset(self::$readCaches[$id]);
      unset(self::$readCacheMetas[$id]);
      @unlink(self::getFilePath($id));
      return null;
    }

    $targetPath = self::getFilePath($id);
    if (!file_exists($targetPath)) {
      return false;
    }

    $cache = @unserialize(file_get_contents($targetPath));
    if (!is_array($cache) || !array_key_exists("content", $cache) || !is_array($cache["meta"] ?? null)) {
      return false;
    }

    $expiredAt = $cache["meta"]["expiredAt"] ?? 0;
    if ($expiredAt !== null && $expiredAt > 0 && $expiredAt < time()) {
      //* 缓存已过期：返回 null 并清理过期文件
      @unlink($targetPath);
      return null;
    }

    self::$readCaches[$id] = $cache["content"];
    self::$readCacheMetas[$id] = $cache["meta"];

    return $cache["content"];
  }

  /**
   * 读取缓存的元数据（不判断过期，不清理文件）
   *
   * @param string $id 缓存 ID
   * @return array|bool 元数据；缓存不存在或损坏时返回 false
   */
  static function meta($id)
  {
    //* 进程内已读取过元数据，直接返回
    if (array_key_exists($id, self::$readCacheMetas)) {
      return self::$readCacheMetas[$id];
    }

    $targetPath = self::getFilePath($id);
    if (!file_exists($targetPath)) {
      return false;
    }

    $cache = @unserialize(file_get_contents($targetPath));
    if (!is_array($cache) || !isset($cache["meta"]) || !is_array($cache["meta"])) {
      return false;
    }

    return $cache["meta"];
  }

  /**
   * 写入缓存（合并模式）
   *
   * 缓存已存在且新旧内容均为数组时做数组合并（新键覆盖旧键），
   * 否则新内容完全替换旧内容。过期缓存视为不存在，直接全新写入。
   *
   * @param string $id 缓存 ID
   * @param mixed $content 缓存内容
   * @param int|float|null $expiresIn 有效期（天），<=0 或 null 表示永不过期
   * @return bool
   */
  static function write($id, $content, $expiresIn = 30)
  {
    $targetPath = self::getFilePath($id);
    self::ensureDir();

    $expired = is_null($expiresIn) || $expiresIn <= 0 ? 0 : time() + (int)round(self::$daySeconds * $expiresIn);

    //* 合并已有缓存：内存优先，否则读文件（过期/损坏视为无）
    $existing = null;
    if (array_key_exists($id, self::$readCaches)) {
      $existing = self::$readCaches[$id];
    } else {
      $readResult = self::read($id);
      if ($readResult !== false && $readResult !== null) {
        $existing = $readResult;
      }
    }

    if (is_array($content) && is_array($existing)) {
      $cacheContent = array_merge($existing, $content);
    } else {
      $cacheContent = $content;
    }

    $now = time();
    $meta = [
      "updatedAt" => $now,
      "addedAt" => isset(self::$readCacheMetas[$id]) ? self::$readCacheMetas[$id]["addedAt"] : $now,
      "expiredAt" => $expired,
      "format" => "php_serialize"
    ];

    $result = file_put_contents($targetPath, serialize([
      "content" => $cacheContent,
      "meta" => $meta
    ]), LOCK_EX);
    if ($result !== false) {
      chmod($targetPath, 0700);
    }

    self::$readCaches[$id] = $cacheContent;
    self::$readCacheMetas[$id] = $meta;

    return $result !== false;
  }

  /**
   * 覆盖写入缓存（不合并，完全替换）
   *
   * @param string $id 缓存 ID
   * @param mixed $content 覆盖的内容
   * @param int|float|null $expiresIn 有效期（天），<=0 或 null 表示永不过期
   * @return bool
   */
  static function overwrite($id, $content, $expiresIn = 30)
  {
    $targetPath = self::getFilePath($id);
    self::ensureDir();

    $now = time();
    $meta = [
      "updatedAt" => $now,
      "addedAt" => $now,
      "expiredAt" => is_null($expiresIn) || $expiresIn <= 0 ? 0 : $now + (int)round(self::$daySeconds * $expiresIn),
      "format" => "php_serialize"
    ];

    $result = file_put_contents($targetPath, serialize([
      "content" => $content,
      "meta" => $meta
    ]), LOCK_EX);
    if ($result !== false) {
      chmod($targetPath, 0700);
    }

    self::$readCaches[$id] = $content;
    self::$readCacheMetas[$id] = $meta;

    return $result !== false;
  }

  /**
   * 清除缓存内容（将内容设为空数组，保留文件与 30 天有效期）
   *
   * @param string $id 缓存 ID
   * @return bool
   */
  static function clear($id)
  {
    return self::overwrite($id, []);
  }

  /**
   * 删除缓存文件并清理进程内缓存
   *
   * @param string $id 缓存 ID
   * @return bool
   */
  static function remove($id)
  {
    $targetPath = self::getFilePath($id);
    if (!file_exists($targetPath)) {
      return true;
    }

    $result = unlink($targetPath);
    unset(self::$readCaches[$id]);
    unset(self::$readCacheMetas[$id]);

    return $result;
  }

  /**
   * 缓存-回调模式：缓存命中直接返回，未命中用回调生成并写入
   *
   * 等价于"read → 生成 → overwrite"的完整流程，适合缓存计算结果、
   * 数据库查询等场景，避免手动判断。
   *
   * @param string $id 缓存 ID
   * @param callable $callback 生成缓存的回调（缓存未命中时调用）
   * @param int|float|null $expiresIn 有效期（天），<=0 或 null 表示永不过期
   * @return mixed 缓存内容
   */
  static function remember($id, $callback, $expiresIn = 30)
  {
    $cached = self::read($id);
    if ($cached !== false && $cached !== null) {
      return $cached;
    }

    $value = $callback();
    self::overwrite($id, $value, $expiresIn);
    return $value;
  }

  /**
   * 读取缓存，未命中（不存在或已过期）时返回默认值
   *
   * @param string $id 缓存 ID
   * @param mixed $default 未命中时的返回值，默认 null
   * @return mixed
   */
  static function get($id, $default = null)
  {
    $cached = self::read($id);
    return ($cached === false || $cached === null) ? $default : $cached;
  }

  /**
   * 原子自增计数器
   *
   * 通过文件锁保证并发安全，计数器内容为数字。缓存不存在时从 0 开始。
   *
   * @param string $id 缓存 ID
   * @param int|float $step 增量，默认 1
   * @param int|float|null $expiresIn 有效期（天），<=0 或 null 表示永不过期
   * @return int|float|false 自增后的新值（打开文件失败时返回 false）
   */
  static function increment($id, $step = 1, $expiresIn = 30)
  {
    $targetPath = self::getFilePath($id);
    self::ensureDir();

    $fp = fopen($targetPath, "c+");
    if ($fp === false) {
      return false;
    }
    flock($fp, LOCK_EX);

    $cache = @unserialize(stream_get_contents($fp));
    $current = (is_array($cache) && array_key_exists("content", $cache) && is_numeric($cache["content"])) ? $cache["content"] : 0;
    $value = $current + $step;

    $now = time();
    $oldMeta = (is_array($cache) && isset($cache["meta"]) && is_array($cache["meta"])) ? $cache["meta"] : [];
    $meta = [
      "updatedAt" => $now,
      "addedAt" => isset($oldMeta["addedAt"]) ? $oldMeta["addedAt"] : $now,
      "expiredAt" => is_null($expiresIn) || $expiresIn <= 0 ? 0 : $now + (int)round(self::$daySeconds * $expiresIn),
      "format" => "php_serialize"
    ];

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, serialize([
      "content" => $value,
      "meta" => $meta
    ]));
    fflush($fp);

    flock($fp, LOCK_UN);
    fclose($fp);
    chmod($targetPath, 0700);

    self::$readCaches[$id] = $value;
    self::$readCacheMetas[$id] = $meta;

    return $value;
  }

  /**
   * 原子自减计数器（基于 increment 实现）
   *
   * @param string $id 缓存 ID
   * @param int|float $step 减量，默认 1
   * @param int|float|null $expiresIn 有效期（天），<=0 或 null 表示永不过期
   * @return int|float|false 自减后的新值（打开文件失败时返回 false）
   */
  static function decrement($id, $step = 1, $expiresIn = 30)
  {
    return self::increment($id, -$step, $expiresIn);
  }

  /**
   * 清空缓存目录下的全部缓存文件
   *
   * @return int 清理的文件数量
   */
  static function flush()
  {
    $removed = 0;
    foreach (glob(self::$saveBasePath . "/*.txt") ?: [] as $file) {
      if (@unlink($file)) {
        $removed++;
      }
    }
    self::$readCaches = [];
    self::$readCacheMetas = [];
    return $removed;
  }

  /**
   * 清理过期或损坏的缓存文件
   *
   * @return int 清理的文件数量
   */
  static function gc()
  {
    $removed = 0;
    foreach (glob(self::$saveBasePath . "/*.txt") ?: [] as $file) {
      $cache = @unserialize(@file_get_contents($file));
      if (!is_array($cache) || !isset($cache["meta"]) || !is_array($cache["meta"])) {
        @unlink($file);
        $removed++;
        continue;
      }
      $expiredAt = $cache["meta"]["expiredAt"] ?? 0;
      if ($expiredAt !== null && $expiredAt > 0 && $expiredAt < time()) {
        @unlink($file);
        $removed++;
      }
    }
    return $removed;
  }

  /**
   * 确保缓存目录存在
   *
   * @return void
   */
  private static function ensureDir()
  {
    if (!is_dir(self::$saveBasePath)) {
      mkdir(self::$saveBasePath, 0777, true);
    }
  }

  /**
   * 缓存文件路径（对 ID 做安全过滤，防止目录穿越）
   *
   * @param string $id 缓存 ID
   * @return string 文件路径
   */
  private static function getFilePath($id)
  {
    return self::$saveBasePath . "/" . self::sanitizeId($id) . ".txt";
  }

  /**
   * 过滤缓存 ID 中的路径分隔符与空字节，防止目录穿越
   *
   * @param string $id 缓存 ID
   * @return string 安全的 ID
   */
  private static function sanitizeId($id)
  {
    return str_replace(["\\", "/", "\0"], "_", (string)$id);
  }
}
