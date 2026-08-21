<?php

namespace kernel\Foundation\Exception;

use kernel\Foundation\Exception\Error;

/**
 * 错误码注册器（门面）
 *
 * 提供静态注册表：把业务错误码按 name 注册到进程内静态池，提供 find/exists/register/clear 等
 * 查询与维护方法。所有错误码对象统一为 {@see ErrorCode} 真类，便于 IDE 提示与类型推断。
 *
 * 典型用法：
 *
 *     ErrorCode::load($appRoot . '/Configs/errorCodes.php');
 *     throw new Error("用户不存在", 404, ErrorCode::find('USER_NOT_FOUND')->errorCode, ErrorCode::find('USER_NOT_FOUND'));
 *
 * 错误码文件要求 `return array<string, ErrorCode>`（每个元素都是 ErrorCode 实例）。
 */
class ErrorCode
{
  /**
   * 已注册错误码对象池
   *
   * @var array<string, ErrorCode>
   */
  private static $errorCodes = [];

  /**
   * 从 PHP 文件批量加载错误码
   *
   * 文件必须 `return array`，元素均为 {@see ErrorCode} 实例或 [statusCode, errorCode, message] 三元组（向后兼容字段模式）。
   *
   * @param string $filePath 错误码注册文件绝对路径
   * @return void
   * @throws Error 文件不存在、文件 return 非数组、注册项格式无效时抛出
   */
  public static function load($filePath)
  {
    if (!file_exists($filePath)) {
      throw new Error("错误码文件不存在", 500, "500:ErrorCodeFileNotExist", $filePath);
    }
    $codes = include $filePath;
    if (!is_array($codes)) {
      throw new Error("错误码文件未返回数组", 500, "500:ErrorCodeFileInvalid", $filePath);
    }
    foreach ($codes as $nameOrCodeObject => $value) {
      // 兼容两种写法：
      //  1. return [ErrorCode::create('A', 500, 500, '...'), ErrorCode::create('B', ...)]  → key=0,1,2  value=对象
      //  2. return ['A' => [500, 500, '...'], 'B' => [...]]                                 → key='A' value=三元组
      if ($value instanceof self) {
        self::register($value);
      } elseif (is_array($value) && count($value) === 3) {
        if (is_string($nameOrCodeObject)) {
          [$statusCode, $errorCode, $message] = $value;
          self::register($nameOrCodeObject, $statusCode, $errorCode, $message);
        }
      } else {
        throw new Error("错误码注册项格式无效", 500, "500:ErrorCodeItemInvalid", [
          "file" => $filePath,
          "name" => is_string($nameOrCodeObject) ? $nameOrCodeObject : (is_int($nameOrCodeObject) ? (string)$nameOrCodeObject : gettype($nameOrCodeObject)),
        ]);
      }
    }
  }

  /**
   * 注册一条错误码
   *
   * 支持两种调用：
   *   register(ErrorCode $obj)
   *   register(string $name, int $statusCode, int|string $errorCode, string $message)
   *
   * 重复 name 第二次注册会覆盖第一次并触发 warning（PHP user warning，不阻断流程），便于调试。
   *
   * @param ErrorCode|string $nameOrCodeObject 错误码对象或字符串 name
   * @param int|null $statusCode HTTP 状态码（对象模式忽略）
   * @param int|string|null $errorCode 业务错误码（对象模式忽略）
   * @param string|null $message 错误描述（对象模式忽略）
   * @return ErrorCode 注册后的错误码对象
   */
  public static function register($nameOrCodeObject, $statusCode = null, $errorCode = null, $message = null)
  {
    if (is_string($nameOrCodeObject)) {
      $obj = new ErrorCode($nameOrCodeObject, (int)$statusCode, $errorCode, (string)$message);
    } elseif ($nameOrCodeObject instanceof self) {
      $obj = $nameOrCodeObject;
    } else {
      throw new Error("register() 第一个参数必须是字符串 name 或 ErrorCode 对象", 500, "500:ErrorCodeAddInvalid", gettype($nameOrCodeObject));
    }

    if (isset(self::$errorCodes[$obj->name]) && self::$errorCodes[$obj->name] != $obj) {
      trigger_error("错误码 name 重复注册：{$obj->name}", E_USER_WARNING);
    }
    self::$errorCodes[$obj->name] = $obj;
    return $obj;
  }

  /**
   * 按 name 取错误码对象
   *
   * @param string $name 错误码 name
   * @return ErrorCode
   * @throws Error name 不存在时抛出
   */
  public static function find($name)
  {
    if (!isset(self::$errorCodes[$name])) {
      throw new Error("错误码未注册：{$name}", 500, "500:ErrorCodeNotExist", $name);
    }
    return self::$errorCodes[$name];
  }

  /**
   * 按 name 探测错误码是否存在
   *
   * @param string $name 错误码 name
   * @return bool
   */
  public static function exists($name)
  {
    return isset(self::$errorCodes[$name]);
  }

  /**
   * 移除一条错误码
   *
   * @param string $name 错误码 name
   * @return void
   */
  public static function remove($name)
  {
    unset(self::$errorCodes[$name]);
  }

  /**
   * 取全部已注册错误码
   *
   * @return array<string, ErrorCode>
   */
  public static function all()
  {
    return self::$errorCodes;
  }

  /**
   * 清空错误码池（主要给单元测试使用）
   *
   * @return void
   */
  public static function clear()
  {
    self::$errorCodes = [];
  }

  /**
   * 工厂方法：构造错误码对象
   *
   * 等价调用 {@see ErrorCode::__construct}，便于链式写法的语义统一。
   *
   * @param string $name 错误码 name（必填）
   * @param int $statusCode HTTP 状态码
   * @param int|string $errorCode 业务错误码
   * @param string $message 错误描述
   * @return ErrorCode
   */
  public static function create($name, $statusCode, $errorCode, $message)
  {
    return new self($name, $statusCode, $errorCode, $message);
  }

  /**
   * 错误码名称（唯一标识）
   *
   * @var string
   */
  public $name;

  /**
   * HTTP 状态码
   *
   * @var int
   */
  public $statusCode;

  /**
   * 业务错误码
   *
   * @var int|string
   */
  public $errorCode;

  /**
   * 错误描述
   *
   * @var string
   */
  public $message;

  /**
   * 构造错误码对象
   *
   * @param string $name 错误码 name（必填）
   * @param int $statusCode HTTP 状态码
   * @param int|string $errorCode 业务错误码
   * @param string $message 错误描述
   */
  public function __construct($name, $statusCode, $errorCode, $message)
  {
    if (!is_string($name) || $name === '') {
      throw new Error("错误码 name 必须为非空字符串", 500, "500:ErrorCodeKeyInvalid");
    }
    $this->name = $name;
    $this->statusCode = (int)$statusCode;
    $this->errorCode = $errorCode;
    $this->message = (string)$message;
  }
}
