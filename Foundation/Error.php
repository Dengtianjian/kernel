<?php

namespace kernel\Foundation;

use Exception as GlobalException;

/**
 * 框架业务异常基类
 *
 * 继承自 PHP 全局 \Exception，并额外携带 HTTP 状态码（statusCode）、
 * 业务错误码（errorCode）与错误详情（errorDetails）三字段。
 *
 * 设计目的：让上层 catch (\Exception) 仍能抓到，但运营侧可通过 statusCode/errorCode
 * 把异常区分成不同的 HTTP 响应。
 */
class Error extends GlobalException
{
  /**
   * HTTP 状态码
   *
   * @var integer
   */
  public $statusCode = 500;
  /**
   * 错误码
   *
   * @var integer|string
   */
  public $errorCode = 500;
  /**
   * 错误详情
   *
   * @var mixed
   */
  public $errorDetails = null;
  /**
   * 抛出异常
   *
   * @param string $message 错误信息
   * @param integer $statusCode HTTP状态码
   * @param integer|string $errorCode 错误码
   * @param mixed $errorDetails 错误详情
   */
  public function __construct($message = "Server error", $statusCode = 500, $errorCode = 500, $errorDetails = null)
  {
    $this->code = E_USER_ERROR;
    $this->message = $message;
    $this->statusCode = $statusCode;
    $this->errorCode = $errorCode;
    $this->errorDetails = $errorDetails;
  }
}
