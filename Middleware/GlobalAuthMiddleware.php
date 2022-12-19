<?php

namespace gstudio_kernel\Middleware;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use Error;
use gstudio_kernel\Foundation\Config;
use gstudio_kernel\Foundation\Controller\AuthController;
use gstudio_kernel\Foundation\Lang;
use gstudio_kernel\Foundation\Output;
use gstudio_kernel\Foundation\Request;
use gstudio_kernel\Foundation\Response;
use gstudio_kernel\Foundation\Store;
use gstudio_kernel\Model\LoginsModel;
use gstudio_kernel\Platform\Discuzx\Member;
use gstudio_kernel\Service\AuthService;
use ReflectionMethod;

class GlobalAuthMiddleware
{
  private function sameOrigin(Request $request)
  {
    if ($request->headers("Sec-Fetch-Site")) {
      return $request->headers("Sec-Fetch-Site") === "same-origin";
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
  private function verifyToken($request, $strongCheck = true)
  {
    $token = $request->headers("Authorization") ?: $request->query("Authorization") ?: $request->body("Authorization");
    if ($strongCheck && (empty($token) || !$token)) {
      Response::error(401, "Auth:40101", Lang::value("kernel/auth/needLogin"), [], Lang::value("kernel/auth/emptyToken"));
    }
    if (empty($token)) {
      return;
    }
    if (!preg_match("/^Bearer (.+)$/", $token, $token)) {
      Response::error(401, "Auth:40102", Lang::value("kernel/auth/needLogin"), [], Lang::value("kernel/auth/headerTOKENParamError"));
    }
    $token = $token[1];
    if ($strongCheck) {
      if (empty($token) || !$token)
        Response::error(401, "Auth:40103", Lang::value("kernel/auth/needLogin"), [], Lang::value("kernel/auth/headerAuthorizationEmpty"));
    }
    if (empty($token)) {
      return null;
    }
    $ULM = new LoginsModel();
    $token = $ULM->getByToken($token);
    if ($token === null) {
      header("Authorization", "");
      if ($strongCheck) {
        Response::error(401, "Auth:40104", Lang::value("kernel/auth/needLogin"), [], Lang::value("kernel/auth/invalidToken"));
      }
    }
    if (!$token) {
      return null;
    }
    $expirationDate = $token['createdAt'] + $token['expiration'];
    $diffDay = round((time() - $token['createdAt']) / 86400);
    $expirationDay = round($token['expiration'] / 86400);
    if (time() > $expirationDate || $diffDay > $expirationDay) {
      Response::error(401, "Auth:40105", Lang::value("kernel/auth/loginExpired"), [], Lang::value("kernel/auth/expiredToken"));
    }
    header("Authorization:" . $token['token'] . "/" . $expirationDate, true);
    //* 如果token的有效期剩余20%，就自动刷新token
    if ($diffDay / $expirationDay > 0.8) {
      //* 自动刷新token
      $newToken = AuthService::generateToken($token['userId']);
      header("Authorization:" . $newToken['value'] . "/" . $newToken['expirationDate'], true);
      $ULM->deleteByToken($token['token']);
      $newToken['token'] = $newToken['value'];
      $token = $newToken;
    }

    Store::setApp([
      "auth" => $token
    ]);
  }
  public function verifyViewControllerAdmin($controller)
  {
    $Member = getglobal("member");
    if ((int)$Member['uid'] === 0) {
      Response::error(401, "Auth:40101", Lang::value("kernel/auth/needLogin"), [], Lang::value("kernel/auth/emptyToken"));
    }
    if (is_array($controller::$Admin)) {
      if (!in_array($Member['adminid'], $controller::$Admin)) {
        Response::error(403, "Auth:40301", Lang::value("kernel/auth/noAccess"), [], Lang::value("kernel/auth/insufficientPermissions"));
      }
    } else if (is_bool($controller::$Admin)) {
      if ((int)$Member['adminid'] !== 1) {
        Response::error(403, "Auth:40302", Lang::value("kernel/auth/noAccess"), [], Lang::value("kernel/auth/insufficientPermissions"));
      }
    } else if (is_numeric($controller::$Admin) || is_string($controller::$Admin)) {
      if ((int)$Member['adminid'] !== (int)$controller::$Admin) {
        Response::error(403, "Auth:40302", Lang::value("kernel/auth/noAccess"), [], Lang::value("kernel/auth/insufficientPermissions"));
      }
    }
  }
  public function verifyViewControllerAuth($controller)
  {
    $Member = getglobal("member");
    // Output::debug($Member);
    if ((int)$Member['uid'] === 0) {
      Response::error(401, "Auth:40101", Lang::value("kernel/auth/needLogin"), [], Lang::value("kernel/auth/emptyToken"));
    }
    if (is_array($controller::$Auth)) {
      if (!in_array($Member['groupid'], $controller::$Auth)) {
        Response::error(403, "Auth:40301", Lang::value("kernel/auth/noAccess"), [], Lang::value("kernel/auth/insufficientPermissions"));
      }
    } else if (is_numeric($controller::$Auth) || is_string($controller::$Auth)) {
      if ((int)$Member['groupid'] !== (int)$controller::$Auth) {
        Response::error(403, "Auth:40302", Lang::value("kernel/auth/noAccess"), [], Lang::value("kernel/auth/insufficientPermissions"));
      }
    }
  }
  public function verify(Request $request, $controller, $viewVerifyType, $strongCheck = false)
  {
    //* 如果是同源，那么来源就是视图页面发起的ajax请求，无需token，用verifyViewControllerAdmin和verifyViewControllerAuth去验证
    $SameOrigin = $this->sameOrigin($request);
    if ($request->ajax()) {
      if ($SameOrigin) {
        if ($viewVerifyType === "admin") {
          $this->verifyViewControllerAdmin($controller);
        } else {
          $this->verifyViewControllerAuth($controller);
        }
      } else {
        $this->verifyToken($request, $strongCheck);
      }
    } else {
      if ($viewVerifyType === "admin") {
        $this->verifyViewControllerAdmin($controller);
      } else {
        $this->verifyViewControllerAuth($controller);
      }
    }
  }
  public function handle($next, Request $request)
  {
    $router = $request->router;
    $SameOrigin = $this->sameOrigin($request);
    $isAdminVerify = false;
    if (!$router) {
      $next();
      return;
    }

    if (is_callable($router['controller'])) {
      $next();
      return;
    }

    if (!class_exists($router['controller'])) {
      throw new Error("Router controller(" . $router['controller'] . ") not exists");
    }

    if (get_parent_class($router['controller']) === "gstudio_kernel\Foundation\Controller\AuthController") {

      //* 验证Formhash
      $router['controller']::verifyFormhash();

      $needCheckAdmin = true;
      $isAdminVerify = false;
      $strongCheckAdmin = false;
      if (is_array($router['controller']::$AdminMethods)) {
        $methods = array_map(function ($item) {
          return strtolower($item);
        }, $router['controller']::$AdminMethods);
        if (count($methods)) {
          if (in_array(strtolower($request->method), $methods)) {
            $strongCheckAdmin = true;
          } else {
            $needCheckAdmin = false;
          }
        }
      }

      if ($needCheckAdmin) {
        if (method_exists($router['controller'], "Admin")) {
          $adminMethodRM = new ReflectionMethod($router['controller'], "Admin");
          if ($adminMethodRM->isStatic() && $router['controller']::Admin()) {
            $isAdminVerify = true;
            $this->verify($request, $router['controller'], "admin", true);
            $router['controller']::verifyAdmin();
          }
        } else if ($router['controller']::$Admin || $strongCheckAdmin) {
          $isAdminVerify = true;
          $this->verify($request, $router['controller'], "admin", true);
          $router['controller']::verifyAdmin();
        }
      }

      if ($isAdminVerify === false) {
        $needCheckAuth = true;
        $strongCheckAuth = false;
        if (is_array($router['controller']::$AuthMethods)) {
          $methods = array_map(function ($item) {
            return strtolower($item);
          }, $router['controller']::$AuthMethods);
          if (count($methods)) {
            if (in_array(strtolower($request->method), $methods)) {
              $strongCheckAuth = true;
            } else {
              $needCheckAuth = false;
            }
          }
        }

        if ($needCheckAuth) {
          if (method_exists($router['controller'], "Auth")) {
            $authMethodRM = new ReflectionMethod($router['controller'], "Auth");
            if ($authMethodRM->isStatic() && $router['controller']::Auth()) {
              $this->verify($request, $router['controller'], "auth", true);
              $router['controller']::verifyAuth();
            }
          } else if ($router['controller']::$Auth || $strongCheckAuth) {
            $this->verify($request, $router['controller'], "auth", true);
            $router['controller']::verifyAuth();
          }
        } else {
          $this->verify($request, $router['controller'], "auth");
        }
      }
    } else {
      if ($request->headers("Authorization")) {
        $this->verifyToken($request);
      }
    }

    $memberInfo = null;
    if ($request->ajax() && !$SameOrigin) {
      $Auth = Store::getApp("auth");
      if ($Auth && isset($Auth['userId'])) {
        $memberInfo = Member::get($Auth['userId']);
        include_once libfile("function/member");
        \setloginstatus($memberInfo, 0);
      }
      Store::setApp([
        "member" => $memberInfo
      ]);
    } else {
      $memberInfo = Member::get(getglobal("uid"));
      Store::setApp([
        "member" => $memberInfo
      ]);
    }

    $next();
  }
}
