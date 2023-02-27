<?php

namespace kernel\Platform\DiscuzX\Middleware;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Store;
use kernel\Middleware\GlobalAuthMiddleware;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Member\DiscuzXMember;
use kernel\Platform\DiscuzX\Model\DiscuzXLoginsModel;

class GlobalDiscuzXAuthMiddleware extends GlobalAuthMiddleware
{
  public function __construct()
  {
    $this->LoginsModel = DiscuzXLoginsModel::class;
  }
  /**
   * 验证视图控制器管理员权限
   *
   * @param DiscuzXController $Controller 控制器
   * @return ReturnResult
   */
  public function verifyViewControllerAdmin(DiscuzXController $Controller)
  {
    $RR = new ReturnResult(true);
    $Member = getglobal("member");
    if ((int)$Member['uid'] === 0) {
      $RR->error(401, "DiscuzXAdminAuth:401001", "请登录后重试", "空Token");
    }
    if (is_array($Controller->Admin)) {
      if (!in_array($Member['adminid'], $Controller->Admin)) {
        $RR->error(403, "DiscuzXAdminAuth:403001", "无权访问", "非管理员，无权访问");
      }
    } else if (is_bool($Controller->Admin)) {
      if ((int)$Member['adminid'] !== 1) {
        $RR->error(403, "DiscuzXAdminAuth:403003", "无权访问", "非管理员，无权访问");
      }
    } else if (is_numeric($Controller->Admin) || is_string($Controller->Admin)) {
      if ((int)$Member['adminid'] !== (int)$Controller->Admin) {
        $RR->error(403, "DiscuzXAdminAuth:403004", "无权访问", "非管理员，无权访问");
      }
    }
    return $RR;
  }
  /**
   * 验证视图控制器权限
   *
   * @param DiscuzXController $Controller 控制器
   * @return ReturnResult
   */
  public function verifyViewControllerAuth(DiscuzXController $Controller)
  {
    $RR = new ReturnResult(true);
    $Member = getglobal("member");
    if ((int)$Member['uid'] === 0) {
      $RR->error(401, "DiscuzXAuth:401001", "请登录后重试", "空Token");
    }
    if (is_array($Controller->Auth)) {
      if (!in_array($Member['groupid'], $Controller->Auth)) {
        $RR->error(403, "DiscuzXAuth:403001", "无权访问", "非管理员，无权访问");
      }
    } else if (is_numeric($Controller->Auth) || is_string($Controller->Auth)) {
      if ((int)$Member['groupid'] !== (int)$Controller->Auth) {
        $RR->error(403, "DiscuzXAuth:403003", "无权访问", "非管理员，无权访问");
      }
    }
    return $RR;
  }
  public function verify(Request $request, $controller, $viewVerifyType, $strongCheck = false)
  {
    //* 如果是同源，那么来源就是视图页面发起的ajax请求，无需token，用verifyViewControllerAdmin和verifyViewControllerAuth去验证
    if ($viewVerifyType === "admin") {
      return $this->verifyViewControllerAdmin($controller);
    } else {
      return $this->verifyViewControllerAuth($controller);
    }
  }
  /**
   * 中间件处理
   *
   * @param \Closure $next
   * @param Request $request
   * @param DiscuzXController $Controller
   * @return Response
   */
  public function handle(\Closure $next, Request $request, $Controller = null)
  {
    if (!($Controller instanceof DiscuzXController)) {
      $Verified = $this->verifyToken($request, false);
      if ($Verified->error) {
        return $Verified;
      }
      return $next();
    }
    if (!function_exists("getglobal")) {
      function getglobal($key)
      {
        return null;
      }
    }

    $SameOrigin = $this->sameOrigin($request);
    if (!$SameOrigin) {
      $Verified = $this->verifyToken($request);
      if ($Verified->error) {
        return $Verified;
      }
    }

    $memberInfo = null;
    if ($request->ajax() && !$SameOrigin) {
      $Auth = Store::getApp("auth");
      if ($Auth && isset($Auth['userId'])) {
        $memberInfo = DiscuzXMember::get($Auth['userId']);
        include_once libfile("function/member");
        \setloginstatus($memberInfo, 0);
      } else {
        $memberInfo = DiscuzXMember::get(0);
      }
      Store::setApp([
        "member" => $memberInfo,
      ]);
    } else {
      $memberInfo = DiscuzXMember::get(getglobal("uid"));
      Store::setApp([
        "member" => $memberInfo
      ]);
    }

    $adminChecked = false;
    $authChecked = false;
    $verified = null;
    if ($Controller->Admin) {
      $adminChecked = true;
      $verified = $this->verify($request, $Controller, "admin", true);
      if (!$verified->error) {
        $verified = $Controller->verifyAdmin();
      }
    }
    if (!$adminChecked && $Controller->Auth) {
      $authChecked = true;
      $verified = $this->verify($request, $Controller, "auth", true);
      if (!$verified->error) {
        $verified = $Controller->verifyAuth();
      }
    }
    if (!$authChecked && !$adminChecked && !$verified) {
      $verified = $this->verifyToken($request, false);
    }
    if ($verified->error) {
      return $verified;
    }

    return $next();
  }
}
