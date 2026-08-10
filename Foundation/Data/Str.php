<?php

namespace kernel\Foundation\Data;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

/**
 * 字符串工具类
 *
 * 提供字符串编解码、格式化、截取、大小写转换、随机生成、XML 解析等一系列
 * 静态工具方法。全部方法无需实例化即可调用。
 *
 * 方法分为以下几组：
 * - 编解码：    fromJsEscape、ucs2ToUtf8、fromXml
 * - 模板替换：  replace
 * - 随机生成：  random、randomInt、serialNo、uuid
 * - 判断：      startsWith、endsWith、contains
 * - 截取：      before、after、limit、mask
 * - 长度：      length
 * - 大小写转换：studly、camel、snake
 * - URL/Slug：  slug
 *
 * @package kernel\Foundation\Data
 */
class Str
{
  // ========================
  //  编解码
  // ========================

  /**
   * 可对前端通过escape()编码的字符进行解码
   *
   * 支持以下编码格式：
   * - %uXXXX    — JavaScript escape() 的 Unicode 编码
   * - %XX       — 标准 URL 编码（ASCII 字符）
   * - &#xXXXX;  — HTML 十六进制字符实体
   * - &#NNN;    — HTML 十进制字符实体
   *
   * @param string $str 要解码的字符串
   * @return string 解码后的 UTF-8 字符串
   *
   * @example
   * Str::fromJsEscape("%u4E2D%u6587")   // "中文"
   * Str::fromJsEscape("%20%40")         // " @"
   */
  static function fromJsEscape($str): string
  {
    $str = rawurldecode($str);
    preg_match_all("/%u.{4}|%[0-9A-Fa-f]{2}|&#x.{4};|&#\d+;|.+/U", $str, $r);
    $ar = $r[0];
    foreach ($ar as $k => $v) {
      if (substr($v, 0, 2) == "%u") {
        $decoded = self::ucs2ToUtf8(pack("H4", substr($v, -4)));
        $ar[$k] = $decoded !== false ? $decoded : $v;
      } elseif (substr($v, 0, 1) == "%" && strlen($v) == 3) {
        $ar[$k] = chr(hexdec(substr($v, 1)));
      } elseif (substr($v, 0, 3) == "&#x") {
        $decoded = self::ucs2ToUtf8(pack("H4", substr($v, 3, -1)));
        $ar[$k] = $decoded !== false ? $decoded : $v;
      } elseif (substr($v, 0, 2) == "&#") {
        $decoded = self::ucs2ToUtf8(pack("n", substr($v, 2, -1)));
        $ar[$k] = $decoded !== false ? $decoded : $v;
      }
    }
    return join("", $ar);
  }

  /**
   * UCS-2 编码转换为 UTF-8
   *
   * 优先使用 mb_convert_encoding，不可用时 fallback 到 iconv。
   *
   * @param string $data UCS-2 编码的原始字节
   * @return string|false 转换后的 UTF-8 字符串，失败返回 false
   *
   * @example
   * Str::ucs2ToUtf8(pack("H4", "4E2D"))  // "中"
   */
  static function ucs2ToUtf8($data)
  {
    if (function_exists("mb_convert_encoding")) {
      return \mb_convert_encoding($data, "UTF-8", "UCS-2");
    }
    return \iconv("UCS-2", "UTF-8", $data);
  }

  /**
   * XML 字符串转数组，保留 XML 属性（以 @attr 键存储）
   *
   * 递归解析 XML 节点树，自动处理 CDATA、重复子元素（合并为数组）、
   * 以及混合内容节点（文本存储为 #text 键）。
   *
   * @param string $XMLString XML 字符串
   * @return array|false 解析成功返回数组，失败返回 false
   *
   * @example
   * $xml = '<user id="1"><name>Alice</name></user>';
   * Str::fromXml($xml)  // ['@id' => '1', 'name' => 'Alice']
   */
  static function fromXml($XMLString)
  {
    $options = LIBXML_NOCDATA;
    $toObject = \simplexml_load_string($XMLString, "SimpleXMLElement", $options);
    if ($toObject === false) {
      return false;
    }
    return self::_simpleXmlToArray($toObject);
  }

