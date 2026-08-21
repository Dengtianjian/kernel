<?php

namespace kernel\Foundation\HTTP;

use kernel\Foundation\App;
use kernel\Foundation\Config;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Output;

class Response
{
  /**
   * 是否返回失败
   *
   * @var boolean
   */
  public $error = false;
  /**
   * 响应头
   *
   * @var array
   */
  protected $responseHeaders = [];
  /**
   * 响应数据
   *
   * @var mixed
   */
  protected $responseData = [];
  /**
   * 响应状态码
   *
   * @var integer
   */
  protected $responseStatusCode = 200;
  /**
   * 响应码
   *
   * @var integer
   */
  protected $responseCode = 200;
  /**
   * 响应信息
   *
   * @var string
   */
  protected $responseMessage = "ok";
  /**
   * 响应错误详情，用于开发模式
   *
   * @var mixed
   */
  protected $responseDetails = null;
  /**
   * 增加到响应主体的数据
   *
   * @var array
   */
  protected $responseAddBody = [];
  /**
   * 重置响应主体的数据
   *
   * @var mixed
   */
  protected $responseResetBody = [];
  /**
   * 响应输出的格式
   *
   * @var "json"|"text"|"xml"|"html"
   */
  protected $outputType = NULL;
  /**
   * 响应输出为text格式是，是否需要格式化
   *
   * @var boolean
   */
  protected $formatOutputTypeOfText = false;

  /**
   * 构建响应
   *
   * @param array|null $data 响应的数据
   * @param integer $statusCode HTTP状态码
   * @param integer $code 响应码
   * @param string $message 响应信息
   * @param array $details 响应详情，主要针对报错
   */
  public function __construct($data = null, $statusCode = 200, $code = 200, $message = "ok", $details = [])
  {
    $this->responseStatusCode = $statusCode;
    $this->responseData = $data;
    $this->responseCode = $code;
    $this->responseMessage = $message;
    $this->responseDetails = $statusCode > 299 ? $details : null;
  }

  protected static $interactions = [
    "list" => [],
    "error" => [],
    "success" => [],
    "statusCodes" => [],
    "errorCodes" => [],
    "mixCodes" => []
  ];
  /**
   * 响应时拦截
   * 如果同时传入状态码和错误码，就只有当响应状态码和响应错误码都匹配时才会执行回调函数。  
   * 如果不传入状态码和错误码，只要响应就会执行
   *
   * @param \Closure $callback 回调函数，函数接收一个参数，是Response类或者基于Response的派生类
   * @param int $statusCode 状态码，如果传入该值，只有当响应状态码等于指定状态码时才会执行回调函数
   * @param string|int $errorCode 错误码，如果传入该值，只有当响应错误码等于指定错误码时才会执行回调函数
   * @param string|int $responseType 响应类型，可以传入success（成功），或者error（错误），也可传入1或者0，1代表成功，0代表失败，如果响应是成功的就会调用成功的回调函数，如果是错误的，就会调用错误的回调函数
   * @return self
   */
  static function interaction(
    \Closure $callback,
    $statusCode = null,
    $errorCode = null,
    $responseType = null
  ) {
    if ($statusCode && $errorCode) {
      if (!self::$interactions['mixCodes'][$statusCode]) {
        self::$interactions['mixCodes'][$statusCode] = [];
      }
      if (!self::$interactions['mixCodes'][$statusCode][$errorCode]) {
        self::$interactions['mixCodes'][$statusCode][$errorCode] = [];
      }
      array_push(self::$interactions['mixCodes'][$statusCode][$errorCode], $callback);
    } else if ($statusCode) {
      if (!self::$interactions['statusCodes'][$statusCode]) {
        self::$interactions['statusCodes'][$statusCode] = [];
      }
      array_push(self::$interactions['statusCodes'][$statusCode], $callback);
    } else if ($errorCode) {
      if (!self::$interactions['errorCodes'][$errorCode]) {
        self::$interactions['errorCodes'][$errorCode] = [];
      }
      array_push(self::$interactions['errorCodes'][$errorCode], $callback);
    } else if (!is_null($responseType)) {
      if (is_numeric($responseType)) {
        $responseType = $responseType ? 'success' : 'error';
      }

      array_push(self::$interactions[$responseType], $callback);
    } else {
      array_push(self::$interactions['list'], $callback);
    }

    return self::class;
  }

