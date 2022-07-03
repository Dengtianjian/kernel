<?php

namespace kernel\Foundation;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use Error;
use kernel\Foundation\Exception\ErrorCode;
use kernel\Service\RequestService;

class Response
{
  private static $resultData = []; //* 增加到响应结果数据体里的数据
  private static $responseData = []; //* 增加到响应结果里的数据
  private static $headers = []; //* 响应头
  private static $responseInterceptors = []; //* 响应拦截回调函数
  private static $statusCode = null; //* 响应状态码
  static function header(string $key, string $value, bool $replace = true)
  {
    array_push(self::$headers, [
      "key" => $key,
      "value" => $value,
      "replace" => $replace
    ]);
  }
  static function intercept(callable $callback, ?string $responseType = null, $statusCode = null, $responseCode = null): callable
  {
    $key = microtime();
    self::$responseInterceptors[$key] = [
      "responseType" => $responseType,
      "statusCode" => $statusCode,
      "responseCode" => $responseCode,
      "callback" => $callback
    ];
    return function () use ($key) {
      unset(self::$responseInterceptors[$key]);
    };
  }
  static function error($statusCode, $code = 500, $message = "", $data = [], $details = [])
  {
    if (\is_string($statusCode)) {
      $error = ErrorCode::match($statusCode);
      if ($error) {
        self::result($error[0], $error[1], $data, $error[2], $details, "error");
      } else {
        self::result($statusCode, $code, $data, $message, $details, "error");
      }
    } else {
      self::result($statusCode, $code, $data, $message, $details, "error");
    }
  }
  static function success($data, $statusCode = 200, $code = 200000, $message = "ok")
  {
    self::result($statusCode, $code, $data, $message);
  }
  static function null($statusCode = 200)
  {
    http_response_code($statusCode);
    exit;
  }
  static function statusCode($statusCode = 200)
  {
    self::$statusCode = $statusCode;
  }
  static function result($statusCode = 200, $code = 200000,  $data = null, string|null $message = "", $details = [], string $type = "success")
  {
    $interceptResult = true;
    if (count(self::$responseInterceptors) > 0) {
      foreach (Response::$responseInterceptors as $intercrptor) {
        if (isset($intercrptor['responseType']) && $intercrptor['responseType'] !== $type) {
          continue;
        }
        if (isset($intercrptor['statusCode'])) {
          if (is_array($intercrptor['statusCode'])) {
            if (!in_array($statusCode, $intercrptor['statusCode'])) {
              continue;
            }
          } else if ($intercrptor['statusCode'] !== $statusCode) {
            continue;
          }
        }
        if (isset($intercrptor['responseCode']) && $intercrptor['responseCode'] !== $code) {
          if (is_array($intercrptor['responseCode'])) {
            if (!in_array($code, $intercrptor['responseCode'])) {
              continue;
            }
          } else if ($intercrptor['responseCode'] !== $code) {
            continue;
          }
        }
        $interceptResult = call_user_func($intercrptor['callback'], $statusCode, $code, $data, $message, $details);
        if ($interceptResult === false) {
          break;
        }
      }
    }
    if ($interceptResult === false) {
      return false;
    }

    $isAjax = RequestService::request()->ajax();
    if (self::$statusCode !== null) {
      $statusCode = self::$statusCode;
    }

    if (!$isAjax) {
      $currentUrl = F_BASE_URL;
      $currentUrl = substr($currentUrl, 0, \strlen($currentUrl) - 1) . $_SERVER['REQUEST_URI'];
      $redirectUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
      if ($redirectUrl === $currentUrl || !$redirectUrl) {
        $redirectUrl = F_BASE_URL;
      }
      if ($statusCode > 299) {
        if (Config::get("mode") === "development") {
          echo "error";
          Output::debug($message, $statusCode, $code, $data, $details);
        } else {
          Output::print($message);
        }
      }
      exit();
    }

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
      "message" => $message,
      "version" => Config::get("version")
    ];
    if (Config::get("mode") === "development") {
      $result['details'] = $details;
    }
    if (!empty(self::$responseData)) {
      $result = array_merge($result, self::$responseData);
    }
    self::output($result);
  }
  static function output($data)
  {
    \print_r(\json_encode($data));
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

    $range = $GLOBALS['App']->request->headers("Range") ?: false;
    // $range=200000;

    $remainingLength = 0;
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $fileSize);
    if ($range) {
      $remainingLength = $fileSize - $range;
      header("Content-Range: bytes $range-$remainingLength/$fileSize");
      header('Content-Length: ' . $fileSize - $range);
    }

    if (File::isImage($filePath) && $range === false) {
      header('Content-type: image/png;', true);
      header('Content-Disposition: inline; filename=' . urlencode($fileName));
      $content = file_get_contents($filePath);
      echo $content;
    } else {
      header('Content-type: application/x-' . $fileinfo['extension'], true);
      header('Content-Disposition: attachment; filename=' . urlencode($fileName));

      if ($range) {
        header("HTTP/1.1 206 Partial Content");
        $content = file_get_contents($filePath, false, null, $range, $fileSize);
        echo $content;
      } else {
        if (file_exists($filePath)) {
          readfile($filePath);
        } else {
          echo "";
        }
      }

      exit();
    }
  }
  static function text(string|null $content, bool $format = false): void
  {
    if ($format) {
      Output::format($content);
    } else {
      Output::print($content);
    }
    exit();
  }
}
