<?php

namespace kernel\Foundation\Security;

use kernel\Foundation\Exception\Error;
use kernel\Foundation\FileSystem\Path;

/**
 * 安全净化器
 *
 * 把不可信数据（用户输入、外部接口数据）净化为可安全使用的形式。
 * 提供按「使用场景」区分的静态方法：输出到 HTML / JS / URL、作为数字、
 * SQL 标识符、文件路径、通用字母数字等。
 *
 * 设计原则：
 * - **按使用点净化**：同一个值输出到 HTML 与拼进 URL，净化方式不同，不要期待「一次过滤到处安全」；
 * - **不提供 SQL 值过滤**：SQL 注入的正确防护是参数绑定（见 {@see \kernel\Foundation\Database\RDB\Query}），
 *   对值做转义/过滤是反模式（无法穷举手法、破坏合法数据如 `O'Brien`）；SQL 相关只提供标识符白名单 {@see identifier()}；
 * - **数组递归**：{@see html()} / {@see filter()} 对数组递归净化，适配批量输出；{@see js()} 对整体编码。
 *
 * @package kernel\Foundation\Security
 */
class Sanitizer
{
  /**
   * HTML 文本/属性转义（防 XSS）
   *
   * 数组会递归逐项转义；null 转为空串。输出到 HTML 页面或属性时使用。
   *
   * 用法示例：
   * ```php
   * // 单个值：尖括号、引号全部实体化
   * echo '<p>' . Sanitizer::html('<script>alert(1)</script>') . '</p>';
   * // 输出：<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>
   *
   * // 属性值同样适用（双引号被转成 &quot;，不会截断属性）
   * echo '<a title="' . Sanitizer::html($title) . '">';
   *
   * // 数组递归，null 变空串（便于直接拼模板）
   * Sanitizer::html(['title' => '<b>Hi</b>', 'note' => null]);
   * // 返回 ['title' => '&lt;b&gt;Hi&lt;/b&gt;', 'note' => '']
   * ```
   *
   * @param mixed $value 待转义的值（支持数组递归）
   * @return mixed 转义后的值
   */
  public static function html($value)
  {
    if (is_array($value)) {
      return array_map([self::class, "html"], $value);
    }
    if ($value === null) {
      return "";
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
  }

  /**
   * JS 上下文编码（防 XSS）
   *
   * 使用 JSON 编码并转义 `< > & ' "`（HEX 标记），结果可安全内嵌进 `<script>` 或事件属性。
   * 数组整体编码为 JSON，不逐项递归。
   *
   * 用法示例：
   * ```php
   * // 内嵌数据到页面，值里带 </script> 也无法闭合脚本标签
   * $data = ['name' => '张三', 'bio' => '</script><img src=x onerror=alert(1)>'];
   * echo '<script>var data = ' . Sanitizer::js($data) . ';</script>';
   * // 输出：var data = {"name":"\u5f20\u4e09","bio":"\u003C\/script\u003E\u003Cimg ..."};
   *
   * // 也可以直接用于事件属性
   * echo '<button onclick="doIt(' . Sanitizer::js($id) . ')">';
   * ```
   *
   * @param mixed $value 待编码的值
   * @return string JSON 字串
   */
  public static function js($value)
  {
    $result = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    return $result === false ? "null" : $result;
  }

  /**
   * URL 参数编码
   *
   * 用法示例：
   * ```php
   * $link = '/search?q=' . Sanitizer::url('a b&c');
   * // 输出：/search?q=a%20b%26c
   *
   * // 拼多个参数，避免手写 urlencode 时漏转义 & 造成参数拆分
   * $url = '/list?' . http_build_query(['page' => Sanitizer::int($page), 'q' => $keyword]);
   * ```
   *
   * @param mixed $value 待编码的值
   * @return string 编码后的字符串
   */
  public static function url($value)
  {
    return rawurlencode((string)$value);
  }

  /**
   * 强制整数
   *
   * 用法示例：
   * ```php
   * $page = max(1, Sanitizer::int($request->query->get('page', 1)));  // "12abc" → 12
   * $id = Sanitizer::int($request->query->get('id'));                 // "abc" → 0，可据此判空
   * if ($id <= 0) {
   *   // 参数非法
   * }
   * ```
   *
   * @param mixed $value 待转换的值
   * @return int
   */
  public static function int($value)
  {
    return (int)$value;
  }

  /**
   * 强制字符串
   *
   * 用法示例：
   * ```php
   * $name = Sanitizer::str($input);   // null → ""，避免 PHP 8.1+ 的 null 转串废弃提示
   * $tags = Sanitizer::str($request->input('tags'));  // 数组会得到 "Array" 并告警，数组请先判类型
   * ```
   *
   * @param mixed $value 待转换的值
   * @return string
   */
  public static function str($value)
  {
    return (string)$value;
  }

  /**
   * SQL 标识符白名单校验（字段名 / 表名）
   *
   * 只允许字母、数字、下划线、点、`$`；非法直接抛 {@see Error}（400）。
   * 用于 `ORDER BY`、字段名等无法参数化的结构位；**查询值请走参数绑定，不要用本方法**。
   *
   * 用法示例：
   * ```php
   * // 通过：合法的字段名 / 带表前缀的列名
   * $query->orderBy(Sanitizer::identifier($request->query->get('sort', 'created_at')));
   * Sanitizer::identifier('post.created_at');   // 返回 "post.created_at"
   *
   * // 抛 Error(400, "invalidIdentifier:400")
   * Sanitizer::identifier('created_at; DROP TABLE user');
   * Sanitizer::identifier('');
   *
   * // 值不要这样写，请用参数绑定
   * // $query->whereRaw("name = '" . Sanitizer::identifier($name) . "'");  // 错误示范
   * ```
   *
   * @param mixed $value 待校验的标识符
   * @return string 合法的标识符
   * @throws Error 标识符不合法时抛出
   */
  public static function identifier($value)
  {
    $value = trim((string)$value);
    if ($value === "" || !preg_match('/^[A-Za-z0-9_$.]+$/', $value)) {
      throw new Error("非法的 SQL 标识符", 400, "invalidIdentifier:400", $value);
    }
    return $value;
  }

  /**
   * 文件路径安全校验（防目录穿越）
   *
   * 拒绝空字节与 `..`；默认拒绝绝对路径，传 `$allowAbsolute = true` 时仅防穿越。
   * 非法直接抛 {@see Error}（400）。
   *
   * 用法示例：
   * ```php
   * // 通过：相对路径拼到固定根目录下
   * $file = Sanitizer::safePath($request->query->get('path'));
   * $full = DISCUZ_ROOT . '/data/attachment/' . $file;
   *
   * // 抛 Error(400, "invalidPath:400")
   * Sanitizer::safePath('../../etc/passwd');       // 含 ..
   * Sanitizer::safePath("/etc/passwd");            // 绝对路径（默认不允许）
   * Sanitizer::safePath("a.png\0.jpg");            // 空字节截断
   *
   * // 明确需要绝对路径时放行，但仍拒绝穿越
   * Sanitizer::safePath('/var/www/uploads/a.png', true);   // 通过
   * ```
   *
   * @param mixed $value 待校验的路径
   * @param bool $allowAbsolute 是否允许绝对路径（默认不允许）
   * @return string 合法的路径
   * @throws Error 路径不安全时抛出
   */
  public static function safePath($value, $allowAbsolute = false)
  {
    $value = (string)$value;
    if (strpos($value, "\0") !== false) {
      throw new Error("路径包含空字节", 400, "invalidPath:400", $value);
    }
    if (strpos($value, "..") !== false) {
      throw new Error("路径包含目录穿越", 400, "invalidPath:400", $value);
    }
    if (!$allowAbsolute && preg_match('#^(/|[A-Za-z]:[\\\\/])#', $value)) {
      throw new Error("不允许绝对路径", 400, "invalidPath:400", $value);
    }
    return $value;
  }

  /**
   * 文件路径范围校验（防越界）
   *
   * 在 {@see safePath()} 的形态校验之上，再判定「最终位置是否落在 $root 之内」：
   * 先经 {@see safePath()} 挡掉空字节、`..` 与（默认）绝对路径，再用
   * {@see Path::canonicalize()} 把目标与根都规范化后做前缀比对。相对路径以 $root 为基准
   * 求绝对；空路径视为根目录本身。越界直接抛 {@see Error}（400）。
   *
   * 与 `realpath()` 的取舍：canonicalize 是纯字符串计算，目标尚不存在（如待 mkdir 的目录）
   * 也能判定；代价是挡不住符号链接穿越——若根目录下可能被放入指向外部的软链接，
   * 需在部署层禁止，或在落盘前后补 `realpath()` 事实校验。
   *
   * 注意：本方法需要根目录参数，因此不纳入 {@see filter()} 的类型表；`filter($v, "path")`
   * 仍是不带根范围的 {@see safePath()}。
   *
   * 用法示例：
   * ```php
   * // 相对路径：拼到根下后仍在根内 → 通过
   * Sanitizer::pathWithin('community/post/images', '/data/storage');
   *
   * // 绝对路径：显式放行后仍必须在根内 → 通过
   * Sanitizer::pathWithin('/data/storage/avatar/a.png', '/data/storage', true);
   *
   * // 绝对路径不在根内 → 抛 Error(400, "outOfPath:400")
   * Sanitizer::pathWithin('/etc/passwd', '/data/storage', true);
   *
   * // 含 .. → 由 safePath 先拦下，抛 Error(400, "invalidPath:400")
   * Sanitizer::pathWithin('../../etc', '/data/storage');
   * ```
   *
   * @param mixed $value 待校验的路径
   * @param string $root 允许的根目录（绝对/相对均可，相对时按相对根比对）
   * @param bool $allowAbsolute 是否允许绝对路径（默认不允许）
   * @return string 合法的路径
   * @throws Error 路径不安全（形态）或越界（范围）时抛出
   */
  public static function pathWithin($value, $root, $allowAbsolute = false)
  {
    $value = self::safePath($value, $allowAbsolute);

    $canonicalRoot = Path::canonicalize($root);
    if ($canonicalRoot === "") {
      throw new Error("根目录不合法", 500, "invalidPathRoot:500", $root);
    }
    if ($canonicalRoot !== "/") {
      $canonicalRoot = rtrim($canonicalRoot, "/");
    }

    $target = Path::canonicalize($value);
    //* 空路径即根目录本身
    if ($target === "") {
      return $value;
    }
    //* 相对路径以根为基准求绝对
    if (strpos($target, "/") !== 0) {
      $target = Path::canonicalize($canonicalRoot . "/" . $target);
    }

    //* 根为 "/" 时前缀即 "/"，其余情况用 "根 + /" 避免 "/root2" 误判为 "/root" 的子路径
    $prefix = ($canonicalRoot === "/" ? "" : $canonicalRoot) . "/";
    if ($target !== rtrim($prefix, "/") && strpos($target, $prefix) !== 0) {
      throw new Error("路径越界", 400, "outOfPath:400", $value);
    }

    return $value;
  }

  /**
   * 仅保留字母与数字（及额外允许字符）
   *
   * 用法示例：
   * ```php
   * Sanitizer::alnum('Abc-123!');          // "Abc123"
   * Sanitizer::alnum('a1-b2_c3!', '-_.');  // "a1-b2_c3"（额外放行 - _ .）
   *
   * // 作为缓存键 / 文件名片段，先清洗再拼接
   * $cacheKey = 'user:' . Sanitizer::alnum($uid);
   * ```
   *
   * @param mixed $value 待清洗的值
   * @param string $extra 额外允许的字符（如 "-_."）
   * @return string 清洗后的字符串
   */
  public static function alnum($value, $extra = "")
  {
    $pattern = '/[^A-Za-z0-9' . preg_quote($extra, '/') . ']/';
    return preg_replace($pattern, "", (string)$value);
  }

  /**
   * 统一入口：按类型净化
   *
   * 支持类型：html / js / url / int / str / identifier / path / alnum。
   * 数组会逐项递归净化（`js` 除外，其整体编码）。
   * **不支持 `sql`**：SQL 值安全请用参数绑定，字段名请用 `identifier`。
   *
   * 用法示例：
   * ```php
   * // 类型字符串大小写不敏感
   * Sanitizer::filter('<b>Hi</b>', 'html');   // "&lt;b&gt;Hi&lt;/b&gt;"
   * Sanitizer::filter('128abc', 'int');       // 128
   * Sanitizer::filter('a b', 'url');          // "a%20b"
   *
   * // 数组逐项递归（js 除外）
   * Sanitizer::filter(['<i>', '<j>'], 'html');        // ['&lt;i&gt;', '&lt;j&gt;']
   * Sanitizer::filter(['a' => '<i>'], 'js');          // '{"a":"\u003Ci\u003E"}' 整体编码
   *
   * // 适合按配置批量处理请求字段
   * foreach (['title' => 'html', 'page' => 'int'] as $field => $type) {
   *   $clean[$field] = Sanitizer::filter($input[$field] ?? null, $type);
   * }
   *
   * // 抛 Error(500, "unknownSanitizeType:500")
   * Sanitizer::filter($value, 'sql');
   * ```
   *
   * @param mixed $value 待净化的值
   * @param string $type 净化类型
   * @return mixed 净化后的值
   * @throws Error 未知类型时抛出
   */
  public static function filter($value, $type)
  {
    $type = strtolower((string)$type);

    // js 对整体数组编码，不递归；其余类型对数组递归
    if (is_array($value) && $type !== "js") {
      return array_map(function ($item) use ($type) {
        return self::filter($item, $type);
      }, $value);
    }

    switch ($type) {
      case "html":
        return self::html($value);
      case "js":
        return self::js($value);
      case "url":
        return self::url($value);
      case "int":
        return self::int($value);
      case "str":
        return self::str($value);
      case "identifier":
        return self::identifier($value);
      case "path":
        return self::safePath($value);
      case "alnum":
        return self::alnum($value);
      default:
        throw new Error("未知的净化类型", 500, "unknownSanitizeType:500", $type);
    }
  }
}
