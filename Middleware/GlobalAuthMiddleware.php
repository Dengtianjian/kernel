<?php

namespace kernel\Middleware;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

use kernel\Foundation\Config;
use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Middleware;
use kernel\Foundation\Response;
use kernel\Foundation\ReturnResult;
use kernel\Foundation\Store;
use kernel\Model\LoginsModel;
use kernel\Service\AuthService;

class GlobalAuthMiddleware extends Middleware
{
  protected $LoginsModel = null;
  public function __construct()
  {
    $this->LoginsModel = LoginsModel::class;
  }
  /**
   * 判断当前请求是否同源
   *
   * @param Request $request 请求实例
   * @return bool
   */
  protected function sameOrigin(Request $request)
  {
    if ($request->header->has("Sec-Fetch-Site")) {
      return $request->header->get("Sec-Fetch-Site") === "same-origin";
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
   * @param Request $request 请求体
   * @param boolean $strongCheck 严格校验
   * @return ReturnResult
   */
  protected function verifyToken(Request $request, $strongCheck = true)
  {
    $RR = new ReturnResult(true);
    $token = $request->header->get("Authorization") ?: $request->query->get("authToken") ?: $request->body->get("authToken");
    if ($strongCheck && (empty($token) || is_null($token))) {
      $RR->error(401, "Auth:401001", "请登录后重试", null, "空Token");
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
    $token = $ULM->getByToken($token);
    if ($token === null) {
      header("Authorization", "");
      if ($strongCheck) {
        $RR->error(401, "Auth:401003", "请登录后重试", "无效的Token");
        return $RR;
      }
    }
    if (!$token) {
      return $RR;
    }
    $expirationDate = $token['createdAt'] + $token['expiration'];
    $diffDay = round((time() - $token['createdAt']) / 86400);
    $expirationDay = round($token['expiration'] / 86400);
    if (time() > $expirationDate || $diffDay > $expirationDay) {
      $RR->error(401, "Auth:401004", "登录已失效，请重新登录", "Token已过期");
      return $RR;
    }
    header("Authorization:" . $token['token'] . "/" . $expirationDate, true);
    //* 如果token的有效期剩余20%，就自动刷新token
    if ($diffDay / $expirationDay > 0.8) {
      //* 自动刷新token
      $newToken = AuthService::generateToken($token['userId']);
      header("Authorization:" . $newToken['value'] . "/" . $newToken['expirationDate'], true);
      $ULM->deleteByToken($token['token']);
      $ULM->insert([
        "id" => $ULM->genId(),
        "token" =>  $newToken['value'],
        "expiration" =>  $newToken['expiration'],
        "userId" => $token['userId']
      ]);
      $token = $newToken;
    }

    Store::setApp([
      "auth" => $token
    ]);
    return $RR;
  }
  public function handle(\Closure $next, Request $request, $Controller = null)
  {
    if (!($Controller instanceof AuthController)) {
      $Verified = $this->verifyToken($request);
      if ($Verified->error) {
        return $Verified;
      }
      return $next();
    }

    $adminChecked = false;
    $authChecked = false;

    if ($Controller->Admin) {
      $adminChecked = true;
      $Verified = $this->verifyToken($request);
      if ($Verified->error) {
        return $Verified;
      }
      $adminVerified = $Controller->verifyAdmin();
      if ($adminVerified->error) {
        return $adminVerified;
      }
    }
    if (!$adminChecked && $Controller->Auth) {
      $authChecked = true;
      $Verified = $this->verifyToken($request);
      if ($Verified->error) {
        return $Verified;
      }
      $authVerified = $Controller->verifyAuth();
      if ($authVerified->error) {
        return $authVerified;
      }
    }
    if (!$authChecked) {
      $Verified = $this->verifyToken($request, false);
      if ($Verified->error) {
        return $Verified;
      }
    }

    $res = $next();
    if (Store::getApp("auth")) {
      header("Authorization:" . Store::getApp("auth")['token'] . "/" . Store::getApp("auth")['expiration'], true);
    }

    return $res;
  }
}
