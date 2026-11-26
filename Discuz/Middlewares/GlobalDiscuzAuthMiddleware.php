<?php

namespace kernel\Discuz\Middlewares;

use kernel\Modules\Auth\Auth;
use kernel\Foundation\HTTP\Cors;
use kernel\Foundation\HTTP\Response;
use kernel\Support\Result;
use kernel\Middlewares\GlobalAuthMiddleware;
use kernel\Discuz\Foundation\DiscuzController;
use kernel\Discuz\Member\DiscuzMember;

/**
 * Discuz 环境下的全局认证中间件
 *
 * 在 {@see GlobalAuthMiddleware} 的基础上追加两件事：
 * - 同源视图请求（页面发起的 ajax）不用 token，改按 Discuz 的 uid / adminid / groupid 判定；
 * - 校验通过后用 Discuz 的会员信息补齐上下文（{@see \kernel\Modules\Auth\Auth}）。
 *
 * 登录态仍然只写上下文，不缓存到任何静态变量。
 */
class GlobalDiscuzAuthMiddleware extends GlobalAuthMiddleware
{
  /**
   * 控制器
   * @var DiscuzController
   */
  protected $controller = null;
  public function verifyViewControllerAdmin()
  {
    $RR = new Result(true);
    $userId = Auth::userId() ? (int) Auth::userId() : 0;
    $adminId = $userId ? (int) getglobal("member/adminid") : 0;

    if (is_bool($this->controller->admin) && $this->controller->admin) {
      if ($userId === 0) {
        return $RR->error(401, "DiscuzAdminAuth:401001", "请登录后重试", "未登录，缺少Token（Admin）");
      }
      if ($adminId === 0) {
        return $RR->error(401, "DiscuzAdminAuth:401002", "抱歉，您所在的用户组无法访问该资源", "非管理员，无权访问");
      }
    }
    if ($adminId !== 1) {
      if (is_array($this->controller->admin)) {
        if (!in_array($adminId, $this->controller->admin)) {
          return $RR->error(403, "DiscuzAdminAuth:403001", "抱歉，您所在的用户组无法访问该资源", "非管理员，无权访问");
        }
      } else if (is_numeric($this->controller->admin) || is_string($this->controller->admin)) {
        if ($adminId !== (int) $this->controller->admin) {
          return $RR->error(403, "DiscuzAdminAuth:403002", "抱歉，您所在的用户组无法访问该资源", "非管理员，无权访问");
        }
      }
    }

    return $RR;
  }
  public function verifyViewControllerAuth()
  {
    $RR = new Result(true);
    $userId = Auth::userId() ? (int) Auth::userId() : 0;
    $groupId = $userId ? (int) getglobal("member/groupid") : 0;

    if (is_bool($this->controller->auth) && $this->controller->auth && $userId === 0) {
      return $RR->error(401, "DiscuzAuth:401001", "请登录后重试", "未登录，缺少Token（Auth）");
    }

    if (is_array($this->controller->auth)) {
      if (!in_array($groupId, $this->controller->auth)) {
        return $RR->error(403, "DiscuzAuth:403001", "抱歉，您所在的用户组无法访问该资源", "不在可访问用户范围（Auth1）");
      }
    } else if (is_numeric($this->controller->auth) || is_string($this->controller->auth)) {
      if ($groupId !== (int) $this->controller->auth) {
        return $RR->error(403, "DiscuzAuth:403002", "抱歉，您所在的用户组无法访问该资源", "不在可访问用户范围（Auth2）");
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
   * 用会员信息补齐登录态
   *
   * @return Result 封禁用户返回 403，其余情况返回成功（删除/改密后由后续 auth 校验落到 401）
   */
  private function login()
  {
    $RR = new Result(true);
    $userId = Auth::userId();
    if (!$userId)
      return $RR;

    $memberInfo = DiscuzMember::get($userId);
    if (!$memberInfo) {
      //* 用户已删除：吊销该用户全部 token，并清空本进程登录态
      Auth::deleteTokensByUser($userId);
      $this->clearAuth();
      return $RR;
    }

    //* 密码指纹：密码已变更则全端下线
    $tokenData = Auth::tokenData();
    $storedCredential = is_array($tokenData) ? ($tokenData['credential'] ?? null) : null;
    if ($storedCredential !== null) {
      $currentCredential = hash("sha256", (string) $memberInfo['password']);
      if (!hash_equals((string) $storedCredential, $currentCredential)) {
        Auth::deleteTokensByUser($userId);
        $this->clearAuth();
        return $RR;
      }
    }

    //* 严格封禁：status=-1 / groupid 4|5 / allowvisit=0
    $group = DiscuzMember::group((int) $memberInfo['groupid']);
    $isBanned = (int) $memberInfo['status'] === -1
      || in_array((int) $memberInfo['groupid'], [4, 5], true)
      || !$group
      || empty($group['allowvisit']);
    if ($isBanned) {
      Auth::deleteTokensByUser($userId);
      $this->clearAuth();
      return $RR->error(403, "DiscuzAuth:403003", "账号已被封禁，请联系管理员", "用户被封禁");
    }

    include_once libfile("function/member");
    \setloginstatus($memberInfo, 1296000);
    Auth::setLogged(true)->setUser($memberInfo);

    return $RR;
  }
  /**
   * 中间件处理
   *
   * @param \Closure $next
   * @return Response
   */
  public function handle(\Closure $next)
  {
    $isWrite = !in_array(strtoupper($this->request->method()), ["GET", "HEAD", "OPTIONS"], true);
    $isDiscuzController = $this->controller instanceof DiscuzController;
    $hasToken = $this->request->header->has("Authorization");

    //* 用户侧写操作强制 Token；后台写操作有 Token 时也走 Token
    $requiresToken = $isWrite
      && $isDiscuzController
      && ($this->controller->auth || ($this->controller->admin && $hasToken));

    if ($requiresToken) {
      $verified = $this->verifyToken(true);
      if ($verified->error) {
        return $verified;
      }
      $login = $this->login();
      if ($login->error) {
        return $login;
      }
    } else {
      $sameOrigin = Cors::isSameOrigin();

      if (getglobal("uid") && ($sameOrigin || !Cors::requestOrigin())) {
        Auth::setUserId(getglobal("uid"));
        $login = $this->login();
        if ($login->error) {
          return $login;
        }
      }

      if (!Auth::logged()) {
        $verified = $this->verifyToken(false);
        if ($verified->error) {
          return $verified;
        }
        $login = $this->login();
        if ($login->error) {
          return $login;
        }

        if (!$isDiscuzController) {
          return $this->applyAuthHeader($next());
        }
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

    if ($verified && $verified->error) {
      return $verified;
    }

    //* cookie 认证的写请求需要 formhash；token 认证不需要
    $needsCsrf = $isWrite
      && $isDiscuzController
      && !$this->tokenVerified
      && Auth::logged()
      && !$this->controller->csrfExempt
      && ($this->controller->admin || $this->controller->auth || $this->controller->csrfProtected);
    if ($needsCsrf) {
      $this->controller->verifyFormhash(true);
    }

    return $this->applyAuthHeader($next());
  }
}
