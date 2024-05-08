<?php

namespace kernel\Foundation\Object;

use kernel\Foundation\ReturnResult\ReturnResult;

/**
 * 能力基础对象  
 * 适用于作为提供功能、能力类的基类
 * 
 * @property boolean $error 是否有错误
 */
class AbilityBaseObject extends BaseObject
{
  /**
   * 是否有错误
   *
   * @var boolean
   */
  protected $error = FALSE;
  /**
   * 错误码
   *
   * @var int
   */
  protected $errorCode = NULL;
  /**
   * 错误信息
   *
   * @var string
   */
  protected $errorMessage = NULL;
  /**
   * HTTP响应状态码
   *
   * @var int
   */
  protected $errorStatusCode = NULL;
  /**
   * 错误详情
   *
   * @var mixed
   */
  protected $errorDetails = NULL;
  /**
   * 错误数据
   *
   * @var mixed
   */
  protected $errorData = NULL;
  /**
   * 错误响应
   *
   * @param integer $statusCode HTTP状态码
   * @param integer|string $code 响应码
   * @param string $message 响应信息
   * @param boolean $return 直接返回ReturnResult
   * @param mixed $data 主体数据
   * @param mixed $details 错误详情
   * @return this
   */
  final protected function setError(
    $statusCode = 500,
    $code = 500,
    $message = "error",
    $return = FALSE,
    $details = [],
    $data = []
  ) {
    $this->error = TRUE;
    $this->errorStatusCode = $statusCode;
    $this->errorCode = $code;
    $this->errorMessage = $message;
    $this->errorDetails = $details;
    $this->errorData = $data;

    if ($return) return $this->return();

    return $this;
  }
  function __get($name)
  {
    return $this->$name;
  }
  /**
   * 设置错误并且该函数会返回指定的值  
   * 与setError不同的是该方法适用于直接return false
   * public function(){
   * \# code
   *  return $this-interrupt(500,500,"服务错误"); // false
   * }
   *
   * @param integer｜ReturnResult $statusCode HTTP状态码
   * @param integer|string $code 响应码
   * @param string $message 响应信息
   * @param mixed $data 主体数据
   * @param mixed $details 错误详情
   * @return false
   */
  final protected function break(
    $statusCode = 500,
    $code = 500,
    $message = "error",
    $details = [],
    $data = []
  ) {
    if ($statusCode instanceof ReturnResult) {
      $this->setError($statusCode->statusCode(), $statusCode->errorCode(), $statusCode->errorMessage(), false, $statusCode->errorDetails(), $statusCode->getData());
    } else {
      $this->setError($statusCode, $code, $message, FALSE, $details, $data);
    }

    return FALSE;
  }
  /**
   * 获取错误信息
   *
   * @return string
   */
  final public function getErrorMessage()
  {
    return $this->errorMessage;
  }
  /**
   * 获取错误码
   *
   * @return int|string
   */
  final public function getErrorCode()
  {
    return $this->errorCode;
  }
  /**
   * 获取错误状态码
   *
   * @return int
   */
  final public function getStatusCode()
  {
    return $this->errorStatusCode;
  }
  /**
   * 获取错误详情
   *
   * @return mixed
   */
  final public function getErrorDetails()
  {
    return $this->errorDetails;
  }
  /**
   * 获取错误数据
   *
   * @return mixed
   */
  final public function getErrorData()
  {
    return $this->errorData;
  }
  /**
   * 返回错误
   *
   * @return ReturnResult
   */
  final public function return()
  {
    return (new ReturnResult(FALSE))->error($this->errorStatusCode, $this->errorCode, $this->errorMessage, $this->errorDetails, $this->errorData);
  }
}
