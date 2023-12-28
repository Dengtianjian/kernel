<?php

namespace kernel\Foundation\File;

use kernel\Foundation\BaseObject;

class FileStorageSignature extends BaseObject
{
  /**
   * 签名秘钥，用于签名时加盐
   *
   * @var string
   */
  protected $SignatureKey = null;

  /**
   * 签名加密方式
   *
   * @var string
   */
  protected static $SignAlgorithm = "sha1";

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

  public function __construct($SignatureKey)
  {
    $this->SignatureKey = $SignatureKey;
  }

  /**
   * 获取签名加密方式
   *
   * @return string
   */
  static function getSignAlgorithm()
  {
    return self::$SignAlgorithm;
  }

  /**
   * 获取对象键名列表
   *
   * @param array $object 数组
   * @return array
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
   * 生成签名
   *
   * @param string $FileKey 文件名称
   * @param int $StartTime 签名有效期起始时间
   * @param int $EndTime 签名有效期结束时间
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param string $HTTPMethod 请求方式
   * @return string 签名
   */
  protected function generateSignature($FileKey, $StartTime, $EndTime, $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    $HTTPMethod = strtolower($HTTPMethod);

    $KeyTime = implode(";", [$StartTime, $EndTime]);
    $SignKey = hash_hmac("sha1", $KeyTime, $this->SignatureKey);

    $URLParamList = $this->object2List($URLParams);
    $URLParameterString = implode("&", array_values($URLParamList));

    $SkipHeaderKeys = [];

    $HeaderList = $this->object2List($Headers, $SkipHeaderKeys, false);
    $HeaderKeys = $this->getObjectKeys($HeaderList);
    $HeaderString = implode("&", array_values($HeaderList));
    $HeaderKeyString = implode(";", array_keys($HeaderList));

    $HTTPString = implode("\n", [
      $HTTPMethod,
      urldecode($FileKey),
      strtolower($URLParameterString),
      strtolower($HeaderString),
      ""
    ]);

    $StringToSign = implode("\n", [
      self::getSignAlgorithm(),
      $KeyTime,
      sha1($HTTPString),
      ""
    ]);

    return hash_hmac("sha1", $StringToSign, $SignKey);
  }
  /**
   * 制作授权信息
   *
   * @param string $FileKey 文件名称
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param integer $Expires 有效期，秒级数值
   * @param string $HTTPMethod 请求方法
   * @param boolean $toString 字符串形式返回参数，如果传入false，将会返回参数数组
   * @return string &连接的授权字符串
   */
  function createAuthorization($FileKey, $URLParams = [], $Headers = [], $Expires = 600, $HTTPMethod = "get", $toString = true)
  {
    $HTTPMethod = strtolower($HTTPMethod);

    $StartTime = time();
    $EndTime = time() + $Expires;

    $KeyTime = implode(";", [$StartTime, $EndTime]);
    $SignKey = hash_hmac("sha1", $KeyTime, $this->SignatureKey);

    $URLParamList = $this->object2List($URLParams);
    $URLParamKeys = $this->getObjectKeys($URLParams);
    $URLParameterString = implode("&", array_values($URLParamList));
    $URLParameterKeyString = implode(";", array_keys($URLParamList));

    $SkipHeaderKeys = [];

    $HeaderList = $this->object2List($Headers, $SkipHeaderKeys, false);
    $HeaderKeys = $this->getObjectKeys($HeaderList);
    $HeaderString = implode("&", array_values($HeaderList));
    $HeaderKeyString = implode(";", array_keys($HeaderList));

    $Signature = $this->generateSignature($FileKey, $StartTime, $EndTime, $URLParams, $Headers, $HTTPMethod);

    $QueryStrings = [
      "sign-algorithm" => self::getSignAlgorithm(),
      "sign-time" => $KeyTime,
      "key-time" => $KeyTime,
      "header-list" => $HeaderKeyString,
      "signature" => $Signature,
      "url-param-list" => rawurlencode($URLParameterKeyString)
    ];


    $QueryStrings = array_merge($QueryStrings, array_map(function ($item) {
      return urlencode($item);
    }, $URLParams));

    return $toString ? $this->object2String($QueryStrings, [], 0) : $QueryStrings;
  }
  /**
   * 验证签名是否正确
   *
   * @param string $Signature 被验证的签名
   * @param string $FileKey 文件名称
   * @param int $StartTime 签名有效期起始时间
   * @param int $EndTime 签名有效期结束时间
   * @param array $URLParams 请求参数
   * @param array $Headers 请求头
   * @param string $HTTPMethod 请求方式
   * @return boolean 是否正确
   */
  function verifyAuthorization($Signature, $FileKey, $StartTime, $EndTime, $URLParams = [], $Headers = [], $HTTPMethod = "get")
  {
    return $this->generateSignature($FileKey, $StartTime, $EndTime, $URLParams, $Headers, $HTTPMethod) === $Signature;
  }
}
