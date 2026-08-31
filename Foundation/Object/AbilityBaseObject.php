<?php

namespace kernel\Foundation\Object;

use kernel\Foundation\Result;

/**
 * 能力基础对象
 * 适用于作为提供功能、能力类的基类，为实例提供一套统一的"错误状态机制"
 *
 * 用法（典型流程）：
 *   1. 在方法内部遇到失败时，用 break() 记录错误状态并中断当前方法（返回 false）；
 *   2. 需要对外输出结果时，用 return() 把当前错误状态打包成一个 Result 交给调用方；
 *   3. 调用方可通过 isError()/getError() 或 Result 的判态方法消费结果。
 *
 * 与 Result 的分工：
 *   - AbilityBaseObject 是"实例级错误状态机"，用于在方法执行过程中累积并中断错误；
 *   - Result 是"独立值对象"，用于承载一次调用的最终结果（成功或失败），供调用方消费。
 *   - 两者不冲突、互补：内部用 break() 设状态 + 中断，末尾用 return() 打包成 Result 输出。
 *
 * 继承说明：纯静态 Service 类不应依赖这套实例错误机制（静态上下文无 $this）。
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
  protected $error = false;
  /**
   * 错误码
   *
   * @var int
   */
  protected $errorCode = null;
  /**
   * 错误信息
   *
   * @var string
   */
  protected $errorMessage = null;
  /**
   * HTTP响应状态码
   *
   * @var int
   */
  protected $errorStatusCode = null;
  /**
   * 错误详情
   *
   * @var mixed
   */
  protected $errorDetails = null;
  /**
   * 错误数据
   *
   * @var mixed
   */
  protected $errorData = null;
  /**
   * 记录错误状态
   *
   * 将实例置为错误态，并写入错误字段。仅供类内部调用（通常由 break()/forwardBreak() 触发），
   * 一般不建议在子类中直接调用——需要"记录错误并中断"时请使用 break()。
   *
   * @param integer $statusCode HTTP状态码
   * @param integer|string $code 响应码
   * @param string $message 响应信息
   * @param mixed $details 错误详情
   * @param mixed $data 主体数据
   * @return AbilityBaseObject 返回 $this 以便链式调用
   */
  final protected function setError(
    $statusCode = 500,
    $code = 500,
    $message = "error",
    $details = [],
    $data = []
  ) {
    $this->error = true;
    $this->errorStatusCode = $statusCode;
    $this->errorCode = $code;
    $this->errorMessage = $message;
    $this->errorDetails = $details;
    $this->errorData = $data;

    return $this;
  }
  /**
   * 魔术读取方法
   *
   * 允许外部以属性方式读取 protected 错误字段（如 Controller 中的 $this->platform->error）。
   * 仅对真实存在的属性生效，访问不存在的属性返回 null 而非触发警告。
   *
   * @param string $name 属性名
   * @return mixed
   */
  function __get($name)
  {
    if (property_exists($this, $name)) {
      return $this->$name;
    }

    return null;
  }
  /**
   * 魔术 isset 判断
   *
   * 与 __get 配套，保证 isset($obj->error) 等判断行为一致。
   *
   * @param string $name 属性名
   * @return boolean
   */
  function __isset($name)
  {
    return property_exists($this, $name) && isset($this->$name);
  }
  /**
   * 记录错误并中断
   *
   * 与 setError 不同，该方法在记录错误后始终返回 false，适合直接在方法中
   * `return $this->break(...)` 使用，以便"记录错误 + 中断当前方法"一步完成：
   *
   *   public function upload()
   *   {
   *     // ...
   *     if (!$ok) {
   *       return $this->break(500, 500, "服务错误"); // 记录错误并返回 false
   *     }
   *   }
   *
   * 当第一个参数为 Result 时，会从其错误态字段中提取错误信息；若该 Result 处于
   * 成功态（无错误），则不会污染当前错误状态，直接返回 false。
   *
   * @param integer|Result $statusCode HTTP状态码，或一个 Result 实例
   * @param integer|string $code 响应码
   * @param string $message 响应信息
   * @param mixed $details 错误详情
   * @param mixed $data 主体数据
   * @return false 始终返回 false
   */
  final protected function break(
    $statusCode = 500,
    $code = 500,
    $message = "error",
    $details = [],
    $data = []
  ) {
    if ($statusCode instanceof Result) {
      // 仅当传入的 Result 处于错误态时才设置错误，避免用成功态 Result 污染错误状态
      if (!$statusCode->isError()) {
        return false;
      }
      $this->setError($statusCode->errorStatusCode(), $statusCode->errorCode(), $statusCode->errorMessage(), $statusCode->errorDetails(), $statusCode->getData());
    } else {
      $this->setError($statusCode, $code, $message, $details, $data);
    }

    return false;
  }
  /**
   * 转发当前已记录的错误并中断
   *
   * 适用于"当前实例已经记录过错误，需要再次中断流程"的场景——直接转发当前错误字段，
   * 避免经 Result 中转的冗余往返。等价于 `break()` 传入当前错误状态。
   * 若当前实例尚未设置错误，则直接返回 false，不构造"空错误"。
   *
   * @return false 始终返回 false
   */
  final protected function forwardBreak()
  {
    if (!$this->error) {
      return false;
    }

    return $this->break($this->errorStatusCode, $this->errorCode, $this->errorMessage, $this->errorDetails, $this->errorData);
  }
  /**
   * 获取错误信息
   *
   * 无错误时为 null。
   *
   * @return string|null
   */
  final public function getErrorMessage()
  {
    return $this->errorMessage;
  }
  /**
   * 获取错误码
   *
   * 无错误时为 null。
   *
   * @return int|string|null
   */
  final public function getErrorCode()
  {
    return $this->errorCode;
  }
  /**
   * 获取错误 HTTP 状态码
   *
   * 无错误时为 null。
   *
   * 注意：语义上等价于 Result::errorStatusCode()（仅错误态有效），
   * 不要与 Result::getStatusCode()（无论成败都返回 responseStatusCode）混淆。
   *
   * @return int|null
   */
  final public function getStatusCode()
  {
    return $this->errorStatusCode;
  }
  /**
   * 获取错误详情
   *
   * 无错误时为 null。
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
   * 无错误时为 null。
   *
   * @return mixed
   */
  final public function getErrorData()
  {
    return $this->errorData;
  }
  /**
   * 获取聚合错误信息
   *
   * 一次性返回全部错误字段的关联数组；无错误时返回 null。
   *
   * @return array{code:mixed,message:mixed,statusCode:mixed,details:mixed,data:mixed}|null
   */
  final public function getError()
  {
    if (!$this->error) {
      return null;
    }

    return [
      "code" => $this->errorCode,
      "message" => $this->errorMessage,
      "statusCode" => $this->errorStatusCode,
      "details" => $this->errorDetails,
      "data" => $this->errorData,
    ];
  }
  /**
   * 将当前错误状态打包为 Result 返回
   *
   * 通常在方法末尾调用，把累积的错误状态输出为一个 Result 交给调用方：
   *
   *   public function upload()
   *   {
   *     if (!$ok) return $this->break(500, 500, "失败");
   *     // ...
   *     return $this->return(); // 有错误 → 错误态 Result；无错误 → 成功态 Result
   *   }
   *
   * 若当前未处于错误态，则返回成功态 Result（Result::succeeded），避免构造"假错误"。
   *
   * @return Result
   */
  final public function return()
  {
    // 无错误状态时返回成功 Result，避免构造 errorStatusCode 为 null 的"假错误" Result
    if (!$this->error) {
      return Result::succeeded($this->errorData);
    }

    return (new Result(false))->error($this->errorStatusCode, $this->errorCode, $this->errorMessage, $this->errorDetails, $this->errorData);
  }
  /**
   * 判断是否处于错误状态
   *
   * 等价于读取 $this->error 属性（也可通过 __get 以 $obj->error 方式访问）。
   *
   * @return boolean
   */
  final public function isError()
  {
    return $this->error;
  }
  /**
   * 重置错误状态
   *
   * 将全部错误字段清空并回到非错误态，便于复用同一个实例进行多次操作。
   *
   * @return AbilityBaseObject 返回 $this 以便链式调用
   */
  final public function resetError()
  {
    $this->error = false;
    $this->errorCode = null;
    $this->errorMessage = null;
    $this->errorStatusCode = null;
    $this->errorDetails = null;
    $this->errorData = null;

    return $this;
  }
}
