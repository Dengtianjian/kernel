<?php

namespace kernel\Modules\QCloud\QCloudCos;

/**
 * 腾讯云 COS 请求签名器（q-sign-* 系列，COS 签名 v5）
 *
 * **不继承任何类**：本类是**纯计算**（凭据 + 请求要素 → 签名参数），不参与 HTTP 发送，
 * 所以只自带 4 个属性（`secretId` / `secretKey` / `securityToken` / `host`）。
 * 说明：此前继承过 {@see \kernel\Modules\QCloud\QCloud}，但父类构造会 `new Curl()`，还会带来
 * `get()` / `post()` / `generateAuthorizaion()`（那是 TC3-HMAC，与 COS 的 `q-*` 是两套算法）
 * 等与 COS 签名无关的方法 —— 本类**一个都没用到**，故已解耦（无任何类型依赖，构造签名不变）。
 *
 * 产出
 * `q-sign-algorithm` / `q-ak` / `q-sign-time` / `q-key-time` / `q-header-list` /
 * `q-signature` / `q-url-param-list` 这组查询参数，由调用方拼到 URL 上，用于私有对象访问或前端直传。
 *
 * 与 {@see \kernel\Foundation\FileSystem\Storage\StorageSignature}（`sign-*` 系列，站内文件路由用）
 * 是两套**并行**实现 ── `object2List()` / `object2String()` / `getObjectKeys()` 三个工具方法在两边各有一份。
 *
 * 构造参数中：`$host` 非空 ⇒ 把 host 头纳入签名（见 {@see $signHost}）；
 * `$securityToken` 非空 ⇒ 返回值会多一个 `x-cos-security-token`（临时密钥场景）。
 *
 * 注意：类名里的 `Signture` 是 **Signature 的拼写笔误**（历史遗留，涉及文件名与多处引用，本次未改）。
 *
 * @package kernel\Modules\QCloud\QCloudCos
 */
class QCloudCosSignture
{
  /** @var string|null 云 API SecretId（签名里的 `q-ak`） */
  protected $secretId = null;

  /** @var string|null 云 API SecretKey（算 HMAC 用） */
  protected $secretKey = null;

  /** @var string|null 临时密钥的会话令牌；非空时返回值带 `x-cos-security-token` */
  protected $securityToken = null;

  /** @var string|null 请求 Host；仅当 {@see $signHost} 为 true 时参与签名 */
  protected $host = null;

  /**
   * 是否把 host 头纳入签名
   *
   * 由构造函数按 `$host` 是否为空决定（`!is_null($host)`），本类不提供 setter。
   * 为 true 时 {@see createAuthorization()} 会在调用方未传 `host` 头时补一个，
   * 使签名与「该请求发往哪个 Host」绑定。
   *
   * @var boolean
   */
  protected $signHost = false;

  /**
   * 允许参与签名的头部白名单（键名小写）
   *
   * 列举的是 COS 的对象级响应头（`response-*`）与传输类头部。筛选规则见 {@see createAuthorization()}：
   * - 以 `x-cos-` 开头的头部**始终**参与签名（不看这份白名单）；
   * - 其余头部只有在白名单里才参与，未列出的一律跳过（不计入签名，也不出现在 `q-header-list`）。
   *
   * @var array
   */
  protected $signHeader = [
    'cache-control',
    'content-disposition',
    'content-encoding',
    'content-length',
    'content-md5',
    'content-type',
    'expect',
    'expires',
    'host',
    'if-match',
    'if-modified-since',
    'if-none-match',
    'if-unmodified-since',
    'origin',
    'range',
    'response-cache-control',
    'response-content-disposition',
    'response-content-encoding',
    'response-content-language',
    'response-content-type',
    'response-expires',
    'transfer-encoding',
    'versionid',
  ];

  /**
   * 构造 COS 签名器
   *
   * 凭据（`$secretId` / `$secretKey`）与 `$host` / `$securityToken` 全部由本类自己保存
   * （**不再**交给父类 —— 本类已不继承任何类）。
   *
   * ⚠️ **构造签名已简化**：原先第 3/4 参是 `$region` / `$bucket`，但 COS 签名**根本不需要**它们
   * （要签的路径由 {@see createAuthorization()} 的 `$objectName` 提供），属于死参数 ⇒ 已移除。
   * 现签名：`__construct($secretId, $secretKey, $host = null, $securityToken = null)`。
   *
   * @param string $secretId 云 API SecretId
   * @param string $secretKey 云 API SecretKey
   * @param string|null $host 请求 Host；非空时把 host 头纳入签名，默认 null（不签 host）
   * @param string|null $securityToken 临时密钥的会话令牌；非空时返回值带 x-cos-security-token
   * @return void
   */
  public function __construct($secretId, $secretKey, $host = null, $securityToken = null)
  {
    $this->secretId = $secretId;
    $this->secretKey = $secretKey;
    $this->securityToken = $securityToken;
    $this->host = $host;

    $this->signHost = !is_null($host);
  }