  /**
   * 递归将 SimpleXMLElement 转为数组，保留属性
   *
   * @param \SimpleXMLElement $xml
   * @return array|string
   */
  private static function _simpleXmlToArray(\SimpleXMLElement $xml)
  {
    $result = [];

    foreach ($xml->attributes() as $name => $value) {
      $result['@' . $name] = (string) $value;
    }

    $hasChildren = false;
    foreach ($xml->children() as $name => $child) {
      $hasChildren = true;
      $childData = self::_simpleXmlToArray($child);
      if (isset($result[$name])) {
        if (!is_array($result[$name]) || !isset($result[$name][0])) {
          $result[$name] = [$result[$name]];
        }
        $result[$name][] = $childData;
      } else {
        $result[$name] = $childData;
      }
    }

    $text = trim((string) $xml);

    if (!$hasChildren) {
      return empty($result) ? $text : ($text !== '' ? $result + ['#text' => $text] : $result);
    }

    if ($text !== '') {
      $result['#text'] = $text;
    }

    return $result;
  }

  // ========================
  //  模板替换
  // ========================

  /**
   * 使用参数数组替换字符串中的 {key} 占位符
   *
   * 支持两种模式：
   * - 关联数组：按 key 匹配替换，{"name" => "Alice"} 替换 {name}
   * - 索引数组：按占位符出现顺序对应替换
   *
   * @param string $string 包含 {key} 占位符的模板字符串
   * @param array  $params 参数数组，关联数组按 key 匹配，索引数组按顺序匹配
   * @return string 替换后的字符串
   *
   * @example
   * // 关联数组模式
   * Str::replace("Hello {name}, you are {age}", ["name" => "Alice", "age" => 18])
   * // "Hello Alice, you are 18"
   *
   * // 索引数组模式（向后兼容）
   * Str::replace("Hello {0}, you are {1}", ["Alice", "18"])
   * // "Hello Alice, you are 18"
   */
  static function replace($string, $params = []): string
  {
    $matchCount = \preg_match_all("/(?<=\{)\w+(?=\})/i", $string, $paramKeys);
    if ($matchCount > 0) {
      $isAssoc = \array_keys($params) !== range(0, count($params) - 1);
      $paramKeys = $paramKeys[0];
      if ($isAssoc) {
        foreach ($paramKeys as $key) {
          if (\array_key_exists($key, $params)) {
            $string = \str_replace("{" . $key . "}", $params[$key], $string);
          }
        }
      } else {
        $search = [];
        foreach ($paramKeys as $key) {
          $search[] = "{" . $key . "}";
        }
        $string = \str_replace($search, \array_values($params), $string);
      }
    }
    return $string;
  }

  // ========================
  //  随机生成
  // ========================

  /**
   * 生成随机字符串
   *
   * @param integer     $stringLength 生成的字符串长度
   * @param string|null $chars        自定义字符集，默认为 [a-zA-Z0-9]
   * @param bool        $secure       是否使用密码学安全随机数（random_int），默认 false
   * @return string 随机字符串
   *
   * @example
   * Str::random(8)                        // "aB3xY9kL"
   * Str::random(6, '0123456789')          // "392817"（纯数字）
   * Str::random(32, null, true)           // API token 场景（密码学安全）
   */
  static function random($stringLength = 5, $chars = null, $secure = false): string
  {
    if ($chars === null) {
      $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    }
    $charsLength = \strlen($chars) - 1;
    $string = "";
    for ($i = 0; $i < $stringLength; $i++) {
      if ($secure) {
        $string .= $chars[\random_int(0, $charsLength)];
      } else {
        $string .= $chars[\mt_rand(0, $charsLength)];
      }
    }
    return $string;
  }

