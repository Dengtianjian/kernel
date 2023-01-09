<?php

namespace kernel\Foundation\Exception;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class ErrorCode
{
  private static $ErrorCodes = [];
  /**
   * 加载文件，并且把文件内return的错误码对象全都加入到错误码库中
   *
   * @param string $filePath 错误码文件地址
   * @return void
   */
  public static function load($filePath)
  {
    if (file_exists($filePath)) {
      $Codes = include_once($filePath);
      foreach ($Codes as $CodeObject) {
        self::add($CodeObject);
      }
    } else {
      throw new Exception("错误码文件不存在", 500, "500:ErrorCodeFileNotExist");
    }
  }
  /**
   * 添加一个错误码到错误码库
   *
   * @param string|object $keyOrCodeObject 错误码标识符或者用make方法创建的错误码对象。当该值为一个对象时，后面的值不用传
   * @param int $statusCode HTTP状态码
   * @param string|int $code 错误码
   * @param string $message 错误信息
   * @return object 错误码对象
   */
  public static function add($keyOrCodeObject, $statusCode = null, $code = null, $message = null)
  {
    if (is_object($keyOrCodeObject)) {
      self::$ErrorCodes[$keyOrCodeObject->key] = (object)[
        "statusCode" => $keyOrCodeObject->statusCode,
        "code" => $keyOrCodeObject->code,
        "message" => $keyOrCodeObject->message,
      ];
    } else {
      self::$ErrorCodes[$keyOrCodeObject] = (object)[
        "statusCode" => $statusCode,
        "code" => $code,
        "message" => $message,
      ];
    }

    return true;
  }
  /**
   * 根据标识符匹配一个库中的错误码
   *
   * @param string $key 错误码标识符
   * @return object 错误码对象
   */
  public static function match($key)
  {
    return self::$ErrorCodes[$key];
  }
  /**
   * 创建一个错误码对象，里面包含错误码标识符，HTTP状态码，错误码，错误消息
   *
   * @param string $key
   * @param int $statusCode
   * @param string|int $code
   * @param string $message
   * @return object
   */
  public static function make($key, $statusCode, $code, $message)
  {
    return (object)[
      "key" => $key,
      "statusCode" => $statusCode,
      "code" => $code,
      "message" => $message,
    ];
  }
}
