<?php

namespace kernel\Modules\Wechat;

use kernel\Foundation\HTTP\Curl;
use kernel\Modules\Wechat\Models\WechatAccountsModel;

/**
 * 微信账号级接口调用凭证（access_token）
 *
 * 从 WechatApi 中抽出的独立令牌组件，只依赖 AppId/AppSecret，
 * 因此 {@see WechatAccount} 与 {@see WechatApi}（以及其各子类）都能直接调用。
 *
 * token() 的取值优先级：
 * 1. 实例内已注入或已缓存的 token；
 * 2. 进程内共享缓存：同一请求内多个对象共用同一份 token，避免重复获取；
 * 3. wechat_accounts 表：跨请求、跨进程的权威存储；
 * 4. 以上都没有或已过期时，请求微信稳定版接口获取，并写回表与进程内缓存。
 *
 * 另外：
 * - 提前 REFRESH_AHEAD 秒判定过期，避免临界失效导致业务请求失败；
 * - 表不可用（如无数据库连接）或账号未入库时自动退化，不会中断调用；
 * - 回源前会再查一次表（双检），减少并发时的重复获取；
 * - 稳定版接口是幂等的（不会顶掉旧 token），因此写表不加锁。
 *
 * 使用示例：
 *
 * ```php
 * $token = (new WechatAccessToken($appId, $appSecret))->token();
 * // 或从账号上取
 * $token = (new WechatAccount($appId, $appSecret))->accessToken();
 * ```
 */
class WechatAccessToken
{
  /**
   * 微信开放平台接口地址前缀
   */
  const API_URL = "https://api.weixin.qq.com";
  /**
   * access_token 提前刷新时间（秒）
   *
   * 判定过期时提前该秒数即视为过期。
   */
  const REFRESH_AHEAD = 300;
  /**
   * 微信未返回 expires_in 时的兜底有效期（秒）
   */
  const DEFAULT_EXPIRES_IN = 7200;

  /**
   * 账号 AppId
   *
   * @var string|null
   */
  protected $appId = null;
  /**
   * 账号 AppSecret
   *
   * @var string|null
   */
  protected $appSecret = null;
  /**
   * 当前实例持有的 access_token
   *
   * @var string|null
   */
  protected $accessToken = null;
  /**
   * 当前实例持有的 access_token 过期时间（Unix 时间戳，秒）
   *
   * @var int|null
   */
  protected $expiresAt = null;
  /**
   * CURL 客户端实例，用于请求微信接口
   *
   * @var Curl|null
   */
  protected $CURL = null;
  /**
   * 进程内共享缓存（appId => ["accessToken" => string, "expiresAt" => int|null]）
   *
   * 同一请求内可能创建多个令牌对象（如 WechatAccount 与各 WechatApi 实例各持一个），
   * 这里按 appId 共享，使它们共用同一份 token，避免重复向微信获取。仅存活于当前请求。
   *
   * @var array<string, array>
   */
  protected static $cache = [];

