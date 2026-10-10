<?php

namespace kernel\Platform\Wechat\Miniprogram\Apis\Security;

use kernel\Platform\Wechat\Miniprogram\WechatMiniProgramApi;

/**
 * 安全风控
 *
 * 对应微信「小程序安全 → 安全风控」分类。
 */
class RiskControl extends WechatMiniProgramApi
{
  /**
   * 获取用户安全等级（POST /wxa/getuserriskrank）
   * @link https://developers.weixin.qq.com/miniprogram/dev/server/API/sec-center/safety-control-capability/api_getuserriskrank.html
   *
   * @param string $openid 用户的 openid
   * @param int $scene 场景值：0 注册；1 营销作弊
   * @param string|null $clientIp 用户访问来源的 IP，留空时尝试取 Discuz 的 $_G['client_ip']
   * @param bool $isTest 是否测试（测试时不消耗调用次数）
   * @return array 微信响应数组，成功时含 risk_rank（0 最安全，4 最高风险）
   */
  function getUserRiskRank($openid, $scene = 1, $clientIp = null, $isTest = false)
  {
    if ($clientIp === null) {
      $clientIp = $GLOBALS['_G']['client_ip'] ?? null;
    }

    $body = [
      "appid" => $this->account->appId(),
      "openid" => $openid,
      "scene" => $scene,
      "client_ip" => $clientIp,
      "is_test" => $isTest
    ];

    return $this->post("wxa/getuserriskrank", $body)->getData();
  }
}
