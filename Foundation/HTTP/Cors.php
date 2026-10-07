<?php

namespace kernel\Foundation\HTTP;

use kernel\Foundation\Config;

/**
 * CORS 辅助类
 *
 * 集中实现跨域相关的纯计算（origin 校验/归一化、配置解析、响应头装配），
 * 与具体中间件解耦，便于其它需要判定跨域的场景复用。
 *
 * 设计约定：
 *   - 仅从请求头 Origin 读取来源；畸形/伪造（缺 scheme+host）一律视为非同源。
 *   - 允许来源支持 "*"、逗号分隔字符串、数组三种配置形态。
 *   - allowMethods/allowHeaders/exposeHeaders 同样支持 "*"、逗号分隔字符串、数组三种形态（"*" 即通配），
 *     配置为字符串时原样输出，不再要求必须是数组。
 *   - 命中白名单时精确回显请求 origin（便于配合 credentials）；未命中不输出 Allow-Origin 头。
 *   - 同源判断：{@see originOf()} 取规范化 origin（丢 path/query、默认端口归一、host 小写），
 *     {@see isSameOrigin()} 按 scheme+host+port 全等比较，{@see currentOrigin()} 推导本站 origin；
 *     供「该请求是否本站发起」这类判定（如免 token 的同源 ajax）复用，与 CORS 白名单逻辑相互独立。
 *
 * 配置键（cors.*，未配置时用 DEFAULTS）：
 *   - allowOrigin   允许来源，默认 "*"
 *   - allowMethods  允许方法，默认 GET/POST/PUT/DELETE/PATCH/OPTIONS
 *   - allowHeaders  允许请求头，默认 ["Authorization"]
 *   - exposeHeaders 允许暴露响应头，默认 ["x-auth-token","x-auth-token-expires-at"]
 *   - maxAge        预检缓存秒数，默认 86400
 *   - allowCredentials 是否允许凭据，true 时输出 Allow-Credentials
 *   - 开发模式（mode=development）下不做来源限制：任意合法 origin 一律放行（回显该 origin），
 *     便于本地联调时错误响应也能被浏览器读取
 *   - headers()/applyTo()/emit()：同一套头部计算，分别供「装配到 Response」「直接 header() 输出」使用
 */
class Cors
{
  /** @var array CORS 配置默认值（业务未配置 cors.* 时使用） */
  const DEFAULTS = [
    "allowOrigin" => "*",
    "allowMethods" => ["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"],
    "allowHeaders" => ["Authorization"],
    "exposeHeaders" => ["x-auth-token", "x-auth-token-expires-at"],
    "maxAge" => 86400,
    "allowCredentials" => false,
  ];

  /**
   * 读取 cors 配置项（带默认值）
   *
   * @param string $key
   * @return mixed
   */
  public static function config(string $key)
  {
    return Config::get("cors/" . $key, self::DEFAULTS[$key] ?? null);
  }

  /**
   * 校验 origin 是否为合法「scheme://host」形式
   *
   * HTTP_ORIGIN 可被非浏览器客户端伪造任意字符串，缺 scheme 或 host 的
   * 无法构成有效跨域来源，按非同源处理。
   *
   * @param string $origin
   * @return bool
   */
  public static function isValidOrigin(string $origin): bool
  {
    $parts = parse_url($origin);
    return $parts !== false
      && isset($parts['scheme'])
      && isset($parts['host'])
      && $parts['scheme'] !== ""
      && $parts['host'] !== "";
  }

  /**
   * 归一化 origin：去掉尾部斜杠、host 转小写，便于精确比对
   *
   * @param string $origin
   * @return string
   */
  public static function normalizeOrigin(string $origin): string
  {
    $origin = rtrim($origin, "/");
    $parts = parse_url($origin);
    if ($parts === false || !isset($parts['scheme']) || !isset($parts['host'])) {
      return $origin;
    }
    $scheme = $parts['scheme'] . "://";
    $host = strtolower($parts['host']);
    $port = isset($parts['port']) ? ":" . $parts['port'] : "";
    $path = $parts['path'] ?? "";
    return $scheme . $host . $port . $path;
  }

