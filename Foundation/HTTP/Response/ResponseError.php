<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\HTTP\Response;

class ResponseError extends Response
{
  /**
   * 构建错误响应
   * 继承于Response
   *
   * @param array $data 响应的数据
   * @param integer $statusCode HTTP状态码
   * @param integer|string $code 响应码
   * @param string $message 响应信息
   * @param array $details 响应详情，主要针对报错
   * @inherits Response
   */
  public function __construct($statusCode, $code = 500, $message = "error", $details = [], $data = [])
  {
    $this->error($statusCode, $code, $message, $details, $data);
  }
}
