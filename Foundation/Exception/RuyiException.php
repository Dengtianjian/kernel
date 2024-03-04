<?php

namespace kernel\Foundation\Exception;

class RuyiException extends Exception
{
  /**
   * HTTP状态码
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
   * @param integer $statusCode HTTP状态码
   * @param integer|string $errorCode 错误码
   * @param string $message 错误信息
   * @param mixed $errorDetails 错误详情
   */
  public function __construct($message = "Server error", $statusCode = 500, $errorCode = 500, $errorDetails = null, $details = null)
  {
    $this->code = E_USER_ERROR;
    $this->message = $message;
    $this->statusCode = $statusCode;
    $this->errorCode = $errorCode;
    $this->errorDetails = $errorDetails;
  }
}
