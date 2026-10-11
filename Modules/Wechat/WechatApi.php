<?php

namespace kernel\Platform\Wechat;

use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 微信 API 请求客户端
 *
 * 以微信账号（WechatAccount）为身份来源，封装对微信开放平台 API
 * （https://api.weixin.qq.com）的 HTTP 请求：统一拼接接口地址、自动获取并注入
 * access_token、发送 GET/POST 请求、解析 JSON 响应。
 * 各业务模块（公众号、小程序、支付等）可在本类基础上扩展具体接口。
 *
 * access_token 的获取、缓存与刷新由 {@see WechatAccessToken} 负责，
 * 本类仅对外暴露 accessToken() 一个入口，因此 WechatAccount 与 WechatApi
 * 取到的始终是同一份 token（进程内共享缓存）。
 *
 * 使用示例：
 *
 * ```php
 * $api = new WechatApi(new WechatAccount($appId, $appSecret));
 * access_token 为空或过期时自动获取并缓存
 * $user = $api->get("cgi-bin/user/info", ["openid" => $openId])->getData();
 * ```
 */
class WechatApi extends AbilityBaseObject
{
  /**
   * 微信开放平台接口地址前缀
   *
   * @var string
   */
  protected $apiUrl = "https://api.weixin.qq.com";
  /**
   * CURL 客户端实例，用于发起 HTTP 请求
   *
   * 实例在整个对象生命周期内复用，每次请求时重新设置 URL 与请求体。
   *
   * @var Curl
   */
  protected $CURL = null;
  /**
   * 微信账号，提供 AppId/AppSecret 等身份信息
   *
   * @var WechatAccount
   */
  protected $account = null;
  /**
   * access_token 提供者（惰性实例化，携带本账号的 AppId/AppSecret）
   *
   * @var WechatAccessToken|null
   */
  protected $accessTokenProvider = null;
  /**
   * access_token 提前刷新时间（秒）
   *
   * 保留该常量以兼容既有引用，实际以 {@see WechatAccessToken::REFRESH_AHEAD} 为准。
   */
  const ACCESS_TOKEN_REFRESH_AHEAD = WechatAccessToken::REFRESH_AHEAD;
  /**
   * 初始化微信 API 客户端
   *
   * @param WechatAccount $account 微信账号，提供 AppId/AppSecret 等身份信息
   */
  function __construct($account)
  {
    $this->account = $account;
    $this->CURL = new Curl();
  }
  /**
   * 获取当前微信账号
   *
   * @return WechatAccount 当前绑定的微信账号
   */
  public function account()
  {
    return $this->account;
  }
  /**
   * 获取 access_token
   *
   * 委托 {@see WechatAccessToken}：实例内缓存 → 进程内共享缓存 →
   * wechat_accounts 表 → 回源微信（稳定版接口），并自动处理过期与写回。
   *
   * @param bool $forceRefresh 是否强制刷新（默认 false，命中有效缓存时直接返回）
   * @return string|null 当前有效的 access_token；未配置 AppId/AppSecret 或获取失败时返回 null
   */
  public function accessToken($forceRefresh = false)
  {
    if ($this->accessTokenProvider === null) {
      $appId = $this->account ? $this->account->appId() : null;
      $appSecret = $this->account ? $this->account->appSecret() : null;
      $this->accessTokenProvider = new WechatAccessToken($appId, $appSecret);
    }

    if ($forceRefresh) {
      $data = $this->accessTokenProvider->refresh();

      return $data['access_token'] ?? null;
    }

    return $this->accessTokenProvider->token();
  }
  /**
   * 发送 GET 请求到微信 API
   *
   * 当 $withAccessToken 为 true 时，会自动附加当前有效的 access_token
   * （缓存为空或已过期时会先自动获取）。
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
        $query['access_token'] = $this->accessToken();
      } else {
        $query = [
          'access_token' => $this->accessToken()
        ];
      }
    }
    $request = $this->CURL->url($this->apiUrl . "/" . $uri, $query);
    // 清空可能由上一次 POST 请求残留的请求体，避免被拼接到 GET 的 query 上
    return $request->data([])->https(false)->get();
  }
  /**
   * 发送 POST 请求到微信 API
   *
   * 当 $withAccessToken 为 true 时，会自动附加当前有效的 access_token
   * （缓存为空或已过期时会先自动获取）。
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
        $query['access_token'] = $this->accessToken();
      } else {
        $query = [
          'access_token' => $this->accessToken()
        ];
      }
    }
    $request = $this->CURL->url($this->apiUrl . "/" . $uri, $query);
    return $request->https(false)->post($body);
  }
  /**
   * 获取并解析最近一次响应为数组
   *
   * Curl 客户端在响应为 JSON 时已自动解析为关联数组，此时直接返回；否则再尝试
   * 按 JSON 解码。
   *
   * @return array|null 解析后的关联数组；响应非 JSON 或解析失败时返回 null
   */
  public function getJSONData()
  {
    $data = $this->CURL->getData();
    if (is_array($data)) {
      return $data;
    }

    return json_decode($data, true);
  }
}
