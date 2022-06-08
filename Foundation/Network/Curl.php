<?php

namespace kernel\Foundation\Network;

use CURLFile;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Output;

/**
 * 二次封装的CURL类
 *
 * 通过init静态方法实例化后链式调用实例化后的方法
 */

class Curl
{
  private $curlInstance = NULL; //* curl实例
  private $requestUrl = NULL; //* 请求的地址
  private $curlOptions = []; //* curl的设置
  private $curlHeaders = []; //* 请求头
  private $curlDatas = []; //* 请求的post field数据，相当于请求body
  private $curlMethod = "get"; //* 请求的方法
  private $curlTimeout = 60; //* 默认60秒
  private $isJson = true; //* 请求数据是json，接收的也会json格式化。
  private $curlCookie = []; //* 请求的cookies
  private $uploadFile = []; //* 上传的文件，当执行了file方法
  private $bypasSSLVerification = false; //* 绕过SSL验证
  private $responseData = NULL; //* 响应的数据
  private $curlErrorMsg = NULL; //* 错误信息
  private $curlErrorNo = NULL; //* 错误码
  private $responseHeadersData = null; //* 响应头
  private $responseStatusCode = 200; //* 相应状态码
  private $proxy = [ //* 代理相关
    "open" => false, //* 是否开启
    "url" => "", //* 代理URL地址
    "port" => "", //* 代理的URL端口
    "username" => "", //* 代理用户
    "password" => "" //* 代理用户密码
  ];
  /**
   * 实例化当前类
   *
   * @return object Curl的实例
   */
  public static function init(): Curl
  {
    return new Curl();
  }
  /**
   * 设置请求url和请求query
   *
   * @param string $url 请求的url
   * @param array[$key=>$value] $query query参数和参数值
   * @return Curl 当前实例
   */
  public function url($url, $query = []): Curl
  {
    $query = \http_build_query($query);
    if (\strlen($query) > 0) {
      $url .= "?$query";
    }
    $this->requestUrl = $url;
    return $this;
  }
  /**
   * 设置为json请求数据和格式化响应的json数据
   *
   * @param boolean $yes 是否是json格式
   * @return Curl 当前实例
   */
  public function json($yes): Curl
  {
    $this->isJson = $yes;
    return $this;
  }
  /**
   * 设置curl选项
   *
   * @param array[$key=>$value] $options 选项和选项值。$key是CURL的常量值
   * @return Curl 当前实例
   */
  public function options($options): Curl
  {
    foreach ($options as $key => $value) {
      $this->curlOptions[$key] = $value;
    }
    return $this;
  }
  /**
   * 设置header
   *
   * @param array[$key=>$value] $params 参数和参数值
   * @return Curl 当前实例
   */
  public function headers($params): Curl
  {
    foreach ($params as $key => $value) {
      $this->curlHeaders[$key] = $value;
    }
    return $this;
  }
  /**
   * 把数组转换成字符串形式的数组
   *
   * @param array[$key=>$value] $params 参数和参数值
   * @return array 准换后的字符串 [ "k1:v1", "k2:v2", ... ]
   */
  public function buildHeaders($params): array
  {
    if (Arr::isAssoc($params)) {
      foreach ($params as $key => &$value) {
        $value = "$key: $value";
      }
      $params = \array_values($params);
    }
    return $params;
  }
  /**
   * 设置请求数据
   * 每调用就会递增增加数据
   *
   * @param array[$key=>$value] $datas 数据
   * @return Curl 当前实例
   */
  public function data($datas): Curl
  {
    foreach ($datas as $key => $value) {
      $this->curlDatas[$key] = $value;
    }
    return $this;
  }
  /**
   * 设置请求方法为 get
   *
   * @return Curl
   */
  public function get(): Curl
  {
    $this->curlMethod = "get";
    return $this->send();
  }
  /**
   * 设置请求方法为 post
   *
   * @return Curl
   */
  public function post(): Curl
  {
    $this->curlMethod = "post";
    return $this->send();
  }
  /**
   * 设置请求方法为 put
   *
   * @return Curl
   */
  public function put(): Curl
  {
    $this->curlMethod = "put";
    return $this->send();
  }
  /**
   * 设置请求方法为 patch
   *
   * @return Curl
   */
  public function patch(): Curl
  {
    $this->curlMethod = "patch";
    return $this->send();
  }
  /**
   * 设置请求方法为 delete
   *
   * @return Curl
   */
  public function delete(): Curl
  {
    $this->curlMethod = "delete";
    return $this->send();
  }
  /**
   * 设置请求方法为 head
   *
   * @return Curl
   */
  public function head(): Curl
  {
    $this->curlMethod = "head";
    return $this->send();
  }
  /**
   * 设置请求方法为connect
   *
   * @return Curl
   */
  public function connect(): Curl
  {
    $this->curlMethod = "connect";
    return $this->send();
  }
  /**
   * 上传文件
   *
   * @param string|array[filenames]|array[filename=>postName]|[filename=>[$postName,$mime]] $fileNames 文件名称，包含路径
   * @param string $postName 上传数据中的文件名称（默认为属性 name ）
   * @param string $mime 文件的 MIME type（默认是application/octet-stream）
   * @return Curl
   */
  public function file($fileNames, $postName = "", $mime = ""): Curl
  {
    $this->curlMethod = "file";
    if (\is_array($fileNames)) {
      if (Arr::isAssoc($fileNames)) {
        foreach ($fileNames as $key => $value) {
          if (\is_array($value)) {
            \array_push($this->uploadFile, new CURLFile($key, $value[1], $value[0]));
          } else {
            \array_push($this->uploadFile, new CURLFile($key, $mime, $value[0]));
          }
        }
      } else {
        foreach ($fileNames as $fileItem) {
          \array_push($this->uploadFile, new CURLFile($fileItem, $mime, $postName));
        }
      }
    } else {
      \array_push($this->uploadFile, new CURLFile($fileNames, $mime, $postName));
    }

    return $this->send();
  }
  /**
   * 设置请求超时时间
   *
   * @param integer $seconds 超时秒数
   * @return Curl 当前实例
   */
  public function timeout($seconds): Curl
  {
    $this->curlTimeout = $seconds;
    return $this;
  }
  /**
   * 设置请求代理
   *
   * @param string $url 代理地址
   * @param int $port 代理接口
   * @param string $username 代理用户名
   * @param string $password 代理用户密码
   * @return Curl
   */
  public function proxy(string $url, int $port, string $username = "", string $password = ""): Curl
  {
    $this->proxy["open"] = true;
    $this->proxy["url"] = $url;
    $this->proxy["port"] = $port;
    $this->proxy["username"] = $username;
    $this->proxy["password"] = $password;
    return $this;
  }
  /**
   * 开启https。协议头是https情况下默认验证https
   * 传true是验证https，否则就绕过https
   *
   * @param boolean $yes 是否开启
   * @return Curl 当前实例
   */
  public function https($yes = true): Curl
  {
    $this->bypasSSLVerification = !$yes;
    return $this;
  }
  /**
   * 设置cookie
   *
   * @param array[$key=>$value] $datas 数据
   * @return Curl 当前实例
   */
  public function cookie($datas): Curl
  {
    $this->curlCookie = $datas;
    return $this;
  }
  /**
   * 处理cookie数据，转换称curl要求格式
   *
   * @param array[$key=>$value] $datas 数据
   * @return string 转换后的字符串
   */
  public function buildCookie($datas): string
  {
    if (Arr::isAssoc($datas)) {
      foreach ($datas as $key => &$value) {
        $value = "$key=$value";
      }
    }
    $datas = \implode("; ", $datas);

    return $datas;
  }
  /**
   * 发送请求
   * 这个函数包含实例化cur，设置headers、options、cookies
   * 有错调error或者erron，不然就getData
   *
   * @return Curl 当前实例
   */
  public function send(): Curl
  {
    $curl = curl_init();
    $this->curlInstance = $curl;

    $sendDatas = $this->curlDatas;
    $defaultHeaders = [];
    if ($this->curlMethod === "file") {
      $this->isJson = false;
      $sendDatas = Arr::merge($sendDatas, $this->uploadFile);
    }
    if ($this->isJson) {
      $defaultHeaders['Content-type'] = "Application/json";
      $sendDatas = \json_encode($sendDatas);
      if ($this->curlMethod === "get") {
        $sendDatas = \urlencode($sendDatas);
      }
    }
    $headers = Arr::merge($defaultHeaders, $this->curlHeaders);
    $headers = $this->buildHeaders($headers);
    $options = [
      CURLOPT_URL => $this->requestUrl, //* 请求地址
      CURLOPT_RETURNTRANSFER => true, //* 直接返回原生rawData
      CURLOPT_TIMEOUT => $this->curlTimeout, //* 超时
      CURLOPT_HTTPHEADER => $headers, //* headers
      CURLOPT_CUSTOMREQUEST => \strtoupper($this->curlMethod), //* 请求方法
      CURLOPT_COOKIE => $this->buildCookie($this->curlCookie) //* cookies
    ];
    if ($this->curlMethod !== "get") {
      $options[CURLOPT_POSTFIELDS] = $sendDatas;
    }
    //* 绕过SSL验证
    if ($this->bypasSSLVerification === true) {
      $options[CURLOPT_SSL_VERIFYPEER] = false;
      $options[CURLOPT_SSL_VERIFYHOST] = false;
    }
    //* 开启代理
    if ($this->proxy['open']) {
      $options[CURLOPT_PROXY] = $this->proxy['url'];
      $options[CURLOPT_PROXYPORT] =  $this->proxy['port'];
      if ($this->proxy['username']) {
        $options[CURLOPT_PROXYUSERPWD] =  $this->proxy['username'] . ":" . $this->proxy['password'];
      }
    }

    foreach ($this->curlOptions as $key => $value) {
      $options[$key] = $value;
    }
    \curl_setopt_array($curl, $options);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_NOBODY, false);
    $result = \curl_exec($curl);
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($result, 0, $headerSize);
    $header = explode("\r\n", $header);
    $responseHeaders = [];
    foreach ($header as &$headerItem) {
      $headerItem = trim($headerItem);
      $firstColon = strpos($headerItem, ":");
      if ($firstColon === false) {
        if (preg_match("/HTTP.+/", $headerItem)) {
          $headerItem = explode(" ", $headerItem);
          $responseHeaders['http-protocol'] = $headerItem[0];
          $responseHeaders['http-status-code'] = intval($headerItem[1]);
          $this->responseStatusCode = $responseHeaders['http-status-code'];
        }
      } else {
        $key = trim(substr($headerItem, 0,  $firstColon));
        $value = trim(substr($headerItem, $firstColon + 1));
        $responseHeaders[$key] = $value;
      }
    }
    $this->responseHeadersData = $responseHeaders;
    $responseBody = substr($result, $headerSize);

