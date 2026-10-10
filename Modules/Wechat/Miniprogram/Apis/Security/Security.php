<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis\Security;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

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
 *
 * 说明：旧类 {@see \kernel\Platform\Wechat\Miniprogram\Apis\SecretCenter} 中已实现上述接口，
 * 二者功能重叠，建议后续统一到本聚合类下。
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
