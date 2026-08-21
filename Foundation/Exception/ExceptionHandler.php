<?php

namespace kernel\Foundation\Exception;

use kernel\Foundation\App;
use kernel\Foundation\Config;
use kernel\Foundation\FileSystem\Path;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\HTTP\Response\ResponseView;
use kernel\Foundation\Log;
use Throwable;

/**
 * 全局异常 / 错误处理器（静态门面）
 *
 * 专门用于 `set_exception_handler()` 与 `set_error_handler()` 回调：
 *
 *   set_exception_handler('kernel\Foundation\Exception\ExceptionHandler::receive');
 *   set_error_handler('kernel\Foundation\Exception\ExceptionHandler::handle', E_ALL);
 *
 * 设计要点：
 *   - 静态门面以兼容 PHP SAPI 直接注册；不存储任何实例状态
 *   - 主流程分两步：判级 → 分派（写日志 + AJAX 输出 JSON 或渲染错误页 → exit）
 *   - 所有内部副作用都包在 try/catch 中，防止 handler 自身抛错被 PHP 钩子再次回调形成死循环
 */
class ExceptionHandler
{
  /**
   * 致命错误级别（命中即写日志 + 输出响应 + exit）
   */
  private static $fatalLevels = [
    E_ERROR,
    E_CORE_ERROR,
    E_COMPILE_ERROR,
    E_USER_ERROR,
    E_PARSE,
    E_RECOVERABLE_ERROR,
  ];

  /**
   * 警告 / 通知级别（命中仅写日志）
   */
  private static $warningLevels = [
    E_WARNING,
    E_CORE_WARNING,
    E_COMPILE_WARNING,
    E_USER_WARNING,
    E_NOTICE,
    E_USER_NOTICE,
    E_DEPRECATED,
    E_USER_DEPRECATED,
  ];

  /**
   * 处理错误信号
   *
   * 11 个形参是 set_error_handler 的最小外接超集（5 个由 PHP 提供 + 6 个业务字段由 receive() 注入）。
   * PHP 钩子只会传前 5 个。
   *
   * @param int $code PHP 错误号
   * @param string $message 错误信息
   * @param string $file 源文件
   * @param int $line 行号
   * @param array $trace 调用栈（数组）
   * @param string|null $traceString 格式化后的调用栈
   * @param Throwable|null $previous 上一个异常（receive 路径才有）
   * @param int $statusCode HTTP 状态码
   * @param int|string $errorCode 业务错误码
   * @param mixed $errorDetails 错误详情
   * @param bool $directlyThrow 是否无视错误级别直接按致命处理（receive 默认 true，set_error_handler 默认 false）
   */
  public static function handle(
    $code = 0,
    $message = "",
    $file = "",
    $line = 0,
    $trace = [],
    $traceString = null,
    $previous = null,
    $statusCode = 500,
    $errorCode = 500,
    $errorDetails = null,
    $directlyThrow = false
  ) {
    try {
      // 1. 判级：receive() 走 here 时强制致命；error_handler 走 here 时按 PHP 错误号判
      if ($directlyThrow) {
        $isFatal = true;
      } else {
        $isFatal = in_array($code, self::$fatalLevels, true);
      }

      $shouldLog = $isFatal || in_array($code, self::$warningLevels, true);

      // 2. 写日志
      if ($shouldLog) {
        self::writeLog($code, $message, $file, $line, $trace, $traceString, $previous, $isFatal);
      }

      // 3. 非致命则到此为止
      if (!$isFatal) {
        return;
      }

      // 4. 致命分支：AJAX 输出 JSON；非 AJAX 渲染错误视图；最后 exit
      if (self::isAjax()) {
        self::respondJson($statusCode, $errorCode, $message, $errorDetails, $code, $file, $line, $trace, $traceString, $previous);
      } else {
        self::renderView($statusCode, $errorCode, $message, $errorDetails);
      }
    } catch (Throwable $inner) {
      // handler 自身抛错时，绝不让 PHP 钩子再次回调 → 退化为直接 PHP 输出 + 强制退出
      fwrite(STDERR, "[ExceptionHandler internal failure] " . $inner->getMessage() . "\n");
    }

    exit(1);
  }

