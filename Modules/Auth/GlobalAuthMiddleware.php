<?php

namespace kernel\Modules\Auth;

use kernel\Foundation\App;
use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\Middleware\MiddlewareBase;
use kernel\Foundation\Result;

class GlobalAuthMiddleware extends MiddlewareBase
{
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
      return $RR->error(401, "Auth:401001", "请登录后重试", [
        "strongCheck" => $strongCheck,
        "msg" => "未登录，缺少Token（verify）"
      ]);
    }
    if (empty($token)) {
      return $RR;
    }

    // 校验时绑定 app_id，避免多 app 共用表时串号
    $authData = Auth::model()->where("token", $token)->where("app_id", App::id())->first();
    if (empty($authData)) {
      if ($strongCheck) {
        return $RR->error(401, "Auth:401003", "请登录后重试", "无效的Token");
      }
      return $RR;
    }

    $expiresAt = $authData['expires_at'];
    // 仅用绝对过期时间判定是否过期，不依赖 created_at
    if (time() > $expiresAt) {
      return $RR->error(401, "Auth:401004", "登录已失效，请重新登录", "Token已过期");
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

    Auth::tokenExpiresAt($expiresAt);
    Auth::token($token);
    Auth::userId($authData['user_id']);
    Auth::logged(true);

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
   * 仅当本次请求成功登录（Auth::logged() 为真）时才写入，便于客户端
   * 续期/携带 token。通过 Response::header() 管理头信息，由框架统一输出。
   *
   * @param \kernel\Foundation\HTTP\Response $response
   * @return \kernel\Foundation\HTTP\Response
   */
  protected function applyAuthHeader(Response $response): Response
  {
    if (Auth::logged()) {
      $response->header("Authorization", Auth::token() . "/" . Auth::tokenExpiresAt());
    }
    return $response;
  }
}
