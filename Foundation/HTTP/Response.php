<?php

namespace kernel\Foundation\HTTP;

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
  protected $ResponseHeaders = [];
  /**
   * 响应数据
   *
   * @var mixed
   */
  protected $ResponseData = [];
  /**
   * 响应状态码
   *
   * @var integer
   */
  protected $ResponseStatusCode = 200;
  /**
   * 响应码
   *
   * @var integer
   */
  protected $ResponseCode = 200;
  /**
   * 响应信息
   *
   * @var string
   */
  protected $ResponseMessage = "ok";
  /**
   * 响应错误详情，用于开发模式
   *
   * @var mixed
   */
  protected $ResponseDetails = null;
  /**
   * 增加到响应主体的数据
   *
   * @var array
   */
  protected $ResponseAddBody = [];
  /**
   * 重置响应主体的数据
   *
   * @var mixed
   */
  protected $ResponseResetBody = [];
  /**
   * 响应输出的格式
   *
   * @var json|text|xml
   */
  protected $OutputType = "text";
  /**
   * 响应输出为text格式是，是否需要格式化
   *
   * @var boolean
   */
  protected $FormatOutputTypeOfText = false;

  /**
   * 构建响应
   *
   * @param array $data 响应的数据
   * @param integer $statusCode HTTP状态码
   * @param integer $code 响应码
   * @param string $message 响应信息
   * @param array $details 响应详情，主要针对报错
   */
  public function __construct($data = [], $statusCode = 200, $code = 200000, $message = "ok", $details = [])
  {
    $this->ResponseStatusCode = $statusCode;
    $this->ResponseData = $data;
    $this->ResponseCode = $code;
    $this->ResponseMessage = $message;
    $this->ResponseDetails = $statusCode > 299 ? $details : null;
  }

  /**
   * 设置响应头
   *
   * @param string $key 键
   * @param string $value 值
   * @param boolean $replace 是否替换
   * @return Response
   */
  public function header($key,  $value,  $replace = true)
  {
    array_push($this->ResponseHeaders, [
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
    $this->ResponseStatusCode = $statusCode;
    $this->ResponseData = null;
    $this->ResponseCode = $statusCode;
    $this->ResponseMessage = $statusCode > 299 ? 'error' : 'ok';
    $this->ResponseDetails = null;

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
    $this->ResponseStatusCode = $statusCode;
    $this->ResponseData = $data;
    $this->ResponseCode = $code;
    $this->ResponseMessage = $message;
    $this->ResponseDetails = $statusCode > 299 ? $details : null;

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
  public function success($data, $statusCode = 200, $code = 200000, $message = "ok")
  {
    $this->ResponseStatusCode = $statusCode;
    $this->ResponseData = $data;
    $this->ResponseCode = $code;
    $this->ResponseMessage = $message;
    $this->ResponseDetails = null;

    return $this;
  }
  /**
   * 设置/获取HTTP状态码
   *
   * @param integer $statusCode
   * @return Response
   */
  public function statusCode($statusCode = null)
  {
    if ($statusCode === null) {
      return $this->ResponseStatusCode;
    }
    $this->ResponseStatusCode = $statusCode;

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
    $this->ResponseResetBody = $body;

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
      $this->ResponseAddBody =  $responseBody;
    } else {
      unset($responseBody['data']);
      $this->ResponseAddBody = array_merge($this->ResponseAddBody, $responseBody);
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
    if ($cover || is_null($this->ResponseData)) {
      $this->ResponseData = $data;
    } else {
      if (is_array($this->ResponseData) && is_array($data)) {
        $this->ResponseData = array_merge($this->ResponseData, $data);
      } else if (is_string($this->ResponseData) || is_numeric($this->ResponseData)) {
        $this->ResponseData .= $data;
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
    $this->ResponseData = $data;

    return $this;
  }
  /**
   * 输出为json格式的内容
   *
   * @return Response
   */
  public function json()
  {
    $this->OutputType = "json";
    return $this;
  }
  /**
   * 输出为xml格式的内容
   *
   * @return Response
   */
  public function xml()
  {
    $this->OutputType = "xml";
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
    $this->OutputType = "text";
    $this->FormatOutputTypeOfText = $format;
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
    if (is_array($this->ResponseAddBody)) {
      return array_merge([
        "statusCode" => $this->ResponseStatusCode,
        "code" => $this->ResponseCode,
        "data" => $this->getData(),
        "message" => $this->ResponseMessage,
        "details" => $this->ResponseDetails,
      ], $this->ResponseAddBody);
    }
    return $this->ResponseAddBody;
  }
  /**
   * 获取输出的主体数据
   *
   * @return mixed
   */
  public function getData()
  {
    return $this->ResponseData;
  }
  /**
   * 输出内容，调用该方法会直接exit退出程序
   *
   * @return void
   */
  public function output()
  {
    foreach ($this->ResponseHeaders as $Header) {
      header($Header['key'] . ":" . $Header['value'], $Header['replace']);
    }
    http_response_code($this->ResponseStatusCode);

    $body = $this->getBody();
    if ($this->ResponseResetBody) {
      $body = $this->ResponseResetBody;
    }
    $data = $this->getData();

    if (getApp()->request()->ajax()) {
      $body['version'] = Config::get("version");
    }
    switch ($this->OutputType) {
      case "json":
        header("Content-type:application/json", true);
        print_r(json_encode($body));
        break;
      case "xml":
        header("Content-type:text/xml", true);
        print_r(Arr::toXML($data));
        break;
      case "text":
        if ($this->FormatOutputTypeOfText) {
          Output::format($data);
        } else {
          if ($this->ResponseStatusCode > 299) {
            $detailsText = F_APP_MODE === "development" ? Output::format($this->ResponseDetails) : "";
            $data = <<<EOT
{$this->ResponseMessage}\n
{$detailsText}
EOT;
          }
          Output::printContent($data);
        }
        break;
      default:
        Output::printContent($body);
        break;
    }
  }
}