  /**
   * 取出对象数组的「键名」列表
   *
   * 规则：数值键视作**纯值列表**（取该值当键名），关联键取其键名本身。
   *
   * 实现现状：`createAuthorization()` 里两次调用本方法的结果（`$urlParamKeys` / `$headerKeys`）
   * **都没有被使用** —— 键名串实际是用 `implode(";", array_keys($list))` 从 {@see object2List()}
   * 的结果取的，故本方法目前等同空跑（保留自早期实现）。
   *
   * @param array $object 对象数组
   * @return array 键名列表
   */
  protected function getObjectKeys($object)
  {
    $keys = [];

    foreach ($object as $key => $value) {
      if (is_numeric($key)) {
        array_push($keys, $value);
      } else {
        array_push($keys, $key);
      }
    }

    return $keys;
  }
  /**
   * 对象转「键=值&键=值」字符串
   *
   * 例：`["a"=>1,"b"=>2]` → `"a=1&b=2"`。要点：
   * - 数值键视作纯值：键名取该值、值置空；
   * - 命中 `$skipKeys` 的键直接跳过（`in_array` **非严格**比较，注意 `0` / `"0"` 这类值）；
   * - `$keyEncode` 为 true 时对键名做 `rawurlencode(urlencode(...))` **双重编码**。
   *
   * 实现现状：本方法在仓内**没有任何调用方**（{@see \kernel\Foundation\FileSystem\Storage\StorageSignature}
   * 里那份同名副本同样未被调用），保留自早期实现；当前签名走 {@see object2List()}。
   *
   * @param array $object 转换的对象数组
   * @param array $skipKeys 需要跳过的键名
   * @param boolean $keyEncode 是否对键名进行编码，默认 true
   * @return string 以 & 连接的键值串
   */
  protected function object2String($object, $skipKeys = [], $keyEncode = true)
  {
    $list = [];
    foreach ($object as $key => $value) {
      if (is_numeric($key)) {
        $key = $value;
        $value = "";
      }

      if (in_array($key, $skipKeys)) {
        continue;
      }

      if ($keyEncode) {
        $key = rawurlencode(urlencode($key));
      }

      $list[$key] = "{$key}={$value}";
    }

    return implode("&", $list);
  }
  /**
   * 对象转「键名 => 键名=值」列表，并按键名排序
   *
   * 例：`["a"=>1,"b"=>2]` → `["a"=>"a=1","b"=>"b=2"]`。要点：
   * - 数值键视作纯值：键名取该值、值置空；命中 `$skipKeys` 的键跳过；
   * - 键名统一**小写**并 `urlencode`；值 `rawurlencode`（**空值保持空串**，注意 `"0"` 也判为空）；
   * - 最后 `ksort` 按键名升序 —— 这一步是签发方与校验方能拼出同一串的前提。
   *
   * 调用方式：`array_keys($list)` 得到键名串（`q-header-list` / `q-url-param-list` 的内容），
   * `array_values($list)` 得到值串（形如 `k=v`）再用 `&` 连接。
   *
   * @param array $object 对象数组
   * @param array $skipKeys 需要跳过的键名
   * @return array 排序后的「键名 => 键名=值」关联数组
   */
  protected function object2List($object, $skipKeys = [])
  {
    $list = [];
    foreach ($object as $key => $value) {
      if (in_array($key, $skipKeys)) {
        continue;
      }
      if (is_int($key)) {
        $key = $value;
        $value = "";
      }

      $key = strtolower(urlencode($key));

      if ($value) {
        $value = rawurlencode($value);
      } else {
        $value = "";
      }

      $list[$key] = "{$key}={$value}";
    }
    ksort($list);

    return $list;
  }
  /**
   * 生成 COS 请求签名（授权参数）
   *
   * 流程：
   * 1. `KeyTime = "{起始};{结束}"`（起始 = 当前时间，结束 = 起始 + `$expires`），
   *    `SignKey = HMAC-SHA1(KeyTime, secretKey)`；
   * 2. {@see $signHost} 为真且调用方未传 `host` 头时，补一个 `host`（取本实例的 host）；
   * 3. URL 参数与请求头分别经 {@see object2List()} 排序编码 ⇒ 参数串 / 头串 / 键名串；
   *    头部先筛一遍：`x-cos-` 开头的一律参与签名，其余必须在 {@see $signHeader} 白名单里，否则跳过；
   * 4. `HttpString = "方法\n路径\n参数串\n头串\n"`（参数串与头串转小写、路径 `urldecode`），
   *    `StringToSign = "sha1\nKeyTime\nSHA1(HttpString)\n"`，`Signature = HMAC-SHA1(StringToSign, SignKey)`；
   * 5. 组装 `q-sign-algorithm` / `q-ak` / `q-sign-time` / `q-key-time` / `q-header-list` /
   *    `q-signature` / `q-url-param-list`；传了 `securityToken` 时再加 `x-cos-security-token`；
   * 6. 最后把调用方传入的 `$urlParams` 逐个 `urlencode` **并入**返回值 —— 所以返回数组同时含
   *    签名参数与原始业务参数。
   *
   * 实现现状（未改，勿与注释混淆）：
   * - `$signAlgorithm` 在方法内**硬编码** `"sha1"`；
   * - `$urlParamKeys` / `$headerKeys` 赋值后未被使用（见 {@see getObjectKeys()}）；
   * - 编码方式混用：键名 `urlencode`、值 `rawurlencode`、第 6 步的业务参数 `urlencode`。
   *
   * 主要调用方：{@see QCloudCOSStorage::createAuthorization()}（经它再由 {@see DiscuzXQCloudCOS} 使用）。
   *
   * @param string $objectName 对象路径，以 `/` 开头
   * @param array $urlParams 参与签名的 URL 参数（同时会并入返回值）
   * @param array $headers 参与签名的请求头
   * @param integer $expires 签名有效期（秒），默认 1800
   * @param string $httpMethod 请求方法，默认 get（内部转小写）
   * @return array 授权参数（q-* 键 + 可选 x-cos-security-token + 并入的业务参数）
   */
  function createAuthorization($objectName, $urlParams = [], $headers = [], $expires = 1800, $httpMethod = "get")
  {
    $httpMethod = strtolower($httpMethod);

    $startTime = time();
    $endTime = $startTime + $expires;

    $keyTime = implode(";", [$startTime, $endTime]);
    $signKey = hash_hmac("sha1", $keyTime, $this->secretKey);

    $signAlgorithm = "sha1";

    if ($this->signHost) {
      if (!array_key_exists("host", $headers)) {
        $headers['host'] = $this->host;
      }
    }

    $urlParamList = $this->object2List($urlParams);
    $urlParamKeys = $this->getObjectKeys($urlParams);
    $urlParameterString = implode("&", array_values($urlParamList));
    $urlParameterKeyString = implode(";", array_keys($urlParamList));

    $skipHeaderKeys = [];
    foreach ($headers as $headerKey => $headerValue) {
      if (strpos($headerKey, "x-cos-") === false || (strpos($headerKey, "x-cos-") !== false && strpos($headerKey, "x-cos-") !== 0)) {
        if (!in_array($headerKey, $this->signHeader)) {
          array_push($skipHeaderKeys, $headerKey);
        }
      }
    }

    $headerList = $this->object2List($headers, $skipHeaderKeys);
    $headerKeys = $this->getObjectKeys($headerList);
    $headerString = implode("&", array_values($headerList));
    $headerKeyString = implode(";", array_keys($headerList));

    $httpString = implode("\n", [
      $httpMethod,
      urldecode($objectName),
      strtolower($urlParameterString),
      strtolower($headerString),
      ""
    ]);

    $stringToSign = implode("\n", [
      $signAlgorithm,
      $keyTime,
      sha1($httpString),
      ""
    ]);

    $signature = hash_hmac("sha1", $stringToSign, $signKey);

    $queryStrings = [
      "q-sign-algorithm" => $signAlgorithm,
      "q-ak" => $this->secretId,
      "q-sign-time" => $keyTime,
      "q-key-time" => $keyTime,
      "q-header-list" => $headerKeyString,
      "q-signature" => $signature,
      "q-url-param-list" => rawurlencode($urlParameterKeyString)
    ];

    if ($this->securityToken) {
      $queryStrings['x-cos-security-token'] = $this->securityToken;
    }

    return array_merge($queryStrings, array_map(function ($item) {
      return urlencode($item);
    }, $urlParams));
  }
}
