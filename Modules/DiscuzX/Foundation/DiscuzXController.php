<?php

namespace kernel\Modules\DiscuzX\Foundation;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Exception\Error;

class DiscuzXController extends AuthController
{
  /**
   * 是否需要校验 Discuz 的 formhash（防 CSRF），由子类覆盖为 true 开启
   *
   * @var bool
   */
  public $forumhash = false;

  /**
   * 校验 Discuz 的 formhash
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
