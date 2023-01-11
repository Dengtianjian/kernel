<?php

namespace gstudio_kernel\Platform\Wechat;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Network\Curl;

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
