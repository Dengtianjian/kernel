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
 * 调用链与分流：
 *
 *   PHP 未捕获异常 → receive() ─┐
 *                               ├→ handle() → 判级 → 写日志 →（致命时）输出响应 → exit(1)
 *   PHP 错误 / 警告 → handle() ─┘
 *
 * 设计要点：
 *   - 静态门面以兼容 PHP SAPI 直接注册；不存储任何实例状态
 *   - 主流程分两步：判级 → 分派（写日志 + 期望 JSON 则输出 JSON 或渲染错误页 → exit）
 *   - 所有内部副作用都包在 try/catch 中，防止 handler 自身抛错被 PHP 钩子再次回调形成死循环
 *   - 不假设运行环境完整：App 未实例化、Config 未装配、Request 不可用时都要能兜底输出
 *
 * @see \kernel\Foundation\Exception\Error 业务异常（可携带 HTTP 状态码与业务错误码）
 */
class ExceptionHandler
{
  /**
   * 致命错误级别（命中即写日志 + 输出响应 + exit）
   *
   * 判定用严格比较 `in_array(..., true)`；两张表都不在的错误号会被静默忽略（既不写日志也不输出）。
   *
   * @var int[]
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
   * 警告 / 通知级别（命中仅写日志，不中断请求）
   *
   * 统一由 handle() 落到 `Log::warning()`（见 writeLog()），用于保留线上问题的线索而不影响响应。
   *
   * @var int[]
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
   * 处理错误信号（错误处理回调 + 内部统一分派入口）
   *
   * 两个调用来源：
   *   1. PHP 的 `set_error_handler()` 直接回调 —— 只会传前 4 个形参（$code/$message/$file/$line），
   *      其余取默认值，此时 $directlyThrow 为 false，按 PHP 错误号判级；
   *   2. `receive()` 内部调用 —— 11 个形参全部由业务显式传入，且 $directlyThrow 为 true（一律按致命处理）。
   *
   * 处理顺序：判级 → 命中日志级别则写日志 → 非致命直接 return → 致命时按客户端期望输出 JSON 或渲染错误页 → exit(1)。
   *
   * @param int $code PHP 错误号（如 E_WARNING）；异常路径下为 `Throwable::getCode()`
   * @param string $message 错误信息
   * @param string $file 出错源文件
   * @param int $line 出错行号
   * @param array $trace 调用栈数组。注意：经 set_error_handler 触发时，该位由 PHP 传入的内容在不同
   *                     PHP 版本下并不一致（PHP 5/7 为 errcontext，PHP 8 起不再传入），故仅作日志参考；
   *                     只有 receive() 路径传入的才是真正的调用栈
   * @param string|null $traceString 格式化后的调用栈字符串（`Throwable::getTraceAsString()`）
   * @param Throwable|null $previous 链式异常的上一个异常（仅 receive 路径有值）
   * @param int $statusCode HTTP 状态码，默认 500
   * @param int|string $errorCode 业务错误码，默认 500
   * @param mixed $errorDetails 错误详情（`Error` 取其 errorDetails，其它异常取调用栈）
   * @param bool $directlyThrow 是否无视错误级别直接按致命处理（receive 默认 true，set_error_handler 默认 false）
   * @return void 非致命时直接返回；致命时以 exit(1) 终止，不会把控制权交还给调用方
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

      // 致命错误 与 警告/通知/废弃 类错误都要留痕，其余错误号忽略
      $shouldLog = $isFatal || in_array($code, self::$warningLevels, true);

      // 2. 写日志（致命记 error 级别，其余记 warning 级别）
      if ($shouldLog) {
        self::writeLog($code, $message, $file, $line, $trace, $traceString, $previous, $isFatal);
      }

      // 3. 非致命则到此为止：不影响响应，交还控制权
      if (!$isFatal) {
        return;
      }

      // 4. 致命分支：客户端期望 JSON 则输出 JSON；否则渲染错误视图；最后 exit
      // 期望类型取自 Request::preferredOutputType()（依据 Content-Type / Accept，返回 json|xml|html|text|null），
      // 探测失败（App 未实例化、Request 取用异常）时按「非 JSON」处理，保证任何情况下都有兜底输出
      $app = App::getInstance();
      $expectsJson = false;
      if ($app) {
        try {
          $request = $app->request();
          $expectsJson = $request && $request->preferredOutputType() === "json";
        } catch (\Throwable $e) {
          // 探测异常不影响兜底：退化为 HTML 视图路径
          $expectsJson = false;
        }
      }
      if ($expectsJson) {
        self::respondJson($statusCode, $errorCode, $message, $errorDetails, $code, $file, $line, $trace, $traceString, $previous);
      } else {
        self::renderView($statusCode, $errorCode, $message, $errorDetails, $code, $file, $line, $trace, $traceString, $previous);
      }
    } catch (Throwable $inner) {
      // handler 自身抛错时，绝不让 PHP 钩子再次回调（否则 handler → 抛错 → handler 死循环）
      // → 退化为直接写标准错误 + 强制退出。
      // 注意：STDERR 常量仅在 CLI SAPI 下定义，Web 环境下未定义（PHP 7 会告警、PHP 8 会抛 Error），
      // 所以该兜底分支必须保持简单，不再做任何可能抛错的调用。
      fwrite(STDERR, "[ExceptionHandler internal failure] " . $inner->getMessage() . "\n");
    }

    // 无论走完哪条分支都终止请求，避免错误响应之后继续执行后续业务代码
    exit(1);
  }

  /**
   * 接受 Throwable，由 set_exception_handler 调用
   *
   * 从异常对象提取信息后转交 handle()，并以 `$directlyThrow = true` 强制走致命分支
   * （能冒到这里说明业务没有处理它，不再区分错误级别）。
   *
   * 状态码 / 业务码的来源：
   *   - `kernel\Foundation\Exception\Error`（同命名空间，无需 use）→ 取其 statusCode / errorCode / errorDetails；
   *   - 其它异常 → 统一 500 / 500，并把调用栈放进 $errorDetails。
   *
   * @param Throwable $exception 未捕获的异常或错误；传入非 Throwable 时直接忽略（防御 PHP 钩子传入意外值）
   * @return void 实际由 handle() 以 exit(1) 结束
   */
  public static function receive($exception)
  {
    if (!$exception instanceof Throwable) {
      return;
    }

    $statusCode = 500;
    $errorCode = 500;
    $errorDetails = null;
    if ($exception instanceof Error) {
      // 业务异常：状态码与错误码由抛出方决定
      $statusCode = $exception->statusCode;
      $errorCode = $exception->errorCode;
      $errorDetails = $exception->errorDetails;
    } else {
      // 其它异常：无业务码可用，把调用栈作为详情回传，便于排查
      $errorDetails = $exception->getTrace();
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
   *
   * 读 `Config::get("mode")`；读取失败（Config 未初始化等）时按 development 处理 ——
   * 宁可多回调试信息，也不因配置缺失在异常处理过程中再抛一次错。
   *
   * @return string "production" 表示只回状态码；"development" 表示回传完整错误详情
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
   * 写日志
   *
   * 按级别动态落到 `Log::error()` / `Log::warning()`（`Log::$logMethod(...)` 为动态静态方法调用），
   * 正文为单行摘要，调用栈与异常对象放在 context 中交给 Log 处理（避免大对象被提前字符串化）。
   *
   * @param int|string $code 错误号 / 业务错误码
   * @param string $message 错误信息
   * @param string $file 出错源文件
   * @param int $line 出错行号
   * @param array $trace 调用栈数组
   * @param string|null $traceString 格式化后的调用栈字符串
   * @param Throwable|null $previous 链式异常的上一个异常
   * @param bool $isFatal 是否致命（决定 error 还是 warning 级别）
   * @return void 写入结果由 Log 内部处理，此处不关心
   */
  private static function writeLog(
    $code,
    string $message,
    string $file,
    int $line,
    array $trace,
    $traceString,
    $previous,
    bool $isFatal
  ) {
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
   * 输出 JSON 错误响应（客户端期望 JSON 时）
   *
   * - production：只回 HTTP 状态码，不回任何调试细节；
   * - 其它模式：一并回传业务错误码、message 以及 code/file/line/trace/traceString/previous/details。
   *
   * 由 `Response::error()` 组装后立即 `output()` 输出，不返回；$statusCode 为空时兜底为 500。
   *
   * @param int $statusCode HTTP 状态码
   * @param int|string $errorCode 业务错误码
   * @param string $message 错误信息
   * @param mixed $errorDetails 错误详情
   * @param int $code PHP 错误号 / 异常码
   * @param string $file 出错源文件
   * @param int $line 出错行号
   * @param array $trace 调用栈数组
   * @param string|null $traceString 格式化后的调用栈字符串
   * @param Throwable|null $previous 链式异常的上一个异常
   * @return void 直接输出响应体
   */
  private static function respondJson(
    $statusCode,
    $errorCode,
    string $message,
    $errorDetails,
    $code,
    string $file,
    int $line,
    array $trace,
    $traceString,
    $previous
  ) {
    $response = new Response();
    if (self::mode() === "production") {
      // 生产环境不泄露内部细节，只给出状态码
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
   * 渲染错误视图（客户端不期望 JSON 时）
   *
   * 期望优先级：
   *   1. 应用层视图 `{App root}/Views/5xx.php`
   *   2. 退回 kernel 默认视图 `{kernel root}/Views/5xx.php`
   *   3. 都找不到则降级为纯文本 `Error {statusCode}: {message}`
   *
   * 实现现状（与上面的期望不一致，调整前请先确认）：
   *   - 第 1、2 步的 `file_exists()` 判断的是 `5xx.php`，但传给 ResponseView 的视图名是 `error`，
   *     `ResponseView::page()` 会拼成 `.../Views/error.php`；而仓库里只提供 `5xx.php`，
   *     所以构造 ResponseView 时就会抛 Error，被下方 catch 吞掉后**直接进入第 3 步**；
   *   - 第 1 步把已经是绝对路径的 `$appViewDir` 又当作「相对基准目录」传入，拼出的路径必然不存在；
   *   - 第 1 步调用的是静态方法 `ResponseView::render()`（返回渲染后的字符串），且没有输出该返回值，
   *     因此即便不抛异常也不会产生响应体。
   *
   * 结论：当前版本下该方法的失败兜底实际总是走第 3 步（纯文本 + `$statusCode`）。
   *
   * @param int $statusCode HTTP 状态码
   * @param int|string $errorCode 业务错误码（仅第 2 步作为视图数据使用）
   * @param string $message 错误信息（仅第 2、3 步使用）
   * @param mixed $errorDetails 错误详情（仅第 2 步作为视图数据使用）
   * @param int $code PHP 错误号 / 异常码（仅第 2 步作为视图数据使用）
   * @param string $file 出错源文件（仅第 2 步作为视图数据使用）
   * @param int $line 出错行号（仅第 2 步作为视图数据使用）
   * @param array $trace 调用栈数组（仅第 2 步作为视图数据使用）
   * @param string|null $traceString 格式化后的调用栈字符串（仅第 2 步作为视图数据使用）
   * @param Throwable|null $previous 链式异常的上一个异常（仅第 2 步作为视图数据使用）
   * @return void 直接输出内容（视图或纯文本）
   */
  private static function renderView(
    $statusCode,
    $errorCode,
    string $message,
    $errorDetails,
    $code,
    string $file,
    int $line,
    array $trace,
    $traceString,
    $previous
  ) {
    $appViewDir = Path::root() . "/Views";
    $kernelViewDir = Path::kernelRoot() . "/Views";

    // 1. 优先应用层视图
    try {
      if (is_dir($appViewDir) && file_exists($appViewDir . "/5xx.php")) {
        $viewResponse = new ResponseView("error", null, $appViewDir, "error");
        $viewResponse->render($appViewDir . "/5xx.php");
        return;
      }
      // 2. 退回 kernel 视图
      if (is_dir($kernelViewDir) && file_exists($kernelViewDir . "/5xx.php")) {
        $viewResponse = new ResponseView("error", [
          "errorCode" => $errorCode,
          "code" => $code,
          "message" => $message,
          "file" => $file,
          "line" => $line,
          "trace" => $trace,
          "traceString" => $traceString,
          "previous" => $previous,
          "details" => $errorDetails,
        ], "Views", "kernel_page", Path::kernelRoot());
        $viewResponse->statusCode($statusCode);
        $viewResponse->output();
        return;
      }
    } catch (Throwable $e) {
      // 视图渲染失败时降级为纯文本
    }

    // 3. 降级输出：不依赖任何视图与模板引擎，保证错误至少能被看到
    http_response_code($statusCode ?: 500);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Error {$statusCode}: {$message}";
  }
}
