<?php

namespace kernel\Middleware;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Config;
use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Middleware;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Store;
use kernel\Model\LoginsModel;
use kernel\Service\AuthService;

class GlobalAuthMiddleware extends Middleware
{
  protected $LoginsModel = null;
  public function __construct(Request $request, Controller $controller)
  {
    $this->LoginsModel = LoginsModel::class;
    parent::__construct($request, $controller);
  }
  /**
   * 判断当前请求是否同源
   *
   * @return bool
   */
  protected function sameOrigin()
  {
    if ($this->request->header->has("Sec-Fetch-Site")) {
      return $this->request->header->get("Sec-Fetch-Site") === "same-origin";
    } else {
      $Origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
      if (!$Origin) {
        $Referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
        if ($Referer) {
          $parsed = parse_url($Referer);
          $Origin = implode("", [
            $parsed['scheme'],
            "://",
            $parsed['host'],
            in_array($parsed['port'], ["80", "443"]) ? "" : $parsed['port']
          ]);
        }
      }
      if (Config::get("cors/sameOrigin") && Config::get("mode") === 'development') {
        if (is_array(Config::get("cors/sameOrigin"))) {
          return in_array($Origin, Config::get("cors/sameOrigin"));
        }
        return Config::get("cors/sameOrigin") === $Origin;
      } else {
        return F_BASE_URL === $Origin;
      }
    }
  }
  /**
   * 验证token
   *
   * @param boolean $strongCheck 严格校验
   * @return ReturnResult
   */
  protected function verifyToken($strongCheck = true)
  {
    $RR = new ReturnResult(true);
    $token = $this->request->header->get("Authorization") ?: $this->request->query->get("authToken") ?: $this->request->body->get("authToken");
    if ($strongCheck && (empty($token) || is_null($token))) {
      $RR->error(401, "Auth:401001", "请登录后重试", [
        "strongCheck" => $strongCheck,
        "msg" => "未登录，缺少Token（verify）"
      ]);
      return $RR;
    }
    if (empty($token)) {
      return $RR;
    }
    if (!preg_match("/^Bearer (.+)$/", $token, $token)) {
      $RR->error(401, "Auth:401002", "请登录后重试", "头部缺少Token参数");
      return $RR;
    }
    $token = $token[1];
    $ULM = new $this->LoginsModel();
    $auth = $ULM->getByToken($token);
    if ($auth === null) {
      header("Authorization", "");
      if ($strongCheck) {
        $RR->error(401, "Auth:401003", "请登录后重试", "无效的Token");
        return $RR;
      }
    }
    if (!$auth) {
      return $RR;
    }
    $expirationDate = $auth['createdAt'] + $auth['expiration'];
    $diffDay = round((time() - $auth['createdAt']) / 86400);
    $expirationDay = round($auth['expiration'] / 86400);
    if (time() > $expirationDate || $diffDay > $expirationDay) {
      $RR->error(401, "Auth:401004", "登录已失效，请重新登录", "Token已过期");
      return $RR;
    }
    header("Authorization:" . $auth['token'] . "/" . $expirationDate, true);
    //* 如果token的有效期剩余20%，就自动刷新token
    if ($diffDay / $expirationDay > 0.8) {
      //* 自动刷新token
      $newToken = AuthService::generateToken($auth['userId']);
      header("Authorization:" . $newToken['value'] . "/" . $newToken['expirationDate'], true);
      $ULM->deleteByToken($auth['token']);
      $newAuth = [
        "id" => $ULM->genId(),
        "token" =>  $newToken['value'],
        "expiration" =>  $newToken['expiration'],
        "userId" => $auth['userId']
      ];
      $ULM->insert($newAuth);

      $auth = array_merge($auth, $newAuth);
      $token = $newToken['value'];
    }
    
    Store::setApp([
      "auth" => $auth,
      "token" => $token,
      "logged" => true,
      "userId" => $auth['userId']
    ]);
    return $RR;
  }
  public function handle(\Closure $next)
  {
    if (!($this->controller instanceof AuthController)) {
      $Verified = $this->verifyToken(false);
      if ($Verified->error) {
        return $Verified;
      }
      return $next();
    }

    $adminChecked = false;
    $authChecked = false;

    if ($this->controller->Admin) {
      $adminChecked = true;
      $Verified = $this->verifyToken();
      if ($Verified->error) {
        return $Verified;
      }
      $adminVerified = $this->controller->verifyAdmin();
      if ($adminVerified->error) {
        return $adminVerified;
      }
    }
    if (!$adminChecked && $this->controller->Auth) {
      $authChecked = true;
      $Verified = $this->verifyToken();
      if ($Verified->error) {
        return $Verified;
      }
      $authVerified = $this->controller->verifyAuth();
      if ($authVerified->error) {
        return $authVerified;
      }
    }
    if (!$authChecked) {
      $Verified = $this->verifyToken(false);
      if ($Verified->error) {
        return $Verified;
      }
    }

    $res = $next();
    if (Store::getApp("logged")) {
      header("Authorization:" . Store::getApp("auth")['token'] . "/" . Store::getApp("auth")['expiration'], true);
    }

    return $res;
  }
}
