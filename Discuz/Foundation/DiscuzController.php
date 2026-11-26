<?php

namespace kernel\Discuz\Foundation;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Exception\Error;

/**
 * Discuz 适配层基础控制器
 *
 * 本类是 Discuz 适配层的控制器底座，**不承载业务逻辑**，只沉淀通用能力：
 * - Token 认证：继承自 {@see AuthController} 的 `$admin` / `$auth` 开关与校验分发，
 *   接口请求由认证中间件按 Token 鉴权（`AuthModule` 凭证体系），这是本插件的
 *   主要访问控制手段；
 * - formhash 校验：面向「需要 Discuz 会话表单语义」的业务控制器的**可选开关**，
 *   见 {@see $forumhash} 与 {@see verifyFormhash()}。
 */
class DiscuzController extends AuthController
{
  /**
   * 是否校验 Discuz 的 formhash（防 CSRF），由业务子类覆盖为 true 开启
   *
   * 默认 false 是刻意设计：kernel 面向 API 接口，访问控制由 Token 认证承担
   * （{@see AuthController} 开关 + 认证中间件 Token 校验），formhash 属浏览器
   * 会话表单的 CSRF 机制，对纯 Token 接口没有意义；需要会话表单语义的
   * 业务控制器再按需覆盖为 true，是否开启由业务侧自行决定。
   *
   * @var bool
   */
  public $forumhash = false;

  /**
   * 是否允许 cookie 认证的写请求强制走 formhash
   *
   * 后台控制器由 `$admin` 自动纳入；`$auth=false` 但手动检查用户身份的
   * 写控制器（如文件授权）可显式设为 true。
   *
   * @var bool
   */
  public $csrfProtected = false;

  /**
   * 是否豁免 CSRF（仅登录 / silentLogin 等 bootstrap 接口使用）
   *
   * @var bool
   */
  public $csrfExempt = false;

  /**
   * 校验 Discuz 的 formhash
   *
   * 默认只在 {@see $forumhash} 为 true 时校验；认证中间件可传 `$force=true`
   * 对 cookie 认证的写请求强制执行（token 认证不校验）。
   *
   * 期望值取 Discuz 当前会话的 formhash（`$_G['formhash']`，兼容已定义的 FORMHASH 常量），
   * 提交值支持 `X-CSRF-Token` / `X-Formhash` 请求头，或 query/body 的 `formhash`。
   *
   * @param bool $force 是否无视 `$forumhash` 强制执行
   * @return void
   * @throws Error 校验不通过时抛出 403
   */
  final public function verifyFormhash($force = false)
  {
    if (!$force && !$this->forumhash) {
      return;
    }

    $expected = \getglobal("formhash") ?: (defined("FORMHASH") ? \FORMHASH : "");
    $submitted = $this->request->header->get("X-CSRF-Token")
      ?: $this->request->header->get("X-Formhash")
      ?: $this->request->query->get("formhash")
      ?: $this->request->body->get("formhash");

    if (empty($expected) || !hash_equals((string)$expected, (string)$submitted)) {
      throw new Error("非法访问", 403, 403, "formhash");
    }
  }
}
