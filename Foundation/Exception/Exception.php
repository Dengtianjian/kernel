<?php

namespace kernel\Foundation\Exception;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Config as Config;
use kernel\Foundation\Output;
use kernel\Foundation\Response;
use kernel\Foundation\View;

/**
 * 异常处理类
 */

class Exception
{
  /**
   * 处理异常
   * 接收的参数和原生抛出的Exception类一样
   *
   * @param integer $code 异常码
   * @param string $message 异常信息
   * @param string $file  异常所在文件
   * @param integer $line 异常所在文件的行数
   * @param string $trace 异常堆栈
   * @param string $traceString 异常堆栈的字符串信息
   * @param array $previous
   * @return void
   */
  public static function handle($code = 0, $message = "", $file = "", $line = null, $trace = "", $traceString = NULL, $previous = null)
  {
    global $App;
    $traceString = \explode(\PHP_EOL, $traceString);
    if ($App->router === NULL || $App->router['type'] === "view") {
      if (Config::get("mode") === "production") {
        View::kernelPage("error", [
          "code" => $code, "message" => $message, "file" => $file, "line" => $line, "trace" => $trace, "traceString" => $traceString, "previous" => $previous
        ]);
      } else {
        View::kernelPage("error", [
          "code" => $code, "message" => $message, "file" => $file, "line" => $line, "trace" => $trace, "traceString" => $traceString, "previous" => $previous
        ]);
      }
    } else {
      Response::error(500, "ServerExceptionError:500000", "服务器错误", [], [
        "code" => $code,
        "message" => $message,
        "file" => $file,
        "line" => $line,
        "trace" => $trace,
        "previous" => $previous
      ]);
    }
  }
  /**
   * 接收Exception类的参数
   *
   * @param object $exception php Exception类的异常，包括继承自Exception的
   * @return void
   */
  public static function receive($exception)
  {
    $code = $exception->getCode();
    $message = $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    $trace = $exception->getTrace();
    $previous = $exception->getPrevious();
    $traceString = $exception->getTraceAsString();
    self::handle($code, $message, $file, $line, $trace, $traceString, $previous);
  }
  /**
   * 抛出异常，类似throw
   *
   * @param string $message 异常信息
   * @return void
   */
  public static function out($message)
  {
    self::handle(0, $message);
  }
}