  /**
   * 生成随机整数
   *
   * PHP 7.0+ 优先使用密码学安全的 random_int，否则 fallback 到 mt_rand
   *
   * @param integer $min 可选的、返回的最小值（默认：0）
   * @param integer $max 可选的、返回的最大值（默认：100）
   * @return integer 随机整数
   *
   * @example
   * Str::randomInt(1, 100)    // 例如 42
   * Str::randomInt(1000, 9999) // 例如 3847（4 位随机验证码）
   */
  static function randomInt($min = 0, $max = 100): int
  {
    if (function_exists("random_int")) {
      return \random_int($min, $max);
    }
    return \mt_rand($min, $max);
  }

  /**
   * 生成序列单号
   *
   * 格式：{前缀}{日期时间}{随机数字填充}{后缀}
   * 长度不足时用随机数字补全，超出时从尾部截断。
   *
   * @param integer                 $ExpectLength 期望的总长度（含前后缀）
   * @param string|int|float|null   $Prefix       前缀，null 表示无前缀
   * @param string|int|float|null   $Suffix       后缀，null 表示无后缀
   * @param string                  $dateFormat   日期格式，默认 YmdHis
   * @return string 序列单号
   *
   * @example
   * Str::serialNo(32)                          // "20240810143022583917402659184732"
   * Str::serialNo(24, 'ORD')                   // "ORD20240810143022102849"
   * Str::serialNo(20, null, null, 'Ymd')       // "20240810192847593106"
   */
  static function serialNo($ExpectLength = 32, $Prefix = null, $Suffix = null, $dateFormat = 'YmdHis'): string
  {
    $prefix = $Prefix !== null ? strval($Prefix) : '';
    $suffix = $Suffix !== null ? strval($Suffix) : '';
    $length = $ExpectLength - strlen($prefix) - strlen($suffix);

    $no = date($dateFormat);
    $remaining = $length - strlen($no);

    if ($remaining > 0) {
      $no .= self::random($remaining, '0123456789');
    } elseif ($remaining < 0) {
      $no = substr($no, -$length);
    }

    return $prefix . $no . $suffix;
  }

