<?php

namespace kernel\Foundation\FileSystem\Storage;

use kernel\Foundation\Object\BaseObject;

/**
 * 文件访问签名器（仿腾讯云 COS 签名 v2 算法）
 *
 * 为私有文件 URL 提供「时效 + 防篡改」的授权能力，整体流程对齐 COS 的签名方案：
 * - 以密钥对 `KeyTime`（起始;结束）做 HMAC 得到 SignKey；
 * - 将 URL 参数 / 请求头按键名排序、编码后各自拼成字符串（url-param-list / header-list）；
 * - 以 `HTTP方法\n文件路径\n参数串\n头串` 做 SHA1 得到 HttpString，再组装 StringToSign 做最终 HMAC 得到 Signature。
 *
 * 对外暴露两个入口：
 * - {@see createAuthorization()}：签发方调用，生成授权参数字典（sign-algorithm / sign-time / key-time / header-list / signature / url-param-list），由 FileStorage 拼到 URL 上；
 * - {@see verifyAuthorization()}：验签方调用，用相同算法重算签名并比对。
 *
 * 注意：本类为通用 HMAC-SHA1 签名实现，本身不绑定具体云厂商；子类（如 QCloudCosSignture）可在其基础上补充 host / token 等字段。
 *
 * @package kernel\Foundation\FileSystem\Storage
 */
class StorageSignature extends BaseObject
{
  /**
   * @var string|null 签名密钥（HMAC 加盐用），由构造传入
   */
  protected $signatureKey = null;

  /**
   * @var string 签名哈希算法，目前固定为 sha1
   */
  protected static $signAlgorithm = "sha1";

  /**
   * @var array 参与签名的请求头白名单（仅这些头部会被纳入签名计算）
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
   * 构造签名器
   *
   * @param string $signatureKey 签名密钥，用于 HMAC 加盐
   */
  public function __construct($signatureKey)
  {
    $this->signatureKey = $signatureKey;
  }

  /**
   * 获取当前使用的签名哈希算法
   *
   * @return string 算法名（如 "sha1"）
   */
  static function getSignAlgorithm()
  {
    return self::$signAlgorithm;
  }

