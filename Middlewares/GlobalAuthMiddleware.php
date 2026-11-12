<?php

namespace kernel\Middlewares;

use kernel\Modules\Auth\Auth;
use kernel\Foundation\App;
use kernel\Foundation\Application\Applications;
use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\Middleware\MiddlewareBase;
use kernel\Support\Result;

/**
 * 全局认证中间件
 *
 * 从 Authorization 头取 token，校验 logins 表，把结果写入 {@see Auth}（登录态由 AuthModule 持有）
 * ——只写上下文，不碰服务状态，也不把「当前用户」缓存到任何静态变量里。
 *
 * 中间件由 {@see \kernel\Modules\Auth\AuthProvider} 的构造函数按运行环境挂上
 * （DiscuzX 下换成 {@see \kernel\DiscuzX\Middlewares\GlobalDiscuzXAuthMiddleware}）。
 *
 * 注意：中间件是 `new $class($request, $controller)` 直接实例化的（见
 * {@see \kernel\Foundation\Middleware\Middleware::executeMiddleware()}），不走容器注入，
 * 因此这里用 `Applications::default()` 取当前应用，再取上下文 / 服务。
 */
class GlobalAuthMiddleware extends MiddlewareBase
{
  /**
   * 当前应用实例
   *
   * @return App
   */
  protected function app()
  {
    return Applications::default();
  }

  /**
   * 验证token
   *
   * @param boolean $strongCheck 严格校验
   * @return Result
   */
  protected function verifyToken($strongCheck = true)
  {
    $RR = new Result(true);
    $token = null;
    if ($this->request->header->has("Authorization")) {
      $token = $this->request->header->get("Authorization");

      // 兼容 "Bearer <token>" 与裸 token 两种形式
      if (preg_match("/^Bearer\s+(.+)$/i", trim($token), $m)) {
        $token = $m[1];
      }
    }
    // 仅从 Authorization 头取 token，避免 token 进入 URL/Body 被日志记录或泄露

    if ($strongCheck && (empty($token) || is_null($token))) {
      return $RR->error(401, "Auth:401001", "请先登录后再操作", [
        "strongCheck" => $strongCheck,
        "msg" => "未登录，缺少Token（verify）"
      ]);
    }
    if (empty($token)) {
      return $RR;
    }

    // 查哪张表、按什么条件查由认证服务决定（跨应用时查提供方的表）
    $authData = Auth::findValidToken($token);
    if (empty($authData)) {
      if ($strongCheck) {
        return $RR->error(401, "Auth:401003", "登录信息无效，请重新登录", "无效的Token");
      }
      return $RR;
    }

    $expiresAt = $authData['expires_at'];
    // 仅用绝对过期时间判定是否过期，不依赖 created_at
    if (time() > $expiresAt) {
      return $RR->error(401, "Auth:401004", "登录已过期，请重新登录", "Token已过期");
    }
    $expirationDay = (int)($authData['expire_days'] ?? 0);
    $diffDay = round((time() - $authData['created_at']) / 86400);

    //* 如果token的有效期剩余20%，就自动刷新token
    if ($expirationDay > 0 && $diffDay / $expirationDay > 0.8) {
      //* 自动刷新token
      $newToken = Auth::createToken($authData['user_id']);
      Auth::deleteToken($authData['token']);

      $token = $newToken['value'];
      $expiresAt = $newToken['expiresAt'];
    }

    Auth::setTokenExpiresAt($expiresAt)
      ->setToken($token)
      ->setUserId($authData['user_id'])
      ->setLogged(true);

    return $RR;
  }

  /**
   * 中间件处理：校验通过后执行业务逻辑，并为响应补充 Authorization 头
   *
   * 后置注入——先 $next() 执行后续逻辑拿到 Response，再统一写入鉴权头，
   * 不绕过框架的响应头管理（避免直接调用全局 header()）。
   *
   * @param \Closure $next
   * @return \kernel\Foundation\HTTP\Response
   */
  public function handle(\Closure $next)
  {
    if (!($this->controller instanceof AuthController)) {
      $verified = $this->verifyToken(false);
      if ($verified->error) {
        return $verified;
      }
      return $this->applyAuthHeader($next());
    }

    $needAdmin = !empty($this->controller->admin);
    $needAuth = !empty($this->controller->auth);
    // 每请求仅校验一次：admin/auth 走强校验，其余走弱校验
    $verified = $this->verifyToken($needAdmin || $needAuth);
    if ($verified->error) {
      return $verified;
    }

    if ($needAdmin) {
      $adminVerified = $this->controller->verifyAdmin();
      if ($adminVerified->error) {
        return $adminVerified;
      }
    } elseif ($needAuth) {
      $authVerified = $this->controller->verifyAuth();
      if ($authVerified->error) {
        return $authVerified;
      }
    }

    return $this->applyAuthHeader($next());
  }

  /**
   * 为响应补充 Authorization 头（后置注入）
   *
   * 仅当本次请求成功登录（{@see Auth::logged()} 为真）时才写入，便于客户端
   * 续期/携带 token。通过 Response::header() 管理头信息，由框架统一输出。
   *
   * @param \kernel\Foundation\HTTP\Response $response
   * @return \kernel\Foundation\HTTP\Response
   */
  protected function applyAuthHeader(Response $response): Response
  {
    if (Auth::logged()) {
      $response->header("x-auth-token", Auth::token());
      $response->header("x-auth-token-expires-at", Auth::tokenExpiresAt());
    }
    return $response;
  }
}
