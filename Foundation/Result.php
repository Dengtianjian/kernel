<?php

namespace kernel\Foundation;

use kernel\Foundation\App;
use kernel\Foundation\Data\Arr;
use kernel\Foundation\Error;
use kernel\Foundation\HTTP\Response;

/**
 * 调用结果（Result）
 *
 * 用于在服务、方法、类等被调用方返回调用结果时，将「业务结果数据 + HTTP 响应」合二为一：
 * - 被调用方：返回一个 Result 实例，成功时携带数据，失败时携带错误码、错误信息、错误详情
 * - 调用方：通过 `isError()`/`isSuccess()` 判断成败；若不再对错误做进一步处理，
 *   可直接将当前实例 `return` 给控制器，因为它继承自 Response，控制器可原样透传输出
 *
 * 相比「返回 false/true/数字标识」的方案，Result 让被调用方负责构建错误响应，
 * 而把「错误要不要抛出」的决定权留给调用方，便于复用。
 */
class Result extends Response
{
  /**
   * 构建一个调用结果
   *
   * @param mixed $result 结果数据（成功态为业务数据；错误态为错误时的主体数据）
   * @param integer|null $errorStatusCode HTTP 状态码，>299 视为失败并自动进入错误态；null 或不传表示成功
   * @param integer|string $errorCode 错误码
   * @param string $errorMessage 错误信息
   * @param mixed $errorDetails 错误详情（仅开发模式输出）
   */
  public function __construct($result, $errorStatusCode = null, $errorCode = 500, $errorMessage = "error", $errorDetails = null)
  {
    $this->responseData = $result;
    if ($errorStatusCode > 299) {
      $this->error = true;
      // 注意父类 error() 签名为 ($statusCode, $code, $message, $details, $data)
      // $result 作为主体数据放到 data，$errorDetails 放到 details，避免存反
      $this->error($errorStatusCode, $errorCode, $errorMessage, $errorDetails, $result);
    }
  }

  /**
   * 静态工厂：快速创建成功结果
   *
   * @param mixed $data 返回的数据
   * @param int $statusCode HTTP 状态码
   * @param int|string $code 响应码
   * @param string $message 响应信息
   * @return Result
   */
  public static function succeeded($data = null, $statusCode = 200, $code = 200, $message = "ok")
  {
    return new self($data, $statusCode, $code, $message);
  }

  /**
   * 静态工厂：快速创建失败结果
   *
   * @param int $statusCode HTTP 状态码，默认 400
   * @param int|string $code 错误码
   * @param string $message 错误信息
   * @param mixed $details 错误详情
   * @param mixed $result 失败时的主体数据（可选，默认 null）
   * @return Result
   */
  public static function failed($statusCode = 400, $code = "400:FAIL", $message = "error", $details = null, $result = null)
  {
    $response = new self($result, $statusCode, $code, $message, $details);
    // failed() 语义上必为失败：即使传入的状态码 <=299，也强制进入错误态并组装错误字段，
    // 避免出现「由 failed() 创建却是成功态」的边界不一致。
    if (!$response->error) {
      $response->error($statusCode, $code, $message, $details, $result);
    }
    return $response;
  }

  /**
   * 获取成功态的处理结果（无论成败都返回主体数据，请结合 isError()/isSuccess() 使用）
   *
   * @return mixed
   */
  public function result()
  {
    return $this->responseData;
  }

  /**
   * 判断是否为失败结果
   *
   * @return bool
   */
  public function isError()
  {
    return $this->error;
  }

  /**
   * 判断是否为成功结果
   *
   * @return bool
   */
  public function isSuccess()
  {
    return !$this->error;
  }

  /**
   * 追加数据到主体（链式）。主体为数组时做合并，否则追加
   *
   * @param mixed $data 追加的数据
   * @param bool $cover 是否覆盖已有主体数据
   * @return Result
   */
  public function withData($data, $cover = false)
  {
    return $this->addData($data, $cover);
  }

  /**
   * 设置响应信息（链式）
   *
   * @param string $message 响应信息
   * @return Result
   */
  public function withMessage($message)
  {
    $this->responseMessage = $message;
    return $this;
  }

  /**
   * 获取失败的错误信息（成功态返回 null）
   *
   * @return string|null
   */
  public function errorMessage()
  {
    return $this->onlyError("responseMessage");
  }

  /**
   * 获取失败的 HTTP 状态码（成功态返回 null）
   *
   * @return int|null
   */
  public function errorStatusCode()
  {
    return $this->onlyError("responseStatusCode");
  }

  /**
   * 获取失败的错误码（成功态返回 null）
   *
   * @return int|string|null
   */
  public function errorCode()
  {
    return $this->onlyError("responseCode");
  }

  /**
   * 获取失败的错误详情（成功态返回 null）
   * 开发模式下若未显式传入详情，会自动附带调用栈，便于定位问题
   *
   * @return mixed
   */
  public function errorDetails()
  {
    if (!$this->error) return null;
    $details = $this->responseDetails;
    // 开发模式且未显式设置详情时，自动补充调用栈（去掉本方法自身的调用帧）
    if (is_null($details) && App::mode() === "development") {
      $details = $this->buildBacktrace(1);
    }
    return $details;
  }

