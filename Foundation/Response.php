<?php

namespace kernel\Foundation;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Exception\ErrorCode;

class Response
{
  private static $resultData = [];
  private static $responseData = [];
  private static $headers = [];
  private static $responseIntercept = null;
  static function header(string $key, string $value, bool $replace = true)
  {
    array_push(self::$headers, [
      "key" => $key,
      "value" => $value,
      "replace" => $replace
    ]);
  }
  static function error($statusCode, $code = 500, $message = "", $data = [], $details = [])
  {
    if (\is_string($statusCode)) {
      $error = ErrorCode::match($statusCode);
      self::result($error[0], $error[1], $data, $error[2], $details);
    } else {
      self::result($statusCode, $code, $data, $message, $details);
    }
  }
  static function success($data, $statusCode = 200, $code = 200000, $message = "ok")
  {
    self::result($statusCode, $code, $data, $message);
  }
  static function result($statusCode = 200, $code = 200000,  $data = null, string $message = "", $details = [])
  {
    global $app;
    $routerType = $app->router['type'];
    if (!$routerType) {
      $routerType = "view";
    }
    // if ($routerType === "view") {
    //   $currentUrl = F_BASE_URL;
    //   $currentUrl = substr($currentUrl, 0, \strlen($currentUrl) - 1) . $_SERVER['REQUEST_URI'];
    //   $redirectUrl = $_SERVER['HTTP_REFERER'];
    //   if ($redirectUrl == $currentUrl || !$redirectUrl) {
    //     $redirectUrl = F_BASE_URL;
    //   }
    //   \showmessage($message, $redirectUrl, [], [
    //     "alert" => "error"
    //   ]);
    //   exit();
    // }

    header("Content-Type:application/json", true, $statusCode);
    for ($i = 0; $i < count(self::$headers); $i++) {
      $headerItem = self::$headers[$i];
      header($headerItem['key'] . ":" .  $headerItem['value'], $headerItem['replace']);
    }
    if (!empty(self::$resultData)) {
      $data = \array_merge($data, self::$resultData);
    }
    $result = [
      "statusCode" => $statusCode,
      "code" => $code,
      "data" => $data,
      "message" => $message
    ];
    if (Config::get("mode") === "development") {
      $result['details'] = $details;
    }
    if (!empty(self::$responseData)) {
      $result = array_merge($result, self::$responseData);
    }
    $interceptResult = true;
    if (self::$responseIntercept !== null) {
      $callback = Response::$responseIntercept;
      self::$responseIntercept = null;
      $interceptResult = call_user_func($callback, $result, $statusCode, $code, $data, $message, $details);
    }
    if ($interceptResult === false) {
      return false;
    }

    if (CHARSET === "gbk") {
      \print_r(GJson::encode($result));
    } else {
      \print_r(\json_encode($result));
    }
    exit();
  }
  static function addData(array $data)
  {
    self::$resultData = \array_merge(self::$resultData, $data);
    return self::$resultData;
  }
  static function add(array $data)
  {
    self::$responseData = \array_merge(self::$responseData, $data);
    return self::$responseData;
  }
  static function download(string $filePath, string $fileName, $fileSize)
  {
    $fileinfo = pathinfo($filePath);

    if (File::isImage($filePath)) {
      header('Content-type: image/png;', true);
      $content = file_get_contents($filePath);
      echo $content;
    } else {
      header('Content-type: application/x-' . $fileinfo['extension'], true);
      header('Content-Disposition: attachment; filename=' . urlencode($fileName));
      header('Content-Length: ' . $fileSize);
      readfile($filePath);

      exit();
    }
  }
  static function intercept($callback = null)
  {
    self::$responseIntercept = $callback;
  }
}
