<?php

namespace gstudio_kernel\Foundation;

use gstudio_kernel\Foundation\Data\Arr;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

class Cache
{
  static private $SaveBasePath = F_APP_DATA . "/cache"; //* 缓存存储的基路径
  static private $readedCaches = []; //* 已经读取的缓存
  static private $readedCacheMetas = []; //* 已经读取的缓存元数据
  static private $DaySeconeds = 60 * 60 * 24; //* 一天有多少秒
  /**
   * 读取缓存
   *
   * @param string $id 缓存ID，也是文件夹名称
   * @return array|bool|null
   */
  static function read($id)
  {
    $targetPath = self::$SaveBasePath . "/$id.txt";
    if (!file_exists($targetPath)) return false;
    $cache = file_get_contents($targetPath);
    $cache = unserialize($cache);

    if ($cache['meta']['expiredAt'] < time()) {
      return null;
    }

    self::$readedCaches[$id] = $cache['content'];
    self::$readedCacheMetas[$id] = $cache['meta'];

    return $cache['content'];
  }
  /**
   * 读取缓存的 元数据
   *
   * @param string $id 缓存ID
   * @return array|boolean|null
   */
  static function meta($id)
  {
    $result = self::read($id);
    if ($result === false) {
      return false;
    }
    return self::$readedCacheMetas[$id];
  }
  /**
   * 写入缓存，该方法只会合并已有的缓存
   *
   * @param string $id 缓存ID
   * @param array $content 缓存内容
   * @param int $expiresIn 有效期（天）
   * @return bool
   */
  static function write($id,  $content,  $expiresIn = 30)
  {
    if (!is_dir(self::$SaveBasePath)) {
      mkdir(self::$SaveBasePath, 0777, true);
    }
    $targetPath = File::genPath(self::$SaveBasePath, "$id.txt");
    $expired = round(time() + self::$DaySeconeds * $expiresIn);
    if (!in_array($id, self::$readedCaches)) {
      $cache = self::read($id);
    } else {
      $cache = [
        "content" => [],
        "meta" => [
          "updatedAt" => time(),
          "addedAt" => time(),
          "expiredAt" => $expired
        ]
      ];
    }

    $cache['meta']['updatedAt'] = time();
    $cache['meta']['expiredAt'] = $expired;

    $cache['content'] = Arr::merge($cache['content'], $content);

    $result = file_put_contents($targetPath, serialize($cache));
    chmod($targetPath, 0700);

    return boolval($result);
  }
  /**
   * 覆盖已有缓存
   *
   * @param string $id 缓存ID
   * @param array $content 覆盖的内容
   * @return bool
   */
  static function overwrite($id, $content, $expiresIn = 30)
  {
    if (!is_dir(self::$SaveBasePath)) {
      mkdir(self::$SaveBasePath, 757, true);
    }
    $targetPath = self::$SaveBasePath . "/$id.txt";

    $result = file_put_contents($targetPath, serialize([
      "content" => $content,
      "meta" => [
        "updatedAt" => time(),
        "addedAt" => time(),
        "expiredAt" => time() + self::$DaySeconeds * $expiresIn
      ]
    ]));

    self::$readedCaches[$id] = $content;

    return boolval($result);
  }
  /**
   * 清除已有缓存
   *
   * @param string $id 缓存ID
   * @return bool
   */
  static function clear($id)
  {
    $targetPath = self::$SaveBasePath . "/$id.txt";
    if (!file_exists($targetPath)) {
      return self::overwrite($id, []);
    }

    $result = file_put_contents($targetPath, [
      "content" => [],
      "meta" => [
        "updatedAt" => time(),
        "addedAt" => time(),
        "expiredAt" => time() + self::$DaySeconeds * 30
      ]
    ]);

    return boolval($result);
  }
  /**
   * 删除缓存
   *
   * @param string $id 缓存ID
   * @return bool
   */
  static function remove($id)
  {
    $targetPath = self::$SaveBasePath . "/$id.txt";
    if (!file_exists($targetPath)) return true;

    unlink($targetPath);

    return true;
  }
}
