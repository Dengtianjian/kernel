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
 *   - 命中白名单时精确回显请求 origin（便于配合 credentials）；未命中不输出 Allow-Origin 头。
 *
 * 配置键（cors.*，未配置时用 DEFAULTS）：
 *   - allowOrigin   允许来源，默认 "*"
 *   - allowMethods  允许方法，默认 GET/POST/PUT/DELETE/PATCH/OPTIONS
 *   - allowHeaders  允许请求头，默认 ["Authorization"]
 *   - exposeHeaders 允许暴露响应头，默认 ["x-auth-token","x-auth-token-expires-at"]
 *   - maxAge        预检缓存秒数，默认 86400
 *   - allowCredentials 是否允许凭据，true 时输出 Allow-Credentials
 */
class Cors
{
  /** @var array CORS 配置默认值（业务未配置 cors.* 时使用） */
  public const DEFAULTS = [
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
  public static function resolveAllowOrigin(?string $requestOrigin): ?string
  {
    if ($requestOrigin === null || !self::isValidOrigin($requestOrigin)) {
      return null;
    }
    $origin = self::normalizeOrigin($requestOrigin);
    $allowed = self::allowOrigins();
    if (in_array("*", $allowed, true) || in_array($origin, $allowed, true)) {
      return $origin;
    }
    return null;
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
  public static function applyTo(Response $response, ?string $requestOrigin): Response
  {
    $allowOrigin = self::resolveAllowOrigin($requestOrigin);
    if ($allowOrigin !== null) {
      $response->header("Access-Control-Allow-Origin", $allowOrigin);
      if (self::config("allowCredentials") === true) {
        $response->header("Access-Control-Allow-Credentials", "true");
      }
      // 非通配（按请求 origin 动态回显）时告知缓存按 origin 区分
      if (!in_array("*", self::allowOrigins(), true)) {
        $response->header("Vary", "Origin");
      }
    }

    $response->header("Access-Control-Allow-Methods", implode(",", self::config("allowMethods")));
    $response->header("Access-Control-Allow-Headers", implode(",", self::config("allowHeaders")));
    $response->header("Access-Control-Expose-Headers", implode(",", self::config("exposeHeaders")));
    $response->header("Access-Control-Max-Age", self::config("maxAge"));

    return $response;
  }
}