  /**
   * 生成 UUID v4
   *
   * 符合 RFC 4122 标准的版本 4 UUID（随机生成）。
   *
   * @return string 格式为 xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx 的 UUID
   *
   * @example
   * Str::uuid()  // "550e8400-e29b-41d4-a716-446655440000"
   */
  static function uuid(): string
  {
    $data = \random_bytes(16);

    // 设置版本号为 4（第 7 字节的高 4 位）
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // 设置变体为 RFC 4122（第 9 字节的高 2 位）
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return \vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(\bin2hex($data), 4));
  }

  // ========================
  //  判断
  // ========================

  /**
   * 检查字符串是否以指定子串开头
   *
   * @param string $haystack 被检查的字符串
   * @param string $needle   要查找的子串
   * @return bool
   *
   * @example
   * Str::startsWith("Hello World", "Hello")  // true
   * Str::startsWith("/api/user", "/api")     // true
   * Str::startsWith("Hello", "abc")          // false
   */
  static function startsWith($haystack, $needle): bool
  {
    return $needle === '' || \strpos($haystack, $needle) === 0;
  }

  /**
   * 检查字符串是否以指定子串结尾
   *
   * @param string $haystack 被检查的字符串
   * @param string $needle   要查找的子串
   * @return bool
   *
   * @example
   * Str::endsWith("Hello World", "World")  // true
   * Str::endsWith("file.txt", ".txt")      // true
   * Str::endsWith("Hello", "abc")          // false
   */
  static function endsWith($haystack, $needle): bool
  {
    if ($needle === '') {
      return true;
    }
    $needleLen = \strlen($needle);
    return \strlen($haystack) >= $needleLen
      && \substr($haystack, -$needleLen) === $needle;
  }

  /**
   * 检查字符串是否包含指定子串
   *
   * 为 PHP 7 项目提供 str_contains 兼容实现
   *
   * @param string $haystack 被检查的字符串
   * @param string $needle   要查找的子串
   * @return bool
   *
   * @example
   * Str::contains("Hello World", "lo")       // true
   * Str::contains("application/json", "json") // true
   * Str::contains("Hello", "xyz")            // false
   */
  static function contains($haystack, $needle): bool
  {
    return $needle === '' || \strpos($haystack, $needle) !== false;
  }

  // ========================
  //  截取
  // ========================

  /**
   * 返回字符串中指定搜索值之前的部分
   *
   * 若搜索值不存在，返回原字符串。
   *
   * @param string $subject 源字符串
   * @param string $search  搜索值
   * @return string
   *
   * @example
   * Str::before("video/mp4", "/")     // "video"
   * Str::before("hello@example.com", "@")  // "hello"
   * Str::before("hello", "@")          // "hello"（不存在返回原值）
   */
  static function before($subject, $search): string
  {
    if ($search === '') {
      return $subject;
    }
    $pos = \strpos($subject, $search);
    return $pos === false ? $subject : \substr($subject, 0, $pos);
  }

  /**
   * 返回字符串中指定搜索值之后的部分
   *
   * 若搜索值不存在，返回原字符串。
   *
   * @param string $subject 源字符串
   * @param string $search  搜索值
   * @return string
   *
   * @example
   * Str::after("video/mp4", "/")         // "mp4"
   * Str::after("hello@example.com", "@")  // "example.com"
   * Str::after("hello", "@")             // "hello"（不存在返回原值）
   */
  static function after($subject, $search): string
  {
    if ($search === '') {
      return $subject;
    }
    $pos = \strpos($subject, $search);
    return $pos === false ? $subject : \substr($subject, $pos + \strlen($search));
  }

  /**
   * 截取字符串到指定长度，超出部分以指定字符结尾
   *
   * 多字节安全：优先使用 mb_substr，fallback 到 substr。
   *
   * @param string $value 要截取的字符串
   * @param int    $limit 最大长度
   * @param string $end   超出时追加的结尾字符
   * @return string
   *
   * @example
   * Str::limit("Hello World", 5)              // "Hello..."
   * Str::limit("Hello World", 5, '›')         // "Hello›"
   * Str::limit("这是一段很长的中文文本", 5)     // "这是一段很..."
   */
  static function limit($value, $limit = 100, $end = '...'): string
  {
    if (function_exists("mb_strlen")) {
      if (\mb_strlen($value, 'UTF-8') <= $limit) {
        return $value;
      }
      return \mb_substr($value, 0, $limit, 'UTF-8') . $end;
    }

    if (\strlen($value) <= $limit) {
      return $value;
    }
    return \substr($value, 0, $limit) . $end;
  }

  /**
   * 用指定字符掩盖字符串中间部分
   *
   * 适用于手机号、邮箱、身份证号等敏感数据的脱敏展示。
   *
   * @param string $value     原始字符串
   * @param string $character 掩盖字符，默认为 *
   * @param int    $start     从第几位开始掩盖（0-based）
   * @param int    $length    掩盖长度，null 表示从 start 掩盖到末尾
   * @return string
   *
   * @example
   * Str::mask("13812345678")           // "138****5678"（默认前3后4）
   * Str::mask("13812345678", '*', 3, 4) // "138****5678"
   * Str::mask("abcdef", '#', 2)        // "ab####"
   */
  static function mask($value, $character = '*', $start = 3, $length = 4): string
  {
    $strLen = function_exists("mb_strlen")
      ? \mb_strlen($value, 'UTF-8')
      : \strlen($value);

    if ($start >= $strLen) {
      return $value;
    }

    $maskLen = $length !== null ? $length : ($strLen - $start);

    if ($start + $maskLen > $strLen) {
      $maskLen = $strLen - $start;
    }

    if (function_exists("mb_substr")) {
      $prefix  = \mb_substr($value, 0, $start, 'UTF-8');
      $suffix  = \mb_substr($value, $start + $maskLen, null, 'UTF-8');
    } else {
      $prefix  = \substr($value, 0, $start);
      $suffix  = \substr($value, $start + $maskLen);
    }

    return $prefix . \str_repeat($character, $maskLen) . $suffix;
  }

  // ========================
  //  长度
  // ========================

  /**
   * 获取字符串长度（多字节安全）
   *
   * 优先使用 mb_strlen 以正确处理中文等多字节字符，
   * mbstring 不可用时 fallback 到 strlen。
   *
   * @param string $value 要计算长度的字符串
   * @return int 字符数（非字节数）
   *
   * @example
   * Str::length("Hello")  // 5
   * Str::length("中文")    // 2（而非 6）
   */
  static function length($value): int
  {
    if (function_exists("mb_strlen")) {
      return \mb_strlen($value, 'UTF-8');
    }
    return \strlen($value);
  }

  // ========================
  //  大小写转换
  // ========================

  /**
   * 将字符串转换为 StudlyCase（大驼峰，首字母大写）
   *
   * "foo_bar_baz" → "FooBarBaz"
   * "foo-bar-baz" → "FooBarBaz"
   *
   * @param string $value 要转换的字符串
   * @return string
   *
   * @example
   * Str::studly("foo_bar_baz")  // "FooBarBaz"
   * Str::studly("user-profile") // "UserProfile"
   */
  static function studly($value): string
  {
    $value = \ucwords(\str_replace(['-', '_'], ' ', $value));
    return \str_replace(' ', '', $value);
  }

  /**
   * 将字符串转换为 camelCase（小驼峰，首字母小写）
   *
   * "foo_bar_baz" → "fooBarBaz"
   * "foo-bar-baz" → "fooBarBaz"
   * "FooBar"      → "fooBar"
   *
   * @param string $value 要转换的字符串
   * @return string
   *
   * @example
   * Str::camel("foo_bar_baz")  // "fooBarBaz"
   * Str::camel("FooBar")       // "fooBar"
   */
  static function camel($value): string
  {
    return \lcfirst(self::studly($value));
  }

  /**
   * 将字符串转换为 snake_case（下划线分隔，全小写）
   *
   * "FooBarBaz"  → "foo_bar_baz"
   * "fooBarBaz"  → "foo_bar_baz"
   * "foo-barBaz" → "foo_bar_baz"
   *
   * @param string $value     要转换的字符串
   * @param string $delimiter 分隔符，默认为下划线
   * @return string
   *
   * @example
   * Str::snake("FooBarBaz")         // "foo_bar_baz"
   * Str::snake("fooBar", '-')       // "foo-bar"
   * Str::snake("DB_HOST")           // "d_b_h_o_s_t"
   */
  static function snake($value, $delimiter = '_'): string
  {
    if (!\ctype_lower($value)) {
      $value = \preg_replace('/\s+/u', '', \ucwords($value));
      $value = \lcfirst($value);
      $value = \preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value);
      $value = \strtolower($value);
    }

    // 将已有的中划线替换为分隔符
    $value = \str_replace('-', $delimiter, $value);

    return $value;
  }

  // ========================
  //  URL/Slug
  // ========================

  /**
   * 生成 URL 友好的 slug
   *
   * 将字符串转换为仅包含小写字母、数字和指定分隔符的格式。
   * 适用于文章标题转 URL 路径、分类别名等场景。
   *
   * @param string $value     要转换的字符串
   * @param string $separator 分隔符，默认为中划线
   * @return string
   *
   * @example
   * Str::slug("Hello World")              // "hello-world"
   * Str::slug("PHP String Utility Class") // "php-string-utility-class"
   * Str::slug("标题 - Title", '_')         // "biao_ti_title"（中文转拼音需额外扩展）
   */
  static function slug($value, $separator = '-'): string
  {
    // 尝试将中文等非 ASCII 字符转写为 ASCII
    if (function_exists("transliterator_transliterate")) {
      $value = \transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
    } elseif (function_exists("iconv")) {
      $value = \iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    }

    // 将非字母数字的字符替换为分隔符
    $value = \preg_replace('/[^a-zA-Z0-9]+/', $separator, $value);
    // 去除首尾分隔符
    $value = \trim($value, $separator);
    // 转为小写
    $value = \strtolower($value);

    return $value;
  }
}