  /**
   * 构建错误调用栈，并按需裁剪内部方法帧
   *
   * @param int $skipFrames 额外跳过的内部帧数（0 = 保留调用本辅助方法的帧）
   * @return array
   */
  private function buildBacktrace($skipFrames = 0)
  {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    // 去掉 buildBacktrace 自身帧 + 调用方指定要跳过的帧
    return array_slice($trace, 1 + $skipFrames);
  }

  /**
   * 私有辅助：仅在错误态时返回指定响应字段，否则返回 null
   *
   * @param string $field 父类 Response 的受保护属性名
   * @return mixed
   */
  private function onlyError($field)
  {
    if (!$this->error) return null;
    return $this->{$field};
  }

  /**
   * 抛出错误。会抛出 kernel\Foundation\Error 并终止程序
   * 若当前不是错误态，则直接返回实例本身，避免误抛假异常
   *
   * @return Result|void
   */
  public function throwError()
  {
    if (!$this->error) return $this;
    throw new Error($this->responseMessage, $this->responseStatusCode, $this->responseCode, $this->responseDetails);
  }

  /**
   * 条件抛出错误：仅当 $condition 为真且当前为错误态时抛出
   *
   * @param bool $condition 触发条件，默认 true
   * @return Result|void
   */
  public function throwErrorIf($condition = true)
  {
    if ($condition) return $this->throwError();
    return $this;
  }

  /**
   * 获取 HTTP 状态码（无论成败，均返回当前值）
   *
   * @return int
   */
  public function getStatusCode()
  {
    return $this->responseStatusCode;
  }

  /**
   * 获取响应码（无论成败，均返回当前值）
   *
   * @return int|string
   */
  public function getCode()
  {
    return $this->responseCode;
  }

  /**
   * 获取响应信息（无论成败，均返回当前值）
   *
   * @return string
   */
  public function getMessage()
  {
    return $this->responseMessage;
  }

  /**
   * 获取响应数据
   *
   * @param string|null $key 指定键的数据；不传或传 null 返回全部数据
   * @return mixed
   */
  public function getData($key = null)
  {
    if (!is_null($key)) {
      // 键存在（含值为 null / "0" / 空串）时原样返回，键不存在才返回 null
      return Arr::get($this->responseData, $key);
    }
    return $this->responseData;
  }

  /**
   * 判断指定键（支持点号语法）是否存在于主体数据中
   *
   * @param string|null $key 键名，支持点号语法（如 user.profile.name）
   * @return bool
   */
  public function hasData($key = null)
  {
    if (is_null($key)) {
      return !is_null($this->responseData);
    }
    return Arr::has($this->responseData, $key);
  }

  /**
   * 获取结果
   * 有错误时返回默认值；否则返回结果数据（指定键时区分「键存在但为 null」与「键不存在」）
   *
   * @param string|null $key 指定键的数据；不传或传 null 返回全部结果
   * @param mixed $default 错误态、或指定键不存在、或整体数据为空时返回的默认值
   * @return mixed
   */
  public function getResult($key = null, $default = null)
  {
    if ($this->error) return $default;
    if (!is_null($key)) {
      // 键存在（含 null 值）原样返回，仅键不存在才回退默认值
      if (Arr::has($this->responseData, $key)) {
        return Arr::get($this->responseData, $key);
      }
      return $default;
    }
    return is_null($this->responseData) ? $default : $this->responseData;
  }

  /**
   * 获取输出的主体（在开发模式下，错误态未显式设置详情时自动补充调用栈）
   *
   * @return array 结构：statusCode/code/data/message/details（+ 附加主体）
   */
  public function getBody()
  {
    $body = parent::getBody();
    // 错误态 + 开发模式 + 未显式设置详情时，自动附带调用栈，保证 output()/toArray()/toJson() 一致
    if ($this->error && is_null($this->responseDetails) && App::mode() === "development") {
      $body['details'] = $this->buildBacktrace(2); // 去掉 buildBacktrace + getBody 自身帧
    }
    return $body;
  }

  /**
   * 将当前结果序列化为响应体数组结构
   *
   * @return array 结构：statusCode/code/data/message/details（+ 附加主体）
   */
  public function toArray()
  {
    return $this->getBody();
  }

  /**
   * 将当前结果序列化为 JSON 字符串
   *
   * @param int $flags json_encode 的 flags，默认 JSON_UNESCAPED_UNICODE
   * @return string
   * @throws \RuntimeException 当序列化失败（如数据含非法 UTF-8）时抛出，避免静默返回 false
   */
  public function toJson($flags = JSON_UNESCAPED_UNICODE)
  {
    $json = json_encode($this->getBody(), $flags);
    if ($json === false) {
      throw new \RuntimeException("Result::toJson() 序列化失败：" . json_last_error_msg());
    }
    return $json;
  }
}
