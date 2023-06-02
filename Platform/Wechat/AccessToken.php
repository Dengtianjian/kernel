<?php

namespace kernel\Platform\Wechat;

use kernel\Foundation\HTTP\Curl;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

/**
 * AccessToken类
 */
class AccessToken extends Wechat
{
  /**
   * 获取accessToken
   * @link https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/mp-access-token/getAccessToken.html
   *
   * @param string $appId appId
   * @param string $secret secret
   * @return array
   */
  function getAccessToken()
  {
    $CURL = new Curl();
    $request = $CURL->url("https://api.weixin.qq.com/cgi-bin/token", [
      "grant_type" => "client_credential",
      "appid" => $this->AppId,
      "secret" => $this->AppSecret
    ])->https(false);
    return $request->get()->getData();
  }
  /**
   * 获取稳定版接口调用凭证
   * @link https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/mp-access-token/getStableAccessToken.html
   *
   * @return array
   */
  function getStableAccessToken()
  {
    return $this->post("cgi-bin/stable_token", [
      "grant_type" => "client_credential",
      "appid" => $this->AppId,
      "secret" => $this->AppSecret
    ])->getData();
  }
}