  /**
   * 设置响应头
   *
   * @param string $key 键
   * @param string $value 值
   * @param boolean $replace 是否替换
   * @return Response
   */
  public function header($key, $value, $replace = true)
  {
    array_push($this->responseHeaders, [
      "key" => $key,
      "value" => $value,
      "replace" => $replace
    ]);

    return $this;
  }
  /**
   * 空响应
   *
   * @param integer $statusCode HTTP状态码
   * @return Response
   */
  public function null($statusCode = 200)
  {
    $this->responseStatusCode = $statusCode;
    $this->responseData = null;
    $this->responseCode = $statusCode;
    $this->responseMessage = $statusCode > 299 ? 'error' : 'ok';
    $this->responseDetails = null;

    return $this;
  }
  /**
   * 错误响应
   *
   * @param integer $statusCode HTTP状态码
   * @param integer|string $code 响应码
   * @param string $message 响应信息
   * @param mixed $data 主体数据
   * @param mixed $details 错误详情
   * @return Response
   */
  public function error($statusCode, $code = 500, $message = "error", $details = [], $data = [])
  {
    $this->error = true;
    $this->responseStatusCode = $statusCode;
    $this->responseData = $data;
    $this->responseCode = $code;
    $this->responseMessage = $message;
    $this->responseDetails = $statusCode > 299 ? $details : null;

    return $this;
  }
  /**
   * 成功响应
   *
   * @param mixed $data 主体数据
   * @param integer $statusCode HTTP状态码
   * @param integer|string $code 响应码
   * @param string $message 响应信息
   * @return Response
   */
  public function success($data, $statusCode = 200, $code = 200, $message = "ok")
  {
    $this->responseStatusCode = $statusCode;
    $this->responseData = $data;
    $this->responseCode = $code;
    $this->responseMessage = $message;
    $this->responseDetails = null;

    return $this;
  }
  /**
   * 设置/获取HTTP状态码
   *
   * @param integer $statusCode
   * @return Response|int
   */
  public function statusCode($statusCode = null)
  {
    if ($statusCode === null) {
      return $this->responseStatusCode;
    }
    $this->responseStatusCode = $statusCode;

    return $this;
  }
  /**
   * 设置主体
   *
   * @param mixed $body 当输出时会直接输出当前设置的值，而不会组合在一起
   * @return Response
   */
  public function setBody($body)
  {
    $this->responseResetBody = $body;

    return $this;
  }
  /**
   * 添加数据到主体
   *
   * @param array $responseBody 主体，最好是关联数组
   * @param boolean $cover 是否覆盖现有的主体
   * @return Response
   */
  public function addBody($responseBody, $cover = false)
  {
    if ($cover) {
      $this->responseAddBody = $responseBody;
    } else {
      unset($responseBody['data']);
      $this->responseAddBody = array_merge($this->responseAddBody, $responseBody);
    }

    return $this;
  }
  /**
   * 添加合并数据到主体数据
   *
   * @param mixed $data 添加的数据
   * @param boolean $cover 是否覆盖已有的主体数据
   * @return Response
   */
  public function addData($data, $cover = false)
  {
    if ($cover || is_null($this->responseData)) {
      $this->responseData = $data;
    } else {
      if (is_array($this->responseData) && is_array($data)) {
        $this->responseData = array_merge($this->responseData, $data);
      } else if (is_string($this->responseData) || is_numeric($this->responseData)) {
        $this->responseData .= $data;
      }
    }

    return $this;
  }
  /**
   * 设置主体数据
   *
   * @param mixed $data 添加的数据
   * @return Response
   */
  public function setData($data)
  {
    $this->responseData = $data;

    return $this;
  }
  /**
   * 输出为json格式的内容
   *
   * @return Response
   */
  public function json()
  {
    $this->outputType = "json";
    return $this;
  }
  /**
   * 输出为xml格式的内容
   *
   * @return Response
   */
  public function xml()
  {
    $this->outputType = "xml";
    return $this;
  }
  /**
   * 输出为文本格式的内容
   *
   * @param boolean $format 是否格式化输出
   * @return Response
   */
  public function text($format = false)
  {
    $this->outputType = "text";
    $this->formatOutputTypeOfText = $format;
    return $this;
  }
  /**
   * 输出为超文本格式的内容
   *
   * @return Response
   */
  public function html()
  {
    $this->outputType = "html";
    return $this;
  }
  /**
   * 重定向
   *
   * @param string $url 重定向的URL
   * @param integer $statusCode HTTP状态码
   * @return Response
   */
  public function redirect($url, $statusCode = 301)
  {
    $this->header("Location", $url, true);
    $this->statusCode($statusCode);

    return $this;
  }
  /**
   * 获取输出的主体
   *
   * @return array
   */
  public function getBody()
  {
    if (is_array($this->responseAddBody)) {
      return array_merge([
        "statusCode" => $this->responseStatusCode,
        "code" => $this->responseCode,
        "data" => $this->getData(),
        "message" => $this->responseMessage,
        "details" => $this->responseDetails,
      ], $this->responseAddBody);
    }
    return $this->responseAddBody;
  }
  /**
   * 获取输出的主体数据
   *
   * @return mixed
   */
  public function getData()
  {
    return $this->responseData;
  }
  protected function interactionOutput()
  {
    //* 无筛选的
    foreach (self::$interactions['list'] as $item) {
      call_user_func_array($item, [$this]);
    }

    //* 只匹配成功的
    if ($this->responseStatusCode > 199 && $this->responseStatusCode < 300) {
      foreach (self::$interactions['success'] as $item) {
        call_user_func_array($item, [$this]);
      }
    }

    //* 只匹配错误的
    if ($this->responseStatusCode > 399) {
      foreach (self::$interactions['error'] as $item) {
        call_user_func_array($item, [$this]);
      }
    }

    //* 只匹配状态码
    if (isset(self::$interactions['statusCodes'][$this->responseStatusCode])) {
      foreach (self::$interactions['statusCodes'][$this->responseStatusCode] as $item) {
        call_user_func_array($item, [$this]);
      }
    }

    //* 只匹配错误码
    if (isset(self::$interactions['errorCodes'][$this->responseStatusCode])) {
      foreach (self::$interactions['errorCodes'][$this->responseStatusCode] as $item) {
        call_user_func_array($item, [$this]);
      }
    }

    //* 状态码错误码同时匹配
    if (isset(self::$interactions['mixCodes'][$this->responseStatusCode]) && isset(self::$interactions['mixCodes'][$this->responseStatusCode][$this->responseCode])) {
      foreach (self::$interactions['mixCodes'][$this->responseStatusCode][$this->responseCode] as $item) {
        call_user_func_array($item, [$this]);
      }
    }
  }
  /**
   * 输出内容，调用该方法会直接exit退出程序
   *
   * @return void
   */
  public function output()
  {
    $this->interactionOutput();

    foreach ($this->responseHeaders as $Header) {
      header($Header['key'] . ":" . $Header['value'], $Header['replace']);
    }
    http_response_code($this->responseStatusCode);

    $body = $this->getBody();
    if ($this->responseResetBody) {
      $body = $this->responseResetBody;
    }
    $data = $this->getData();

    if (getApp()->request()->ajax()) {
      $body['version'] = Config::get("version");
    }
    $outputType = $this->outputType;
    $Accept = getApp()->request()->header->get("Accept");
    if (!$outputType && $Accept) {
      list($type, $format) = explode("/", $Accept);
      if ($format !== "*") {
        $outputType = $format;
      }
    }

    switch ($outputType) {
      case "json":
        header("Content-type:application/json", true);
        print_r(json_encode($body, JSON_UNESCAPED_UNICODE));
        break;
      case "xml":
        header("Content-type:text/xml", true);
        print_r(Arr::toXML($data));
        break;
      case "html":
        header("Content-type:text/html", true);
        print_r($data);
        break;
      case "text":
        if ($this->formatOutputTypeOfText) {
          Output::format($data);
        } else {
          if ($this->responseStatusCode > 299) {
            $detailsText = App::mode() === "development" ? Output::string($this->responseDetails) : "";
            $data = <<<EOT
{$this->responseMessage}\n
{$detailsText}
EOT;
          }
          Output::printContent($data);
        }
        break;
      default:
        Output::printContent(App::mode() === "development" ? $body : $data);
        break;
    }
  }
  public function outputType()
  {
    return $this->outputType;
  }
}
