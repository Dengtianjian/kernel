<?php

namespace kernel\Modules\QCloud\QCloudCos;

use kernel\Modules\QCloud\QCloud;

class QCloudCosSignture extends QCloud
{
  /**
   * host开关
   *
   * @var boolean
   */
  protected $signHost = false;

  /**
   * 允许的头部键名
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

  public function __construct($secretId, $secretKey, $region, $bucket, $host = null, $securityToken = null)
  {
    $this->signHost = !is_null($host);
    $this->securityToken = $securityToken;

    parent::__construct($secretId, $secretKey, null, $host, $securityToken);
  }

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
   * 对象转对象字符串，每个键值对用 & 连接  
   * ["a"=>1,"b"=>2] => a=1&b=2
   *
   * @param array $object 转换的对象数组
   * @param array $skipKeys 跳过的键名
   * @param boolean $keyEncode 是否对键名进行编码
   * @return string
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
   * 对象转列表，并且按键名排序，键值改成键与键值用=连接  
   * ["a"=>1,"b"=>2] => ["a"=>"a=1","b"=>"b=2"]
   *
   * @param array $object 对象数组
   * @param array $skipKeys 跳过的键名
   * @return array
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
   * 制作授权信息
   *
   * @param string $objectName 路径名称，/开头
   * @param array $urlParams  请求的URL参数
   * @param array $headers  请求头部
   * @param int $expires  签名有效期，多少秒
   * @param string $httpMethod  请求方式
   * @return string 授权信息，k=v&v1=v1 字符串形式的结构
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

    $headerList = $this->object2List($headers, $skipHeaderKeys, false);
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
