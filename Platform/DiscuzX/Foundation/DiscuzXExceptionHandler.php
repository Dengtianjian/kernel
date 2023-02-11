<?php

namespace kernel\Platform\DiscuzX\Foundation;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Config;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\File;
use kernel\Foundation\Log;
use kernel\Foundation\Output;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\HTTP\Response\ResponseView;

/**
 * DiscuzX异常处理类
 */

class DiscuzXExceptionHandler
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
   * @param array $previous 前一个Throwable
   * @param array $statusCode HTTP响应状态码
   * @param array $errorCode 响应错误码 
   * @param mixed $errorDetails 响应详情
   * @return void
   */
  public static function handle($code = 0, $message = "", $file = "", $line = null, $trace = "", $traceString = NULL, $previous = null, $statusCode = 500, $errorCode = 500, $errorDetails = null)
  {
    $ErrorLevels = [E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_CORE_WARNING, E_COMPILE_WARNING, E_WARNING]; //* 写入日志的错误级别 包含 致命性错误级别
    $DeadlyLevels = [E_ERROR, E_USER_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]; //* 致命性错误级别
    if ($traceString) {
      $traceString = \explode(\PHP_EOL, $traceString);
    }

    if (in_array($code, $ErrorLevels)) {
      Log::record([
        "errno" => $code,
        "errstr" => $message,
        "errfile" => $file,
        "errline" => $line,
        "trace" => $trace,
        "error" => $errorDetails
      ]);
    }
    if (in_array($code, $DeadlyLevels)) {
      $ajax = $GLOBALS['App']->request->ajax();
      if ($ajax) {
        $Response = new Response();
        if (Config::get("mode") === "production") {
          $Response->error($statusCode, $errorCode, "SERVER_ERROR");
        } else {
          $Response->error($statusCode, $errorCode, $message, null, $errorDetails ?: [
            "code" => $code,
            "message" => $message,
            "file" => $file,
            "line" => $line,
            "trace" => $trace,
            "previous" => $previous
          ]);
        }
        $Response->output();
        exit;
      } else {
        $View = new ResponseView("error", [], "Views", "page", F_KERNEL_ROOT);
        $View->render(File::genPath(F_KERNEL_ROOT, "Views", "error.php"), [
          "code" => $code, "message" => $message, "file" => $file, "line" => $line, "trace" => $trace, "traceString" => $traceString, "previous" => $previous,
          "error" => $errorDetails
        ]);
        $View->output();
        exit;
      }
    }
    Output::debug([
      "code" => $code,
      "message" => $message,
      "file" => $file,
      "line" => $line,
      "trace" => debug_backtrace(),
      "previous" => $previous,
      "debug" => 1,
      "details" => $errorDetails
    ]);
  }
  /**
   * 接收Exception类的参数
   *
   * @param object $exception php Exception类的异常，包括继承自Exception的
   * @return void
   */
  public static function receive($exception)
  {
    $statusCode = 500;
    $errorCode = 500;
    $errorDetails = null;
    if ($exception instanceof Exception) {
      $statusCode = $exception->statusCode;
      $errorCode = $exception->errorCode;
      $errorDetails = $exception->errorDetails;
    }

    $code = $exception->getCode();
    $message = $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    $trace = $exception->getTrace();
    $previous = $exception->getPrevious();
    $traceString = $exception->getTraceAsString();
    self::handle($code, $message, $file, $line, $trace, $traceString, $previous, $statusCode, $errorCode, $errorDetails);
  }
}
