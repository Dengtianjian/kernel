<?php

namespace gstudio_kernel\Foundation\Exception;

use gstudio_kernel\Foundation\Response;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

class ErrorCode
{
  private static $errorCodes = [];
  public static function load($filePath)
  {
    if (file_exists($filePath)) {
      include_once($filePath);
    } else {
      Response::error(500, "ErrorCode:500001", "Server error");
    }
  }
  public static function add($keyCode, $statusCode, $code, $message)
  {
    self::$errorCodes[$keyCode] = [
      $statusCode, $code, $message
    ];
    return self::$errorCodes[$keyCode];
  }
  public static function match($keyCode)
  {
    return self::$errorCodes[$keyCode];
  }
  public static function all()
  {
    return self::$errorCodes;
  }
}