  /**
   * 从 URL / origin 中取出「scheme://host[:port]」（同源比较用）
   *
   * 与 {@see normalizeOrigin()} 的区别：本方法**丢掉** path / query / fragment，并把默认端口归一化
   * （`http://a.com:80` ≡ `http://a.com`、`https://a.com:443` ≡ `https://a.com`），host 转小写。
   * 协议相对地址（`//a.com/x`）按当前请求的协议补全。
   * 缺 scheme 或 host（相对地址、畸形地址）一律返回 null。
   *
   * @param string|null $url URL 或 origin
   * @return string|null 归一化后的 origin，取不到返回 null
   */
  public static function originOf($url)
  {
    if (!is_string($url) || trim($url) === "") {
      return null;
    }
    $url = trim($url);

    //* 协议相对（//host/path）：按当前请求协议补全
    if (strpos($url, "//") === 0) {
      $current = self::originOf(URL::baseURL());
      $url = ($current === null ? "http" : explode("://", $current)[0]) . ":" . $url;
    }

    $parts = parse_url($url);
    if ($parts === false || !isset($parts['scheme']) || !isset($parts['host'])) {
      return null;
    }

    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    $port = isset($parts['port']) ? (int) $parts['port'] : null;

    //* 默认端口归一化：显式写出的 80/443 与省略写法等价
    if (($scheme === "http" && $port === 80) || ($scheme === "https" && $port === 443)) {
      $port = null;
    }

    return $scheme . "://" . $host . ($port === null ? "" : ":" . $port);
  }

