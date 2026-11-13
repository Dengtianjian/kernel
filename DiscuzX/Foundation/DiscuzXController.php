<?php

namespace kernel\DiscuzX\Foundation;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Exception\Error;

/**
 * DiscuzX 适配层基础控制器
 *
 * 本类是 DiscuzX 适配层的控制器底座，**不承载业务逻辑**，只沉淀通用能力：
 * - Token 认证：继承自 {@see AuthController} 的 `$admin` / `$auth` 开关与校验分发，
 *   接口请求由认证中间件按 Token 鉴权（`AuthModule` 凭证体系），这是本框架的
 *   主要访问控制手段；
 * - formhash 校验：面向「需要 Discuz 会话表单语义」的业务控制器的**可选开关**，
 *   见 {@see $forumhash} 与 {@see verifyFormhash()}。
 */
class DiscuzXController extends AuthController
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
   * 校验 Discuz 的 formhash
   *
   * 仅当业务子类把 {@see $forumhash} 覆盖为 true 时才执行，否则直接跳过（默认）。
   *
   * 期望值取 Discuz 当前会话的 formhash（`$_G['formhash']`，兼容已定义的 FORMHASH 常量），
   * 提交值同时从 query 与 body 中取。
   *
   * @return void
   * @throws Error 校验不通过时抛出 403
   */
  final public function verifyFormhash()
  {
    if (!$this->forumhash) {
      return;
    }

    $expected = defined("FORMHASH") ? \FORMHASH : \getglobal("formhash");
    $submitted = $this->request->query->get("formhash") ?: $this->request->body->get("formhash");

    if (empty($expected) || $submitted != $expected) {
      throw new Error("非法访问", 403, 403, "formhash");
    }
  }
}
