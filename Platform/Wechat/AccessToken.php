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
}
