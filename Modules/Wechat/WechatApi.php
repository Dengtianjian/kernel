<?php

namespace kernel\Platform\Wechat;

use kernel\Foundation\HTTP\Curl;
use kernel\Foundation\Object\AbilityBaseObject;

/**
 * 微信 API 请求客户端
 *
 * 以微信账号（WechatAccount）为身份来源，封装对微信开放平台 API
 * （https://api.weixin.qq.com）的 HTTP 请求：统一拼接接口地址、自动获取并缓存
 * access_token 并注入请求参数、发送 GET/POST 请求、解析 JSON 响应。
 * 各业务模块（公众号、小程序、支付等）可在本类基础上扩展具体接口。
 *
 * 使用示例：
 *
 * ```php
 * $api = new WechatApi(new WechatAccount($appId, $appSecret));
 * // access_token 为空或过期时自动获取并缓存
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
   * 缓存中的 access_token
   *
   * 为空或已过期时由 accessToken() 自动获取并刷新。
   *
   * @var string|null
   */
  protected $accessToken = null;
  /**
   * access_token 过期时间（Unix 时间戳，秒）
   *
   * 未记录时为 null，此时视为未过期（兼容外部直接注入 access_token 的场景）。
   *
   * @var int|null
   */
  protected $accessTokenExpiresAt = null;
  /**
   * access_token 提前刷新时间（秒）
   *
   * 判定过期时提前该秒数即视为过期，避免临界失效导致请求失败。
   */
  const ACCESS_TOKEN_REFRESH_AHEAD = 300;
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
   * 获取 access_token（只读）
   *
   * 内部缓存的 access_token 为空或已过期时，会自动向微信获取新的 access_token
   * 并写入缓存（同时记录过期时间）；未配置 appId/appSecret 或获取失败时返回 null。
   *
   * @return string|null 当前有效的 access_token
   */
  public function accessToken()
  {
    if ($this->isAccessTokenExpired()) {
      $this->fetchAccessToken();
    }

    return $this->accessToken;
  }
  /**
   * 读写一体：获取或设置 access_token 过期时间
   *
   * 传入参数时为设置，并返回当前实例以支持链式调用；不传参数时返回当前过期时间。
   * 外部注入（如从数据库恢复）access_token 时应一并设置过期时间，否则将被视为未过期。
   *
   * @param int|null $data 待设置的过期时间（Unix 时间戳，秒）
   * @return static|int|null
   */
  public function accessTokenExpiresAt($data = null)
  {
    if (func_num_args()) {
      $this->accessTokenExpiresAt = $data;
      return $this;
    }

    return $this->accessTokenExpiresAt;
  }
  /**
   * 判断 access_token 是否为空或已过期
   *
   * access_token 为空时返回 true；已记录过期时间且（提前 ACCESS_TOKEN_REFRESH_AHEAD 秒）
   * 已到期返回 true；未记录过期时间时视为未过期。
   *
   * @return bool
   */
  public function isAccessTokenExpired()
  {
    if (!$this->accessToken) {
      return true;
    }
    if (!$this->accessTokenExpiresAt) {
      return false;
    }

    return $this->accessTokenExpiresAt - self::ACCESS_TOKEN_REFRESH_AHEAD <= time();
  }
  /**
   * 获取接口调用凭证 access_token（普通版）
   *
   * 调用微信普通 access_token 接口，返回微信原始响应数组（含 access_token、
   * expires_in；失败时为 errcode/errmsg）。
   *
   * 注意：该接口会使同一账号此前获取的 access_token 立即失效，多实例/多进程
   * 场景请优先使用 getStableAccessToken()。本方法仅返回响应，不写入缓存。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Basic_Information/Get_access_token.html
   *
   * @return array|null 微信响应数组；请求失败时返回 null
   */
  public function getAccessToken()
  {
    return $this->get("cgi-bin/token", [
      "grant_type" => "client_credential",
      "appid" => $this->account->appId(),
      "secret" => $this->account->appSecret(),
    ], false)->getData();
  }
  /**
   * 获取稳定版接口调用凭证 access_token
   *
   * 稳定版接口不会影响其他 access_token 的有效期，且支持强制刷新。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Basic_Information/getStableAccessToken.html
   *
   * @param bool $forceRefresh 是否强制刷新（默认 false，由微信按有效期返回）
   * @return array|null 微信响应数组；请求失败时返回 null
   */
  public function getStableAccessToken($forceRefresh = false)
  {
    return $this->post("cgi-bin/stable_token", [
      "grant_type" => "client_credential",
      "appid" => $this->account->appId(),
      "secret" => $this->account->appSecret(),
      "force_refresh" => $forceRefresh,
    ], [], false)->getData();
  }
  /**
   * 刷新 access_token（强制）
   *
   * 以 force_refresh = true 调用稳定版接口，成功后将新的 access_token 与过期时间
   * 写入缓存，后续通过 get()/post() 发起的请求会自动携带。
   *
   * @return array|null 微信响应数组；请求失败时返回 null
   */
  public function refreshAccessToken()
  {
    $data = $this->getStableAccessToken(true);
    $this->saveAccessTokenResponse($data);

    return $data;
  }
  /**
   * 获取 access_token 并写入缓存（内部使用）
   *
   * 由 accessToken() 在缓存为空或已过期时调用，走稳定版接口；账号未配置
   * AppId/AppSecret 时不发起请求。
   *
   * @return array|null 微信响应数组；无法获取时返回 null
   */
  protected function fetchAccessToken()
  {
    if (!$this->account->appId() || !$this->account->appSecret()) {
      return null;
    }

    $data = $this->getStableAccessToken();
    $this->saveAccessTokenResponse($data);

    return $data;
  }
  /**
   * 将 access_token 接口响应写入缓存（内部使用）
   *
   * 响应中不含 access_token（如返回 errcode/errmsg）时不覆盖现有缓存；
   * 响应未带 expires_in 时清空过期时间（视为未过期）。
   *
   * @param array|null $data 微信 access_token 接口响应
   * @return bool 是否写入成功
   */
  protected function saveAccessTokenResponse($data)
  {
    if (!is_array($data) || empty($data['access_token'])) {
      return false;
    }

    $this->accessToken = $data['access_token'];
    $this->accessTokenExpiresAt = isset($data['expires_in']) ? time() + (int) $data['expires_in'] : null;

    return true;
  }
  /**
   * 发送 GET 请求到微信 API
   *
   * 当 $withAccessToken 为 true 时，会自动附加当前有效的 access_token
   * （内部缓存为空或已过期时会先自动获取）。
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
   * （内部缓存为空或已过期时会先自动获取）。
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
