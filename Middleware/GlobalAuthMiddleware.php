<?php

namespace kernel\Middleware;

use Error;
use kernel\Foundation\Output;
use kernel\Foundation\Request;
use kernel\Foundation\Response;
use kernel\Foundation\Store;
use kernel\Model\UserLoginsModel;
use kernel\Service\AuthService;
use ReflectionMethod;

class GlobalAuthMiddleware
{
  private function verifyToken(Request $request, bool $strongCheck = true): void
  {
    $token = $request->headers("Authorization") ?: $request->query("Authorization") ?: $request->body("Authorization");
    if ($strongCheck) {
      if (empty($token) || !$token)
        Response::error("Not_Auth");
    }
    if (empty($token)) {
      return;
    }
    if (!preg_match("/^Bearer (.+)$/", $token, $token)) {
      Response::error("Not_Auth");
    }
    $token = $token[1];
    if ($strongCheck) {
      if (empty($token) || !$token)
        Response::error("Not_Auth");
    }
    if (empty($token)) {
      return;
    }
    $ULM = new UserLoginsModel();
    $token = $ULM->getByToken($token);
    if ($token === null) {
      header("Authorization", "");
      if ($strongCheck) {
        Response::error(401, "Auth:401001", "请登录后重试", [], "token无效");
      }
    }
    $expirationDate = $token['createdAt'] + $token['expiration'];
    $diffDay = round((time() - $token['createdAt']) / 86400);
    $expirationDay = round($token['expiration'] / 86400);
    if (time() > $expirationDate || $diffDay > $expirationDay) {
      Response::error(401, "Auth:401002", "登录已过期，请重新登录", [], "token失效");
    }
    header("Authorization:" . $token['token'] . "/" . $expirationDate, true);
    //* 如果token的有效期剩余20%，就自动刷新token
    if ($diffDay / $expirationDay > 0.8) {
      //* 自动刷新token
      $newToken = AuthService::generateToken($token['userId']);
      header("Authorization:" . $newToken['value'] . "/" . $newToken['expirationDate'], true);
      $ULM = new UserLoginsModel();
      $ULM->deleteByToken($token['token']);
      $newToken['token'] = $newToken['value'];
      $token = $newToken;
    }
    Store::setApp([
      "auth" => $token
    ]);
  }
  public function handle($next, Request $request)
  {
    $router = $request->router;
    $isAdminVerify = false;
    if (!$router) {
      $next();
      return;
    }

    if (!class_exists($router['controller'])) {
      throw new Error("Router controller(".$router['controller'].") not exists");
    }

    if (get_parent_class($router['controller']) === "kernel\Foundation\AuthController") {
      if (method_exists($router['controller'], "Admin")) {
        $adminMethodRM = new ReflectionMethod($router['controller'], "Admin");
        if ($adminMethodRM->isStatic() && $router['controller']::Admin()) {
          $isAdminVerify = true;
          $this->verifyToken($request);
          $router['controller']::verifyAdmin();
        }
      } else if ($router['controller']::$Admin) {
        $isAdminVerify = true;
        $this->verifyToken($request);
        $router['controller']::verifyAdmin();
      }

      if ($isAdminVerify === false) {
        if (method_exists($router['controller'], "Auth")) {
          $authMethodRM = new ReflectionMethod($router['controller'], "Auth");
          if ($authMethodRM->isStatic() && $router['controller']::Auth()) {
            $this->verifyToken($request);
            $router['controller']::verifyAuth();
          }
        } else if ($router['controller']::$Auth) {
          $this->verifyToken($request);
          $router['controller']::verifyAuth();
        } else {
          $this->verifyToken($request, false);
        }
      }
    } else {
      if ($request->headers("token")) {
        $this->verifyToken($request);
      }
    }

    $next();
  }
}
