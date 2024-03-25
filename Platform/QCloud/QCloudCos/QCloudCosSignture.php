<?php

namespace kernel\Platform\QCloud\QCloudCos;

class QCloudCosSignture extends QCloudCosBase
{
  /**
   * host开关
   *
   * @var boolean
   */
  protected $SignHost = false;

  /**
   * 允许的头部键名
   *
   * @var array
   */
  protected $SignHeader = [
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

  public function __construct($SecretId, $SecretKey, $Region, $Bucket, $host = null, $SecurityToken = null)
  {
    $this->SignHost = !is_null($host);
    $this->SecurityToken = $SecurityToken;

    parent::__construct($SecretId, $SecretKey, $Region, $Bucket, $host = null, $SecurityToken);
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
   * @param array $Object 转换的对象数组
   * @param array $SkipKeys 跳过的键名
   * @param boolean $keyEnCode 是否对键名进行编码
   * @return string
   */
  protected function object2String($Object, $SkipKeys = [], $keyEnCode = true)
  {
    $List = [];
    foreach ($Object as $key => $value) {
      if (is_numeric($key)) {
        $key = $value;
        $value = "";
      }

      if (in_array($key, $SkipKeys)) {
        continue;
      }

      if ($keyEnCode) {
        $key = rawurlencode(urlencode($key));
      }

      $List[$key] = "{$key}={$value}";
    }

    return implode("&", $List);
  }
  /**
   * 对象转列表，并且按键名排序，键值改成键与键值用=连接  
   * ["a"=>1,"b"=>2] => ["a"=>"a=1","b"=>"b=2"]
   *
   * @param array $Object 对象数组
   * @param array $SkipKeys 跳过的键名
   * @return array
   */
  protected function object2List($Object, $SkipKeys = [])
  {
    $List = [];
    foreach ($Object as $key => $value) {
      if (in_array($key, $SkipKeys)) {
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

      $List[$key] = "{$key}={$value}";
    }
    ksort($List);

    return $List;
  }
  /**
   * 制作授权信息
   *
   * @param string $objectName 路径名称，/开头
   * @param array $URLParams  请求的URL参数
   * @param array $Headers  请求头部
   * @param int $Expires  签名有效期，多少秒
   * @param string $HTTPMethod  请求方式
   * @return string 授权信息，k=v&v1=v1 字符串形式的结构
   */
  function createAuthorization($objectName, $URLParams = [], $Headers = [], $Expires = 1800, $HTTPMethod = "get")
  {
    $HTTPMethod = strtolower($HTTPMethod);

    $StartTime = time();
    $EndTime = $StartTime + $Expires;

    $KeyTime = implode(";", [$StartTime, $EndTime]);
    $SignKey = hash_hmac("sha1", $KeyTime, $this->SecretKey);

    $SignAlgorithm = "sha1";

    if ($this->SignHost) {
      if (!array_key_exists("host", $Headers)) {
        $Headers['host'] = $this->Host;
      }
    }

    $URLParamList = $this->object2List($URLParams);
    $URLParamKeys = $this->getObjectKeys($URLParams);
    $URLParameterString = implode("&", array_values($URLParamList));
    $URLParameterKeyString = implode(";", array_keys($URLParamList));

    $SkipHeaderKeys = [];
    foreach ($Headers as $HeaderKey => $HeaderValue) {
      if (strpos($HeaderKey, "x-cos-") === false || (strpos($HeaderKey, "x-cos-") !== false && strpos($HeaderKey, "x-cos-") !== 0)) {
        if (!in_array($HeaderKey, $this->SignHeader)) {
          array_push($SkipHeaderKeys, $HeaderKey);
        }
      }
    }

    $HeaderList = $this->object2List($Headers, $SkipHeaderKeys, false);
    $HeaderKeys = $this->getObjectKeys($HeaderList);
    $HeaderString = implode("&", array_values($HeaderList));
    $HeaderKeyString = implode(";", array_keys($HeaderList));

    $HTTPString = implode("\n", [
      $HTTPMethod,
      urldecode($objectName),
      strtolower($URLParameterString),
      strtolower($HeaderString),
      ""
    ]);

    $StringToSign = implode("\n", [
      $SignAlgorithm,
      $KeyTime,
      sha1($HTTPString),
      ""
    ]);

    $Signature = hash_hmac("sha1", $StringToSign, $SignKey);

    $QueryStrings = [
      "q-sign-algorithm" => $SignAlgorithm,
      "q-ak" => $this->SecretId,
      "q-sign-time" => $KeyTime,
      "q-key-time" => $KeyTime,
      "q-header-list" => $HeaderKeyString,
      "q-signature" => $Signature,
      "q-url-param-list" => rawurlencode($URLParameterKeyString)
    ];

    if ($this->SecurityToken) {
      $QueryStrings['x-cos-security-token'] = $this->SecurityToken;
    }

    return array_merge($QueryStrings, array_map(function ($item) {
      return urlencode($item);
    }, $URLParams));
  }
}