  /**
   * 接受 Throwable，由 set_exception_handler 调用
   *
   * @param Throwable $exception
   * @return void
   */
  public static function receive($exception): void
  {
    if (!$exception instanceof Throwable) {
      return;
    }

    $statusCode = 500;
    $errorCode = 500;
    $errorDetails = null;
    if ($exception instanceof Error) {
      $statusCode = $exception->statusCode;
      $errorCode = $exception->errorCode;
      $errorDetails = $exception->errorDetails;
    }

    self::handle(
      $exception->getCode(),
      $exception->getMessage(),
      $exception->getFile(),
      $exception->getLine(),
      $exception->getTrace(),
      $exception->getTraceAsString(),
      $exception->getPrevious(),
      $statusCode,
      $errorCode,
      $errorDetails,
      true // receive 永远按致命分支处理
    );
  }

  // ---------- private ----------

  /**
   * 安全获取运行模式，配置未装配时默认为 development
   */
  private static function mode(): string
  {
    try {
      return Config::get("mode", "development");
    } catch (\Throwable $e) {
      return "development";
    }
  }

  /**
   * 检测请求是否为 AJAX / JSON 期望
   */
  private static function isAjax(): bool
  {
    try {
      $app = App::getInstance();
    } catch (\Throwable $e) {
      $app = null;
    }
    if (!$app || !method_exists($app, "request")) {
      return false;
    }
    try {
      $request = $app->request();
    } catch (\Throwable $e) {
      return false;
    }
    return $request ? $request->ajax() : false;
  }

  /**
   * 写日志
   */
  private static function writeLog(
    int $code,
    string $message,
    string $file,
    int $line,
    array $trace,
    ?string $traceString,
    ?Throwable $previous,
    bool $isFatal
  ): void {
    $logMethod = $isFatal ? "error" : "warning";
    Log::$logMethod(
      "code={$code} file={$file}:{$line} message={$message}",
      [
        "trace" => $trace,
        "traceString" => $traceString,
        "previous" => $previous,
      ]
    );
  }

  /**
   * AJAX 输出 JSON 错误响应
   */
  private static function respondJson(
    int $statusCode,
    int|string $errorCode,
    string $message,
    mixed $errorDetails,
    int $code,
    string $file,
    int $line,
    array $trace,
    ?string $traceString,
    ?Throwable $previous
  ): void {
    $response = new Response();
    if (self::mode() === "production") {
      $response->error($statusCode ?: 500);
    } else {
      $response->error(
        $statusCode ?: 500,
        $errorCode,
        $message,
        [
          "code" => $code,
          "file" => $file,
          "line" => $line,
          "trace" => $trace,
          "traceString" => $traceString,
          "previous" => $previous,
          "details" => $errorDetails,
        ]
      );
    }
    $response->output();
  }

  /**
   * 非 AJAX 渲染错误视图
   *
   * 渲染逻辑：
   *   1. 优先用应用层 `{$root}/Views/error.php`
   *   2. 退回 kernel 默认 `kernel/Views/error.php`
   *   3. 都找不到则降级为纯文本
   */
  private static function renderView(int $statusCode, int|string $errorCode, string $message, mixed $errorDetails): void
  {
    $appViewDir = Path::root() . "/Views";
    $kernelViewDir = Path::kernelRoot() . "/Views";

    // 1. 优先应用层视图
    try {
      if (is_dir($appViewDir) && file_exists($appViewDir . "/error.php")) {
        $viewResponse = new ResponseView("error", null, $appViewDir, "error");
        $viewResponse->render($appViewDir . "/error.php");
        return;
      }
      // 2. 退回 kernel 视图
      if (is_dir($kernelViewDir) && file_exists($kernelViewDir . "/error.php")) {
        $viewResponse = new ResponseView("error", null, $kernelViewDir, "kernel_page");
        $viewResponse->render($appViewDir . "/error.php");
        return;
      }
    } catch (Throwable $e) {
      // 视图渲染失败时降级为纯文本
    }

    // 3. 降级输出
    http_response_code($statusCode ?: 500);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Error {$statusCode}: {$message}";
  }
}
