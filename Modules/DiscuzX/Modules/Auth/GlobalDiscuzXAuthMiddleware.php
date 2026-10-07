<?php

namespace kernel\Modules\DiscuzX\Modules\Auth;

use kernel\Foundation\HTTP\Cors;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\Result;
use kernel\Modules\Auth\Auth;
use kernel\Modules\Auth\GlobalAuthMiddleware;
use kernel\Modules\DiscuzX\Foundation\DiscuzXController;
use kernel\Modules\DiscuzX\Member\DiscuzXMember;

class GlobalDiscuzXAuthMiddleware extends GlobalAuthMiddleware
{
  /**
   * 控制器
   * @var DiscuzXController
   */
  protected $controller = null;
  public function verifyViewControllerAdmin()
  {
    $RR = new Result(true);
    $userId = Auth::userId() ? (int)Auth::userId() : 0;
    $adminId =  $userId ? (int)getglobal("member/adminid") : 0;

    if (is_bool($this->controller->admin) && $this->controller->admin) {
      if ($userId === 0) {
        return $RR->error(401, "DiscuzXAdminAuth:401001", "请登录后重试", "未登录，缺少Token（Admin）");
      }
      if ($adminId === 0) {
        return $RR->error(401, "DiscuzXAdminAuth:401002", "抱歉，您所在的用户组无法访问该资源", "非管理员，无权访问");
      }
    }
    if ($adminId !== 1) {
      if (is_array($this->controller->admin)) {
        if (!in_array($adminId, $this->controller->admin)) {
          return $RR->error(403, "DiscuzXAdminAuth:403001", "抱歉，您所在的用户组无法访问该资源", "非管理员，无权访问");
        }
      } else if (is_numeric($this->controller->admin) || is_string($this->controller->admin)) {
        if ($adminId !== (int)$this->controller->admin) {
          return $RR->error(403, "DiscuzXAdminAuth:403002", "抱歉，您所在的用户组无法访问该资源", "非管理员，无权访问");
        }
      }
    }

    return $RR;
  }
  public function verifyViewControllerAuth()
  {
    $RR = new Result(true);
    $userId = Auth::userId() ? (int)Auth::userId() : 0;
    $groupId = $userId ? (int)getglobal("member/groupid") : 0;

    if (is_bool($this->controller->auth) && $this->controller->auth && $userId === 0) {
      return $RR->error(401, "DiscuzXAuth:401001", "请登录后重试", "未登录，缺少Token（Auth）");
    }

    if (is_array($this->controller->auth)) {
      if (!in_array($groupId, $this->controller->auth)) {
        return $RR->error(403, "DiscuzXAuth:403001", "抱歉，您所在的用户组无法访问该资源", "不在可访问用户范围（Auth1）");
      }
    } else if (is_numeric($this->controller->auth) || is_string($this->controller->auth)) {
      if ($groupId !== (int)$this->controller->auth) {
        return $RR->error(403, "DiscuzXAuth:403002", "抱歉，您所在的用户组无法访问该资源", "不在可访问用户范围（Auth2）");
      }
    }
    return $RR;
  }
  public function verify($viewVerifyType)
  {
    //* 如果是同源，那么来源就是视图页面发起的ajax请求，无需token，用verifyViewControllerAdmin和verifyViewControllerAuth去验证
    if ($viewVerifyType === "admin") {
      return $this->verifyViewControllerAdmin();
    } else {
      return $this->verifyViewControllerAuth();
    }
  }
  private function login()
  {
    $userId = Auth::userId();
    if (!$userId) return;

    $memberInfo = null;
    if ($userId) {
      $memberInfo = DiscuzXMember::get($userId);
      include_once libfile("function/member");
      \setloginstatus($memberInfo, 1296000);
    } else {
      $memberInfo = null;
    }

    if ($memberInfo) {
      Auth::logged(true);
      Auth::user($memberInfo);
    }
  }
  /**
   * 中间件处理
   *
   * @param \Closure $next
   * @return Response
   */
  public function handle(\Closure $next)
  {
    $sameOrigin = Cors::isSameOrigin();

    if ($sameOrigin && getglobal("uid")) {
      Auth::userId(getglobal("uid"));
      $this->login();
    }

    if (!Auth::logged()) {
      $verified = $this->verifyToken(false);
      if ($verified->error) {
        return $verified;
      }
      $this->login();

      if (!($this->controller instanceof DiscuzXController)) {
        return $next();
      }
    }

    $adminChecked = false;
    $authChecked = false;
    $verified = null;
    if ($this->controller->admin) {
      $adminChecked = true;
      $verified = $this->verify("admin");
      if (!$verified->error) {
        $verified = $this->controller->verifyAdmin();
        if ($verified instanceof Response && $verified->error) {
          return $verified;
        }
      }
    }
    if (!$adminChecked && $this->controller->auth) {
      $authChecked = true;
      $verified = $this->verify("auth");
      if (!$verified->error) {
        $verified = $this->controller->verifyAuth();
        if ($verified instanceof Response && $verified->error) {
          return $verified;
        }
      }
    }

    if ($verified->error) {
      return $verified;
    }

    return $next();
  }
}
