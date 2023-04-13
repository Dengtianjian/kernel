<?php

namespace kernel\Foundation;

use kernel\Foundation\Data\Arr;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class Cache
{
  /**
   * 缓存存储目录
   *
   * @var string
   */
  static private $SaveBasePath = F_APP_DATA . "/Cache";
  /**
   * 已经读取的缓存
   * 键是缓存ID，值是缓存内容
   *
   * @var array
   */
  static private $readedCaches = [];
  /**
   * 已经读取的缓存元数据
   * 键是缓存ID，值是缓存元数据
   *
   * @var array
   */
  static private $readedCacheMetas = [];
  /**
   * 一天的秒数
   *
   * @var int
   */
  static private $DaySeconeds = 60 * 60 * 24;
  /**
   * 指定缓存是否存在
   *
   * @param string $id 缓存ID，也是文件夹名称
   * @return bool
   */
  static function has($id)
  {
    $targetPath = self::$SaveBasePath . "/$id.txt";
    return file_exists($targetPath);
  }
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

    if (!(is_null($cache['meta']['expiredAt']) || $cache['meta']['expiredAt'] <= 0) && $cache['meta']['expiredAt'] < time()) {
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
   * @param mixed $content 缓存内容，只有已有缓存是数组以及传入的数据是数组才会合并
   * @param int $expiresIn 有效期（天），<=0|null 表示不过期
   * @return bool
   */
  static function write($id, $content, $expiresIn = 30)
  {
    if (!is_dir(self::$SaveBasePath)) {
      mkdir(self::$SaveBasePath, 0777, true);
    }
    $targetPath = File::genPath(self::$SaveBasePath, "$id.txt");
    $expired = is_null($expiresIn) || $expiresIn <= 0 ? 0 : round(time() + self::$DaySeconeds * $expiresIn);
    $cache = [
      "content" => [],
      "meta" => [
        "updatedAt" => time(),
        "addedAt" => time(),
        "expiredAt" => $expired,
        "format" => "php_serialize"
      ]
    ];
    if (!in_array($id, self::$readedCaches)) {
      $cacheContent = self::read($id);
      if ($cacheContent) {
        $cache['content'] = $cacheContent;
        $cache['meta'] = self::$readedCacheMetas[$id];
      }
    }

    $cache['meta']['updatedAt'] = time();
    $cache['meta']['expiredAt'] = $expired;

    if (is_array($content) && is_array($cache['content'])) {
      $cache['content'] = array_merge($cache['content'], $content);
    } else {
      $cache['content'] = $content;
    }

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
        "expiredAt" => is_null($expiresIn) || $expiresIn <= 0 ? 0 : time() + self::$DaySeconeds * $expiresIn
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
