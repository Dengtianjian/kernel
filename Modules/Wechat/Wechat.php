<?php

namespace kernel\Platform\Wechat;

use DB;
use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 微信平台基础类
 *
 * 封装对微信开放平台 API（https://api.weixin.qq.com）的 HTTP 请求，
 * 提供 access_token 注入、GET/POST 请求发送以及 JSON 响应解析等通用能力。
 * 各业务模块（公众号、小程序、支付等）在本类基础上扩展具体接口。
 */
class Wechat extends AbilityBaseObject
{
  protected $appId = null;
  protected $appSecret = null;
  protected $accessToken = null;
  protected $apiUrl = "https://api.weixin.qq.com";
  /**
   * CURL 客户端实例，用于发起 HTTP 请求
   *
   * @var Curl
   */
  protected $CURL = null;
  /**
   * 初始化微信基础类
   *
   * @param string|null $accessToken 接口调用凭证 access_token
   * @param string|null $appId        第三方平台 AppId（公众号/小程序等）
   * @param string|null $secret       第三方平台 AppSecret
   */
  function __construct($accessToken = null, $appId = null, $secret = null)
  {
    $this->appId = $appId;
    $this->appSecret = $secret;
    $this->accessToken = $accessToken;
    $this->CURL = new Curl();
  }
  /**
   * 获取平台标识名
   *
   * @return string 固定返回 "wechat"
   */
  public function name()
  {
    return "wechat";
  }
  /**
   * 读写一体：获取或设置 access_token
   *
   * 传入参数时为设置，并返回当前实例以支持链式调用；
   * 不传参数时返回当前已设置的 access_token。
   *
   * @param string|null $data 待设置的 access_token
   * @return static|string|null
   */
  public function accessToken($data = null)
  {
    if (func_num_args()) {
      $this->accessToken = $data;
      return $this;
    }

    return $this->accessToken;
  }
  /**
   * 读写一体：获取或设置 AppId
   *
   * 传入参数时为设置，并返回当前实例以支持链式调用；
   * 不传参数时返回当前已设置的 AppId。
   *
   * @param string|null $data 待设置的 AppId
   * @return static|string|null
   */
  public function appId($data = null)
  {
    if (func_num_args()) {
      $this->appId = $data;
      return $this;
    }

    return $this->appId;
  }
  /**
   * 读写一体：获取或设置 AppSecret
   *
   * 传入参数时为设置，并返回当前实例以支持链式调用；
   * 不传参数时返回当前已设置的 AppSecret。
   *
   * @param string|null $data 待设置的 AppSecret
   * @return static|string|null
   */
  public function appSecret($data = null)
  {
    if (func_num_args()) {
      $this->appSecret = $data;
      return $this;
    }

    return $this->appSecret;
  }
  /**
   * 发送 GET 请求到微信 API
   *
   * 当 $withAccessToken 为 true 时，会自动在 query 参数中附加 access_token。
   *
   * @param string $uri             业务接口路径，如 "cgi-bin/token"
   * @param array  $query           附加的 query 参数
   * @param bool   $withAccessToken 是否自动附加 access_token（默认 true）
   * @return Curl 返回 Curl 请求实例，可继续调用 getData()/getJSONData() 获取响应
   */
  function get($uri, $query = [], $withAccessToken = true)
  {
    if ($withAccessToken) {
      if (is_array($query)) {
        $query['access_token'] = $this->accessToken;
      } else {
        $query = [
          'access_token' => $this->accessToken
        ];
      }
    }
    $request = $this->CURL->url($this->apiUrl . "/" . $uri, $query);
    return $request->https(false)->get();
  }
  /**
   * 发送 POST 请求到微信 API
   *
   * 当 $withAccessToken 为 true 时，会自动在 query 参数中附加 access_token。
   *
   * @param string $uri             业务接口路径，如 "cgi-bin/message/custom/send"
   * @param array  $body            请求体数据（作为 POST 数据发送）
   * @param array  $query           附加的 query 参数
   * @param bool   $withAccessToken 是否自动附加 access_token（默认 true）
   * @return Curl 返回 Curl 请求实例，可继续调用 getData()/getJSONData() 获取响应
   */
  function post($uri, $body = [], $query = [], $withAccessToken = true)
  {
    if ($withAccessToken) {
      if (is_array($query)) {
        $query['access_token'] = $this->accessToken;
      } else {
        $query = [
          'access_token' => $this->accessToken
        ];
      }
    }
    $request = $this->CURL->url($this->apiUrl . "/" . $uri, $query);
    return $request->https(false)->post($body);
  }
  /**
   * 获取并解析最近一次响应为 JSON 数组
   *
   * 将 Curl 客户端返回的响应体按 JSON 解码为关联数组。
   *
   * @return array|null 解析后的关联数组；响应非 JSON 或解析失败时返回 null
   */
  public function getJSONData()
  {
    return json_decode($this->CURL->getData(), true);
  }
}
