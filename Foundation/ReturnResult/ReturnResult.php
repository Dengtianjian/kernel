<?php

namespace kernel\Foundation\ReturnResult;

use Error;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\HTTP\Response;

class ReturnResult extends Response
{
  /**
   * 返回结果
   * 主要用于调用服务或者类方法时返回错误、处理结果，如果就单纯的返回false、true或者数字标识，调用方还得根据结果去处理、抛出错误，不利于复用，现在是被调用方去返回错误响应，但是调用方决定这个错误要不要抛出
   * 如果是返回了该实例，调用方根据业务需求，如果不用再根据错误进一步处理，就直接在控制器返回当前实例即可，因为是继承于Response类
   *
   * @param mixed $result 结果数据
   * @param boolean $error 是否处理失败，报错
   * @param string $errorMessage 错误信息
   * @param integer $errorStatusCode 错误HTTP状态码
   * @param integer $errorCode 错误码
   * @param mixed $errorDetails 错误详情
   */
  public function __construct($result, $errorStatusCode = null, $errorCode = 500,  $errorMessage = "error", $errorDetails = null)
  {
    $this->ResponseData = $result;
    if ($errorStatusCode > 299) {
      $this->error = true;
      $this->error($errorStatusCode, $errorCode, $errorMessage, $result, $errorDetails);
    }
  }
  /**
   * 获取成功的处理结果
   *
   * @return mixed
   */
  public function result()
  {
    return $this->ResponseData;
  }
  /**
   * 获取失败的错误信息
   *
   * @return string
   */
  public function errorMessage()
  {
    if (!$this->error) return null;
    return $this->ResponseMessage;
  }
  /**
   * 获取失败的HTTP状态码
   *
   * @return int
   */
  public function errorStatusCode()
  {
    if (!$this->error) return null;
    return $this->ResponseStatusCode;
  }
  /**
   * 获取失败的错误码
   *
   * @return string|int
   */
  public function errorCode()
  {
    if (!$this->error) return null;
    return $this->ResponseCode;
  }
  /**
   * 获取失败的错误详情
   *
   * @return mixed
   */
  public function errorDetails()
  {
    if (!$this->error) return null;
    return $this->ResponseDetails;
  }
  /**
   * 抛出错误。会调用Exception类，并且终止程序，抛出错误
   *
   * @return void
   */
  public function throwError()
  {
    throw new Exception($this->ResponseMessage, $this->ResponseStatusCode, $this->ResponseCode, $this->ResponseDetails);
  }
  /**
   * 获取响应数据
   *
   * @param string $key 指定键的数据，不传即返回全部
   * @return mixed
   */
  public function getData($key = null)
  {
    if ($key) {
      return $this->ResponseData[$key];
    }
    return $this->ResponseData;
  }
}
