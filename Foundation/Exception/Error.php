<?php

namespace kernel\Foundation\Exception;

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

  /**
   * 按 ErrorCode name 直接抛出对应错误
   *
   * 业务方只需告诉框架"抛 USER_NOT_FOUND"，由本方法去 ErrorCode 注册表查
   * 对应的 statusCode/message/errorCode，再构造业务异常抛出。
   *
   * 调用前需先 {@see ErrorCode::load()} 加载错误码配置；name 不存在时抛 500:ErrorCodeNotExist。
   *
   * @param string $name ErrorCode 注册名（如 "USER_NOT_FOUND"）
   * @param mixed $details 业务上下文详情（不入 ErrorCode 注册的，仅 runtime 上下文）
   * @return never
   */
  public static function raise(string $name, mixed $details = null): never
  {
    // 防循环：ErrorCode 若未加载就退化为本地异常
    if (!class_exists(ErrorCode::class, false)) {
      throw new self("错误码未注册：{$name}（ErrorCode 未加载）", 500, "500:ErrorCodeNotExist");
    }
    try {
      $code = ErrorCode::find($name);
    } catch (Error $e) {
      // find() 已抛 500:ErrorCodeNotExist，直接透传（不再二次包装）
      throw $e;
    }
    throw new self($code->message, $code->statusCode, $code->errorCode, $details);
  }
}
