<?php

namespace kernel\Foundation\HTTP\Response;

use kernel\Foundation\Config;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\File;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\Output;

class ServerSentEvent extends Response
{
  /**
   * 事件名称
   *
   * @var string
   */
  protected $ResponseEventName = "message";
  /**
   * 构建SSE响应
   *
   * @param callable $callback 回调函数，只有在该回调函数里面调用输出方法前端才可接收到数据。该回调函数第一个参数接收的是当前响应实例。
   * ```php
   * use kernel\Foundation\HTTP\Response\ServerSentEvent;
   * 
   * new ServerSentEvent(function (ServerSentEvent $Response) {
      $Response->success([
        "time" => time()
      ]);
      $Response->ping();
    });
   * ```
   * @param int $intervalTime 间隔时间，单位：秒
   * @param string $outputType 输出数据类型，可传入json、text、xml
   */
  public function __construct($callback, $intervalTime = 1, $outputType = "json")
  {
    $this->header("X-Accel-Buffering", "no", true);
    $this->header("Content-Type", "text/event-stream", true);
    $this->header("Cache-Control", "no-cache", true);
    $this->OutputType = $outputType;

    while (1) {
      call_user_func($callback, $this);

      while (ob_get_level() > 0) {
        ob_end_flush();
      }
      flush();

      if (connection_aborted()) break;

      sleep($intervalTime);
    }
  }
  /**
   * 成功响应
   *
   * @param mixed $data 主体数据
   * @param string $event 事件名称
   * @param integer $statusCode HTTP状态码
   * @param integer|string $code 响应码
   * @param string $message 响应信息
   * @return Response
   */
  public function success($data, $event = "message", $statusCode = 200, $code = 200000, $message = "ok")
  {
    parent::success($data, $statusCode, $code, $message);
    $this->ResponseEventName = $event;

    $this->output();

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
    parent::error($statusCode, $code, $message, $details, $data);
    $this->ResponseEventName = "error";

    $this->output();

    return $this;
  }
  /**
   * 发送ping事件
   * 自动向前端发送ping名称事件
   *
   * @return Response
   */
  public function ping()
  {
    return $this->success(time(), "ping", 200, 200, "ok");
  }
  public function output()
  {
    foreach ($this->ResponseHeaders as $Header) {
      header($Header['key'] . ":" . $Header['value'], $Header['replace']);
    }

    $body = $this->getBody();
    if ($this->ResponseResetBody) {
      $body = $this->ResponseResetBody;
    }
    $data = $this->getData();

    if (getApp()->request()->ajax()) {
      $body['version'] = Config::get("version");
    }

    $content = null;
    switch ($this->OutputType) {
      case "json":
        $content = json_encode($body, JSON_UNESCAPED_UNICODE);
        break;
      case "xml":
        $content = Arr::toXML($data);
        break;
      default:
        $content = $data;
        break;
    }

    echo "id: " . (uniqid()) . "\n";
    echo "event: {$this->ResponseEventName}\n";
    echo "data: {$content}\n\n";
  }
}
