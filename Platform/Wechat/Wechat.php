<?php

namespace gstudio_kernel\Platform\Wechat;

use DB;
use gstudio_kernel\Foundation\Network\Curl;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class Wechat
{
  protected $AppId = null;
  protected $AppSecret = null;
  protected $AccessToken = null;
  protected $ApiUrl = "https://api.weixin.qq.com";
  /**
   * 微信小程序基类
   *
   * @param string $accessToken AccessToken，访问TOKEN
   * @param string $appId AppId
   * @param string $secret AppSecret
   */
  function __construct($accessToken = null, $appId = null, $secret = null)
  {
    $this->AppId = $appId;
    $this->AppSecret = $secret;
    $this->AccessToken = $accessToken;
  }
  /**
   * 设置AccessToken
   *
   * @param string $value AccessToken值
   * @return void
   */
  function setAccessToken($value)
  {
    $this->AccessToken = $value;
  }
  /**
   * 发送GET请求
   *
   * @param string $uri 业务URI
   * @param array $query query参数
   * @param boolean $withAccessToken 是否携带AccessToken
   * @return CURL
   */
  function get($uri, $query = [], $withAccessToken = true)
  {
    if ($withAccessToken) {
      if (is_array($query)) {
        $query['access_token'] = $this->AccessToken;
      } else {
        $query = [
          'access_token' => $this->AccessToken
        ];
      }
    }
    $CURL = new Curl();
    $request = $CURL->url("https://api.weixin.qq.com/" . $uri, $query);
    return $request->https(false)->get();
  }
  /**
   * 发送POST请求
   *
   * @param string $uri 业务URI
   * @param array $body 请求体数据
   * @param array $query query参数
   * @param boolean $withAccessToken 是否携带AccessToken
   * @return CURL
   */
  function post($uri, $body = [], $query = [], $withAccessToken = true)
  {
    if ($withAccessToken) {
      if (is_array($query)) {
        $query['access_token'] = $this->AccessToken;
      } else {
        $query = [
          'access_token' => $this->AccessToken
        ];
      }
    }
    $CURL = new Curl();
    $request = $CURL->url("https://api.weixin.qq.com/" . $uri, $query);
    $request->data($body);
    return $request->https(false)->post();
  }
}
