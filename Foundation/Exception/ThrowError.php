<?php

namespace gstudio_kernel\Foundation\Exception;

use gstudio_kernel\Foundation\Response;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class ThrowError
{
  public $statusCode = null;
  public $code = null;
  public $message = null;
  public $data = null;
  public $details = null;
  /**
   * 抛出错误
   * 场景：调用函数返回该类，交给调用者去处理返回的错误
   *
   * @param int $statusCode http状态码
   * @param string|int $code 错误码
   * @param string $message 错误信息
   * @param array $data 响应数据
   * @param array $details 错误详情，development环境下才会显示
   */
  public function __construct($statusCode, $code, $message, $data = [], $details = [])
  {
    $this->statusCode = $statusCode;
    $this->code = $code;
    $this->message = $message;
    $this->data = $data;
    $this->details = $details;
  }
  /**
   * 响应错误
   * 调用Response::error响应抛出的错误
   *
   * @return void
   */
  public function response()
  {
    Response::error($this->statusCode, $this->code, $this->message, $this->data, $this->details);
  }
  /**
   * 判断发挥的对象是不是ThrowError类
   *
   * @param mixed $target 检测的对象
   * @return boolean
   */
  public static function is($target)
  {
    return $target instanceof ThrowError;
  }
}
