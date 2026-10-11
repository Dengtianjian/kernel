<?php

namespace kernel\Modules\Wechat\Miniprogram\Apis\Security;

use kernel\Modules\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 小程序安全（聚合类）
 *
 * 聚合微信「小程序安全」分类下的接口：
 * - 内容安全：{@see ContentSecurity}
 * - 安全风控：{@see RiskControl}
 *
 * 使用示例：
 *
 * ```php
 * $mp->security()->contentSecurity()->msgSecCheck($content, $openid, 2);
 * $mp->security()->riskControl()->getUserRiskRank($openid, 1);
 * ```
 */
class Security extends WechatMiniProgramApi
{
  /**
   * 内容安全相关接口
   *
   * @return ContentSecurity
   */
  public function contentSecurity()
  {
    return new ContentSecurity($this->account);
  }
  /**
   * 安全风控相关接口
   *
   * @return RiskControl
   */
  public function riskControl()
  {
    return new RiskControl($this->account);
  }
}