  /**
   * 取当前请求的来源（Origin 头，缺失时退化为 Referer）
   *
   * @return string|null 请求头原文；两者都没有时返回 null
   */
  public static function requestOrigin()
  {
    if (!empty($_SERVER['HTTP_ORIGIN']) && is_string($_SERVER['HTTP_ORIGIN'])) {
      return $_SERVER['HTTP_ORIGIN'];
    }
    if (!empty($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER'])) {
      return $_SERVER['HTTP_REFERER'];
    }
    return null;
  }

  /**
   * 当前站点的 origin（`scheme://host[:port]`）
   *
   * 优先用 {@see URL::baseURL()}（读 REQUEST_SCHEME + HTTPS + HTTP_HOST）；
   * 该函数的 REQUEST_SCHEME 在某些 SAPI 下缺失，此时退化为按 `$_SERVER['HTTPS']` 判协议 + `HTTP_HOST`。
   * 非 Web 上下文（HTTP_HOST 缺失，如 CLI）返回 null。
   *
   * @return string|null
   */
  public static function currentOrigin()
  {
    $baseURL = URL::baseURL();

    if ($baseURL === "") {
      $host = $_SERVER['HTTP_HOST'] ?? null;
      if (!is_string($host) || $host === "") {
        return null;
      }
      $https = $_SERVER['HTTPS'] ?? null;
      $scheme = ($https && strtolower($https) !== "off") ? "https" : "http";
      $baseURL = $scheme . "://" . $host;
    }

    return self::originOf($baseURL);
  }

  /**
   * 判断给定来源与本站是否同源
   *
   * 同源 = scheme + host + port 三者全等（默认端口已归一化，host 大小写不敏感），
   * 即浏览器同源策略的判定方式。
   *
   * 注意（保守策略）：来源缺失、相对地址、畸形地址、或取不到本站 origin 时一律返回 **false**。
   * 若调用方希望「无 Origin/Referer 头视为非跨域」（如 {@see \kernel\Middleware\GlobalCorsMiddleware} 的约定），
   * 请自行先用 {@see requestOrigin()} 判断头是否存在，再调用本方法。
   *
   * @param string|null $url 要判断的来源或 URL；不传/为空时取当前请求头 Origin（缺失时退化为 Referer）
   * @param string|null $currentOrigin 本站 origin；不传时按当前请求推导（见 {@see currentOrigin()}）
   * @return bool 同源返回 true，其余情况 false
   */
  public static function isSameOrigin($url = null, $currentOrigin = null)
  {
    if ($url === null || $url === "") {
      $url = self::requestOrigin();
    }

    $target = self::originOf($url);
    if ($target === null) {
      return false;
    }

    $current = self::originOf($currentOrigin === null ? self::currentOrigin() : $currentOrigin);

    return $current !== null && $target === $current;
  }

  /**
   * 解析允许来源配置为归一化数组
   *
   * - 数组   → 直接使用
   * - "*"    → ["*"]
   * - 字符串 → 按逗号拆分并去空白（#1 兜底由 DEFAULTS 提供）
   *
   * @return string[]
   */
  public static function allowOrigins(): array
  {
    $allow = self::config("allowOrigin");
    if (is_array($allow)) {
      $list = $allow;
    } elseif ($allow === "*") {
      return ["*"];
    } else {
      $list = explode(",", (string) $allow);
    }
    return array_values(array_filter(array_map(function ($item) {
      return self::normalizeOrigin(trim((string) $item));
    }, $list)));
  }

  /**
   * 计算最终回写的 Access-Control-Allow-Origin 值
   *
   * - 请求无合法 Origin      → null（不发送该头）
   * - origin 命中白名单/通配  → 回显该 origin
   * - 未命中               → null（不发送该头，而非空字符串脏头）
   *
   * @param string|null $requestOrigin 取自请求头 Origin 的值（可为 null）
   * @return string|null
   */
  public static function resolveAllowOrigin($requestOrigin)
  {
    if ($requestOrigin === null || !self::isValidOrigin($requestOrigin)) {
      return null;
    }
    $origin = self::normalizeOrigin($requestOrigin);

    //* 开发模式：不做来源限制，任意合法 origin 一律放行（回显该 origin）
    if (self::isDevelopment()) {
      return $origin;
    }

    $allowed = self::allowOrigins();
    if (in_array("*", $allowed, true) || in_array($origin, $allowed, true)) {
      return $origin;
    }
    return null;
  }

  /**
   * 是否开发模式（mode=development）
   *
   * 开发模式下 CORS 不做来源限制：任意合法 origin 均放行，便于本地联调。
   * 配置未加载或读取失败时按非开发模式处理（保守），避免误放开线上。
   *
   * @return bool
   */
  public static function isDevelopment(): bool
  {
    try {
      return Config::get("mode", "production") === "development";
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * 将 allowMethods/allowHeaders/exposeHeaders 配置归一化为响应头字符串
   *
   * 支持三种形态：
   *   - 数组        → 用逗号拼接（如 ["GET","POST"] → "GET,POST"）
   *   - "*" / 字符串 → 原样输出（"*" 即通配；逗号分隔字符串也原样回写）
   *
   * 直接对字符串调 implode 在 PHP 8 会抛 TypeError，故此处做形态兜底。
   *
   * @param mixed $value
   * @return string
   */
  private static function toHeaderList($value): string
  {
    if (is_array($value)) {
      return implode(",", $value);
    }
    return (string) $value;
  }

  /**
   * 计算全部 CORS 响应头（key => value）
   *
   * 供 applyTo()（写入 Response）与 emit()（直接 header() 输出）复用，
   * 避免两处重复装配逻辑。
   *
   * @param string|null $requestOrigin 请求头 Origin 值（可为 null）
   * @return array<string,string>
   */
  public static function headers($requestOrigin): array
  {
    $headers = [];
    $allowOrigin = self::resolveAllowOrigin($requestOrigin);
    if ($allowOrigin !== null) {
      $headers["Access-Control-Allow-Origin"] = $allowOrigin;
      if (self::config("allowCredentials") === true) {
        $headers["Access-Control-Allow-Credentials"] = "true";
      }
      // 非通配（按请求 origin 动态回显）时告知缓存按 origin 区分
      if (!in_array("*", self::allowOrigins(), true)) {
        $headers["Vary"] = "Origin";
      }
    }

    $headers["Access-Control-Allow-Methods"] = self::toHeaderList(self::config("allowMethods"));
    $headers["Access-Control-Allow-Headers"] = self::toHeaderList(self::config("allowHeaders"));
    $headers["Access-Control-Expose-Headers"] = self::toHeaderList(self::config("exposeHeaders"));
    $headers["Access-Control-Max-Age"] = (string) self::config("maxAge");

    return $headers;
  }

  /**
   * 将 CORS 响应头应用到 Response
   *
   * 后置使用：先拿到 Response 再调用本方法补充头。非白名单来源不输出
   * Access-Control-Allow-Origin（符合规范），但其它 Access-Control-* 常驻头仍会设置。
   *
   * @param \kernel\Foundation\HTTP\Response $response
   * @param string|null $requestOrigin 请求头 Origin 值（可为 null）
   * @return \kernel\Foundation\HTTP\Response
   */
  public static function applyTo(Response $response,$requestOrigin): Response
  {
    foreach (self::headers($requestOrigin) as $key => $value) {
      $response->header($key, $value);
    }

    return $response;
  }

  /**
   * 直接输出 CORS 响应头（header()）
   *
   * 用于拿不到 Response 的场景：全局异常处理器在输出错误响应前补 CORS 头，
   * 使「抛异常 → 错误响应」同样带上跨域头，浏览器才能读到报错内容。
   * PHP header() 会累积，随后 Response::output() 输出的头部不会覆盖这些 CORS 头。
   *
   * @param string|null $requestOrigin 请求头 Origin 值（可为 null）
   * @return void
   */
  public static function emit($requestOrigin)
  {
    foreach (self::headers($requestOrigin) as $key => $value) {
      header($key . ":" . $value);
    }
  }
}