  /**
   * 初始化令牌组件
   *
   * @param string|null $appId 账号 AppId
   * @param string|null $appSecret 账号 AppSecret
   */
  function __construct($appId = null, $appSecret = null)
  {
    $this->appId = $appId;
    $this->appSecret = $appSecret;
  }
  /**
   * 读写一体：获取或设置 AppId
   *
   * @param string|null $data 待设置的 AppId
   * @return static|string|null 设置时返回当前实例；读取时返回 AppId
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
   * @param string|null $data 待设置的 AppSecret
   * @return static|string|null 设置时返回当前实例；读取时返回 AppSecret
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
   * 获取当前有效的 access_token（只读）
   *
   * 详见类注释中的取值优先级；未配置 AppId 或获取失败时返回 null。
   *
   * @return string|null
   */
  public function token()
  {
    // 1. 实例内已注入或已缓存的 token
    if ($this->accessToken && !$this->isExpired()) {
      return $this->accessToken;
    }
    if (!$this->appId) {
      return null;
    }

    // 2. 进程内共享缓存
    $cached = static::$cache[$this->appId] ?? null;
    if ($cached && !$this->isExpiresAtExpired($cached['expiresAt'])) {
      return $this->cache($cached['accessToken'], $cached['expiresAt']);
    }

    // 3. 表（权威存储），第二次查询为双检：可能在其他进程刷新期间已写入新值
    for ($attempt = 0; $attempt < 2; $attempt++) {
      $stored = $this->readStore();
      if ($stored && !$this->isExpiresAtExpired($stored['expiresAt'])) {
        return $this->cache($stored['accessToken'], $stored['expiresAt']);
      }
    }

    // 4. 回源微信获取并写回
    $data = $this->fetch();
    if (empty($data['access_token'])) {
      return null;
    }

    $expiresAt = time() + (int) ($data['expires_in'] ?? self::DEFAULT_EXPIRES_IN);
    $this->writeStore($data['access_token'], $expiresAt);

    return $this->cache($data['access_token'], $expiresAt);
  }
  /**
   * 强制刷新 access_token
   *
   * 以 force_refresh = true 调用稳定版接口，成功后写入表与进程内缓存。
   *
   * @return array|null 微信响应数组；请求失败时返回 null
   */
  public function refresh()
  {
    $data = $this->fetch(true);
    if (!empty($data['access_token'])) {
      $expiresAt = time() + (int) ($data['expires_in'] ?? self::DEFAULT_EXPIRES_IN);
      $this->writeStore($data['access_token'], $expiresAt);
      $this->cache($data['access_token'], $expiresAt);
    }

    return $data;
  }
  /**
   * 请求微信稳定版接口获取 access_token（不写入缓存）
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Basic_Information/getStableAccessToken.html
   *
   * @param bool $forceRefresh 是否强制刷新（默认 false，由微信按有效期返回）
   * @return array|null 微信响应数组（含 access_token、expires_in）；未配置账号或失败时返回 null
   */
  public function fetch($forceRefresh = false)
  {
    if (!$this->appId || !$this->appSecret) {
      return null;
    }

    return $this->request()
      ->url(self::API_URL . "/cgi-bin/stable_token")
      ->https(false)
      ->post([
        "grant_type" => "client_credential",
        "appid" => $this->appId,
        "secret" => $this->appSecret,
        "force_refresh" => $forceRefresh
      ])
      ->getData();
  }
  /**
   * 请求微信普通版接口获取 access_token（不写入缓存）
   *
   * 注意：普通版接口会使同一账号此前获取的 access_token 立即失效，
   * 多实例/多进程场景请优先使用 fetch()（稳定版）。
   *
   * @link https://developers.weixin.qq.com/doc/offiaccount/Basic_Information/Get_access_token.html
   *
   * @return array|null 微信响应数组；未配置账号或失败时返回 null
   */
  public function fetchPlain()
  {
    if (!$this->appId || !$this->appSecret) {
      return null;
    }

    return $this->request()
      ->url(self::API_URL . "/cgi-bin/token", [
        "grant_type" => "client_credential",
        "appid" => $this->appId,
        "secret" => $this->appSecret
      ])
      ->data([])
      ->https(false)
      ->get()
      ->getData();
  }
  /**
   * 直接注入 access_token 与过期时间（不写入进程内共享缓存）
   *
   * 适用于从其他来源（如自有缓存、外部系统）恢复 token 的场景。
   *
   * @param string|null $accessToken access_token
   * @param int|null $expiresAt 过期时间（Unix 时间戳，秒），不传时视为未过期
   * @return static 当前实例
   */
  public function set($accessToken, $expiresAt = null)
  {
    $this->accessToken = $accessToken;
    $this->expiresAt = $expiresAt ? (int) $expiresAt : null;

    return $this;
  }
  /**
   * 读写一体：获取或设置当前实例的 access_token 过期时间
   *
   * 传入参数时为设置，并返回当前实例以支持链式调用；不传参数时返回当前过期时间。
   * 外部注入 access_token 时应一并设置过期时间，否则将被视为未过期。
   *
   * @param int|null $data 待设置的过期时间（Unix 时间戳，秒）
   * @return static|int|null
   */
  public function expiresAt($data = null)
  {
    if (func_num_args()) {
      $this->expiresAt = $data ? (int) $data : null;
      return $this;
    }

    return $this->expiresAt;
  }
  /**
   * 判断当前实例的 access_token 是否为空或已进入提前刷新窗口
   *
   * @return bool token 为空或已过期返回 true
   */
  public function isExpired()
  {
    if (!$this->accessToken) {
      return true;
    }

    return $this->isExpiresAtExpired($this->expiresAt);
  }
  /**
   * 判断指定过期时间是否已进入提前刷新窗口
   *
   * 提前 REFRESH_AHEAD 秒即视为过期；未记录过期时间（null/0）时视为未过期。
   *
   * @param int|null $expiresAt 过期时间（Unix 时间戳，秒）
   * @return bool
   */
  public function isExpiresAtExpired($expiresAt)
  {
    if (!$expiresAt) {
      return false;
    }

    return (int) $expiresAt - self::REFRESH_AHEAD <= time();
  }
  /**
   * 写入当前实例与进程内共享缓存，并返回 token（内部使用）
   *
   * @param string $accessToken access_token
   * @param int|null $expiresAt 过期时间（Unix 时间戳，秒）
   * @return string 传入的 access_token
   */
  protected function cache($accessToken, $expiresAt)
  {
    $expiresAt = $expiresAt ? (int) $expiresAt : null;

    $this->accessToken = $accessToken;
    $this->expiresAt = $expiresAt;
    static::$cache[$this->appId] = [
      "accessToken" => $accessToken,
      "expiresAt" => $expiresAt
    ];

    return $accessToken;
  }
  /**
   * 从 wechat_accounts 表读取本账号的 access_token（内部使用）
   *
   * 该表是跨请求、跨进程的权威存储；账号未入库或表不可用时返回 null。
   *
   * @return array|null ["accessToken" => string, "expiresAt" => int|null]
   */
  protected function readStore()
  {
    try {
      $row = WechatAccountsModel::where("app_id", $this->appId)->first();
    } catch (\Throwable $e) {
      return null;
    }
    if (!$row) {
      return null;
    }
    if (is_object($row) && method_exists($row, "toArray")) {
      $row = $row->toArray();
    }
    $row = (array) $row;

    $accessToken = $row['access_token'] ?? $row['accessToken'] ?? null;
    if (!$accessToken) {
      return null;
    }
    $expiresAt = $row['access_token_expires_at'] ?? $row['accessTokenExpiresAt'] ?? null;

    return [
      "accessToken" => $accessToken,
      "expiresAt" => $expiresAt ? (int) $expiresAt : null
    ];
  }
  /**
   * 将 access_token 写入 wechat_accounts 表（内部使用）
   *
   * 只更新已存在的账号行（账号未入库时不会插入新行）；稳定版接口返回的 token 是幂等的，
   * 并发写入同一值无害，因此这里不加锁。落库失败不影响本次调用。
   *
   * @param string $accessToken access_token
   * @param int $expiresAt 过期时间（Unix 时间戳，秒）
   * @return void
   */
  protected function writeStore($accessToken, $expiresAt)
  {
    try {
      WechatAccountsModel::where("app_id", $this->appId)->update([
        "access_token" => $accessToken,
        "access_token_expires_at" => $expiresAt,
        "updated_at" => time()
      ]);
    } catch (\Throwable $e) {
      // 落库失败不影响本次调用，token 仍保存在进程内缓存中
    }
  }
  /**
   * 获取 CURL 客户端（内部使用）
   *
   * @return Curl
   */
  protected function request()
  {
    if ($this->CURL === null) {
      $this->CURL = new Curl();
    }

    return $this->CURL;
  }
}