    if ($result === false) {
      $this->curlErrorMsg = \curl_error($curl);
      $this->curlErrorNo = intval(\curl_errno($curl));
    } else {
      if ($this->isJson) {
        $result = \json_decode($responseBody, true);
        if (!$result) {
          $result = [
            "response" => $responseBody
          ];
        }
      }

      $this->responseData = $result;
    }
    curl_close($curl);
    return $this;
  }
  /**
   * 暂停请求
   *
   * @return integer 暂停结果
   */
  public function pause(): int
  {
    return \curl_pause($this->curlInstance, \CURLPAUSE_ALL);
  }
  /**
   * 获取error信息
   *
   * @return string error信息
   */
  public function error(): string
  {
    return $this->curlErrorMsg ?? "";
  }
  /**
   * 获取error号
   *
   * @return integer|string error号
   */
  public function errorNo(): int|string
  {
    return intval($this->curlErrorNo);
  }
  /**
   * 获取响应的数据
   *
   * @return array|integer|string|boolean 响应的数据
   */
  public function getData(): array|int|string|bool
  {
    return $this->responseData;
  }
  /**
   * 获取响应状态码
   *
   * @return integer
   */
  public function statusCode(): int
  {
    return $this->responseStatusCode;
  }
  /**
   * 获取响应头
   *
   * @return array
   */
  public function responseHeaders(): array
  {
    return $this->responseHeadersData;
  }
}
