<?php

namespace kernel\Platform\QCloud\QCloudCos;

use kernel\Foundation\BaseObject;

class QCloudCosSignture extends QCloudCos
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

  public function __construct($SecretId, $SecretKey, $Region, $Bucket, $host = null)
  {

    $this->signHost = !is_null($host);

    parent::__construct($SecretId, $SecretKey, $Region, $Bucket, $host = null);
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
      if (in_array($key, $SkipKeys)) {
        continue;
      }

      if ($keyEnCode) {
        $key = rawurlencode($key);
      }

      $List[$key] = "{$key}=" . ($value ?: '');
    }

    return implode("&", $List);
  }
  /**
   * 对象转列表，并且按键名排序，键值改成键与键值用=连接  
   * ["a"=>1,"b"=>2] => ["a"=>"a=1","b"=>"b=2"]
   *
   * @param array $Object 对象数组
   * @param array $SkipKeys 跳过的键名
   * @param boolean $keyEnCode 是否对键名进行编码
   * @param boolean $sort 是否排序
   * @return array
   */
  protected function object2List($Object, $SkipKeys = [], $keyEnCode = true, $sort = true)
  {
    $List = [];
    foreach ($Object as $key => $value) {
      if (in_array($key, $SkipKeys)) {
        continue;
      }

      if ($keyEnCode) {
        $key = rawurlencode($key);
      }

      $key = strtolower($key);

      $List[$key] = "{$key}=" . ($value ?: '');
    }
    if ($sort) {
      ksort($List);
    }

    return $List;
  }
  /**
   * 制作授权信息
   *
   * @param string $objectName 路径名称，/开头
   * @param string $HTTPMethod 调用的服务所使用的请求方法
   * @param array $URLParams  请求的URL参数
   * @param array $Headers  请求头部
   * @param int $StartTime 授权开始时间，秒级时间戳
   * @param int $EndTime 授权结束时间，秒级时间戳
   * @return string 授权信息，k=v&v1=v1 字符串形式的结构
   */
  function createAuthorization($objectName, $HTTPMethod = "get", $URLParams = [], $Headers = [], $StartTime = null, $EndTime = null)
  {
    $HTTPMethod = strtolower($HTTPMethod);

    $StartTime = $StartTime ?: time() - 60;
    $EndTime = $EndTime ?: strtotime('+30 minutes');

    $KeyTime = implode(";", [$StartTime, $EndTime]);
    $SignKey = hash_hmac("sha1", $KeyTime, $this->SecretKey);

    $SignAlgorithm = "sha1";

    if ($this->signHost) {
      if (!array_key_exists("host", $Headers)) {
        $Headers['host'] = $this->Host;
      }
    }

    $URLParamList = $this->object2List($URLParams, []);
    $URLParamKeys = array_keys($URLParamList);
    $URLParameterStringList = array_values($URLParamList);
    $URLParameterString = implode("&", $URLParameterStringList);
    $URLParameterKeyString = implode(";", $URLParamKeys);

    $SkipHeaderKeys = [];
    foreach ($Headers as $HeaderKey => $HeaderValue) {
      if (!(strpos($HeaderKey, "x-cos-") !== false && strpos($HeaderKey, "x-cos-") === 0)) {
        array_push($SkipHeaderKeys, $HeaderKey);
      }
      if (!in_array($HeaderKey, $this->SignHeader)) {
        array_push($SkipHeaderKeys, $HeaderKey);
      }
    }
    $HeaderList = $this->object2List($Headers, $SkipHeaderKeys);
    $HeaderKeys = array_keys($HeaderList);
    $HeaderStringList = array_values($HeaderList);
    $HeaderString = implode("&", $HeaderStringList);
    $HeaderKeyString = implode(";", $HeaderKeys);

    $HTTPString = implode("\n", [
      $HTTPMethod,
      $objectName,
      $URLParameterString,
      $HeaderString,
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
      "q-header-list" => rawurlencode($HeaderKeyString),
      "q-signature" => $Signature,
      "q-url-param-list" => rawurlencode($URLParameterKeyString)
    ];

    $QueryStrings = array_merge($QueryStrings, $URLParams);

    return $this->object2String($QueryStrings);
  }
}