  /**
   * 提取对象中的「键名」列表
   *
   * 用于从 object2List 的结果中取出参与签名的键名串。规则：
   * - 关联键：取该键名；
   * - 数值键（表示「纯值列表」）：取该值本身作为键名。
   *
   * @param array $object 对象数组（通常来自 object2List 的结果）
   * @return array 键名列表，如 ["a", "b"]
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
   * 对象转字符串，每个键值对以 & 连接
   *
   * 形如 `["a"=>1,"b"=>2] => "a=1&b=2"`。要点：
   * - 数值键视作「纯值」，键名取该值、值置空；
   * - 命中 $skipKeys 的键被跳过；
   * - $keyEncode 为 true 时对键名做 rawurlencode(urlencode(...)) 双重编码。
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
   * 对象转「键名=值」列表，并按键名排序
   *
   * 形如 `["a"=>1,"b"=>2] => ["a"=>"a=1","b"=>"b=2"]`。要点：
   * - 数值键视作「纯值」，键名取该值、值置空；
   * - 命中 $skipKeys 的键被跳过；
   * - 键名统一小写并 urlencode，值经 rawurlencode 编码（空值保持空串）；
   * - 最终按 ksort 按键名升序排列，保证签名双方拼接顺序一致。
   *
   * @param array $object 对象数组
   * @param array $skipKeys 需要跳过的键名
   * @return array 排序后的「键名=值」关联数组
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
   * 生成签名（核心算法）
   *
   * 依据密钥、有效期、URL 参数、请求头、HTTP 方法计算最终 HMAC-SHA1 签名串：
   * 1. KeyTime = "起始;结束"，SignKey = HMAC-SHA1(KeyTime, 密钥)；
   * 2. 将 URL 参数 / 请求头经 {@see object2List()} 排序编码为参数串 / 头串；
   * 3. HttpString = "方法\n路径\n参数串\n头串"，StringToSign = "算法\nKeyTime\nSHA1(HttpString)"；
   * 4. 返回 HMAC-SHA1(StringToSign, SignKey)。
   *
   * @param string $fileKey 文件键（路径）
   * @param int $startTime 签名有效期起始时间（Unix 时间戳）
   * @param int $endTime 签名有效期结束时间（Unix 时间戳）
   * @param array $urlParams 参与签名的 URL 参数
   * @param array $headers 参与签名的请求头
   * @param string $httpMethod 请求方法，默认 get
   * @return string 最终 HMAC-SHA1 签名串
   */
  protected function generateSignature($fileKey, $startTime, $endTime, $urlParams = [], $headers = [], $httpMethod = "get")
  {
    $httpMethod = strtolower($httpMethod);

    $keyTime = implode(";", [$startTime, $endTime]);
    $signKey = hash_hmac("sha1", $keyTime, $this->signatureKey);

    $urlParamList = $this->object2List($urlParams);
    $urlParameterString = implode("&", array_values($urlParamList));

    $skipHeaderKeys = [];

    $headerList = $this->object2List($headers, $skipHeaderKeys);
    $headerKeys = $this->getObjectKeys($headerList);
    $headerString = implode("&", array_values($headerList));
    $headerKeyString = implode(";", array_keys($headerList));

    $httpString = implode("\n", [
      $httpMethod,
      urldecode($fileKey),
      strtolower($urlParameterString),
      strtolower($headerString),
      ""
    ]);

    $stringToSign = implode("\n", [
      self::getSignAlgorithm(),
      $keyTime,
      sha1($httpString),
      ""
    ]);

    return hash_hmac("sha1", $stringToSign, $signKey);
  }
  /**
   * 制作授权信息（签发）
   *
   * 生成一组可直接拼到文件 URL 上的授权参数字典：
   * `sign-algorithm` / `sign-time` / `key-time` / `header-list` / `signature` / `url-param-list`。
   * 其中 sign-time 与 key-time 为 `起始;结束`（当前时间起 $expires 秒内有效），
   * signature 由 {@see generateSignature()} 计算，`url-param-list` 经 rawurlencode 编码。
   *
   * 注意：返回值是**关联数组**（供调用方自行并入 URL query），并非已拼接的字符串。
   *
   * @param string $fileKey 文件键（路径）
   * @param array $urlParams 参与签名的 URL 参数
   * @param array $headers 参与签名的请求头
   * @param integer $expires 有效期，秒级数值，默认 600
   * @param string $httpMethod 请求方法，默认 get
   * @return array 授权参数字典
   */
  function createAuthorization($fileKey, $urlParams = [], $headers = [], $expires = 600, $httpMethod = "get")
  {
    $httpMethod = strtolower($httpMethod);

    $startTime = time();
    $endTime = $startTime + $expires;

    $keyTime = implode(";", [$startTime, $endTime]);
    $signKey = hash_hmac("sha1", $keyTime, $this->signatureKey);

    $urlParamList = $this->object2List($urlParams);
    $urlParamKeys = $this->getObjectKeys($urlParams);
    $urlParameterString = implode("&", array_values($urlParamList));
    $urlParameterKeyString = implode(";", array_keys($urlParamList));

    $skipHeaderKeys = [];

    $headerList = $this->object2List($headers, $skipHeaderKeys);
    $headerKeys = $this->getObjectKeys($headerList);
    $headerString = implode("&", array_values($headerList));
    $headerKeyString = implode(";", array_keys($headerList));

    $signature = $this->generateSignature($fileKey, $startTime, $endTime, $urlParams, $headers, $httpMethod);

    $queryStrings = [
      "sign-algorithm" => self::getSignAlgorithm(),
      "sign-time" => $keyTime,
      "key-time" => $keyTime,
      "header-list" => $headerKeyString,
      "signature" => $signature,
      "url-param-list" => rawurlencode($urlParameterKeyString)
    ];

    return $queryStrings;
  }
  /**
   * 验证签名是否正确（验签）
   *
   * 用与签发完全相同的参数与算法（{@see generateSignature()}）重算签名，
   * 再与传入的 $signature 做严格相等比对。任何参数（文件键 / 有效期 / 参数 / 头 / 方法）不一致都会导致验证失败。
   *
   * @param string $signature 待验证的签名串
   * @param string $fileKey 文件键（路径）
   * @param int $startTime 签名有效期起始时间（Unix 时间戳）
   * @param int $endTime 签名有效期结束时间（Unix 时间戳）
   * @param array $urlParams 参与签名的 URL 参数
   * @param array $headers 参与签名的请求头
   * @param string $httpMethod 请求方法，默认 get
   * @return boolean 签名一致返回 true，否则 false
   */
  function verifyAuthorization($signature, $fileKey, $startTime, $endTime, $urlParams = [], $headers = [], $httpMethod = "get")
  {
    return $this->generateSignature($fileKey, $startTime, $endTime, $urlParams, $headers, $httpMethod) === $signature;
  }
}
