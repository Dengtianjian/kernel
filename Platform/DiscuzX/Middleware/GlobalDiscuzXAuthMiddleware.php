<?php

namespace kernel\Platform\DiscuzX\Middleware;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Store;
use kernel\Middleware\GlobalAuthMiddleware;
use kernel\Platform\DiscuzX\Foundation\DiscuzXController;
use kernel\Platform\DiscuzX\Member\DiscuzXMember;
use kernel\Platform\DiscuzX\Model\DiscuzXLoginsModel;

class GlobalDiscuzXAuthMiddleware extends GlobalAuthMiddleware
{
  public function __construct(Request $request, $controller)
  {
    parent::__construct($request, $controller);
    $this->LoginsModel = DiscuzXLoginsModel::class;
  }
  /**
   * 验证视图控制器管理员权限
   *
   * @return ReturnResult
   */
  public function verifyViewControllerAdmin()
  {
    $RR = new ReturnResult(true);
    $Member = getglobal("member");
    if (is_bool($this->controller->Admin)) {
      if ((int)$Member['uid'] === 0) {
        $RR->error(401, "DiscuzXAdminAuth:401001", "请登录后重试", "未登录，缺少Token（Admin）");
      }
      if ((int)$Member['adminid'] === 0) {
        $RR->error(401, "DiscuzXAdminAuth:401002", "抱歉，您所在的用户组无法访问该资源", "非管理员，无权访问");
      }
    }
    if ((int)$Member['adminid'] !== 1) {
      if (is_array($this->controller->Admin)) {
        if (!in_array($Member['adminid'], $this->controller->Admin)) {
          $RR->error(403, "DiscuzXAdminAuth:403001", "抱歉，您所在的用户组无法访问该资源", "非管理员，无权访问");
        }
      } else if (is_numeric($this->controller->Admin) || is_string($this->controller->Admin)) {
        if ((int)$Member['adminid'] !== (int)$this->controller->Admin) {
          $RR->error(403, "DiscuzXAdminAuth:403002", "抱歉，您所在的用户组无法访问该资源", "非管理员，无权访问");
        }
      }
    }

    return $RR;
  }
  /**
   * 验证视图控制器权限
   *
   * @return ReturnResult
   */
  public function verifyViewControllerAuth()
  {
    $RR = new ReturnResult(true);
    $Member = getglobal("member");
    if (is_bool($this->controller->Auth)) {
      if ((int)$Member['uid'] === 0) {
        $RR->error(401, "DiscuzXAuth:401001", "请登录后重试", "未登录，缺少Token（Auth）");
      }
    }
    if (is_array($this->controller->Auth)) {
      if (!in_array($Member['groupid'], $this->controller->Auth)) {
        $RR->error(403, "DiscuzXAuth:403001", "抱歉，您所在的用户组无法访问该资源", "不在可访问用户范围（Auth1）");
      }
    } else if (is_numeric($this->controller->Auth) || is_string($this->controller->Auth)) {
      if ((int)$Member['groupid'] !== (int)$this->controller->Auth) {
        $RR->error(403, "DiscuzXAuth:403002", "抱歉，您所在的用户组无法访问该资源", "不在可访问用户范围（Auth2）");
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
  /**
   * 中间件处理
   *
   * @param \Closure $next
   * @return Response
   */
  public function handle(\Closure $next)
  {
    if (!($this->controller instanceof DiscuzXController)) {
      $Verified = $this->verifyToken(false);
      if ($Verified->error) {
        return $Verified;
      }
      return $next();
    }

    $SameOrigin = $this->sameOrigin();
    if (!$SameOrigin) {
      $Verified = $this->verifyToken(false);
      if ($Verified->error) {
        return $Verified;
      }
    }

    $memberInfo = null;
    if ($this->request->ajax() && !$SameOrigin) {
      $Auth = Store::getApp("auth");
      if ($Auth && isset($Auth['userId'])) {
        $memberInfo = DiscuzXMember::get($Auth['userId']);
        include_once libfile("function/member");
        \setloginstatus($memberInfo, 1296000);
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
    if ($this->controller->Admin) {
      $adminChecked = true;
      $verified = $this->verify("admin");
      if (!$verified->error) {
        $verified = $this->controller->verifyAdmin();
        if ($verified instanceof Response && $verified->error) {
          return $verified;
        }
      }
    }
    if (!$adminChecked && $this->controller->Auth) {
      $authChecked = true;
      $verified = $this->verify("auth");
      if (!$verified->error) {
        $verified = $this->controller->verifyAuth();
        if ($verified instanceof Response && $verified->error) {
          return $verified;
        }
      }
    }
    if (!$authChecked && !$adminChecked && !$verified) {
      $verified = $this->verifyToken(false);
    }
    if ($verified->error) {
      return $verified;
    }

    return $next();
  }
}
