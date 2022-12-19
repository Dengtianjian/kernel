<?php

namespace gstudio_kernel\Foundation;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

class Log
{
  static private function genLogPath(...$path)
  {
    return File::genPath(F_APP_DATA, "Logs", ...$path);
  }
  static function record($content)
  {
    $year = date("Y");
    $month = date("m");
    $logDir = self::genLogPath($year, $month);
    //* 如果年/月文件夹不存在就创建
    if (!is_dir($logDir)) {
      mkdir($logDir, 0757, true);
    }
    $logFileName = date("d") . ".yml";
    $logFilePath = File::genPath($logDir, $logFileName);
    if (is_array($content)) $content = json_encode($content);
    $content = strval($content);
    $time = date("Y-m-d h:i:s");
    $content = <<<EOT
$time: $content\n
EOT;
    error_log($content, 3, $logFilePath);
  }
  static function read($day = null, $month = null, $year = null)
  {
    if ($year === null) {
      $year = date("Y");
    }
    $directoryPath = self::genLogPath($year);
    if (!is_dir($directoryPath)) {
      return [];
    }
    if ($month === null && $day === null) {
      //* 读取年下的多少个月日志文件夹
      return File::scandir($directoryPath);
    }
    if ($month === null) {
      $month = date("m");
      if ($day === null) {
        $directoryPath = self::genLogPath($year, $month);
        if (!is_dir($directoryPath)) {
          return [];
        }
        //* 读取月下有多少日志文件
        return File::scandir($directoryPath);
      }
    } else if ($month < 10) {
      $month = "0$month";
    }

    //* 最后是直接读取 天 下的日志文件
    $directoryPath .= "/$month";
    if ($day === null) {
      $day = date("d");
    } else if ($day < 10) {
      $day = "0$day";
    }
    $targetTimestamp = strtotime("$year-$month-$day");
    $now = time();
    if ($targetTimestamp > $now) {
      $targetTimestamp = $now;
    }
    $year = date("Y", $targetTimestamp);
    $month = date("m", $targetTimestamp);
    $day = date("d", $targetTimestamp);
    $directoryPath .= "/$day.yml";
    if (!file_exists($directoryPath)) {
      return [];
    }
    return \yaml_parse(file_get_contents($directoryPath));
  }
  //* 待确认需求
  static function readRange($start, $end = null)
  {
    if ($end === null) {
      $end = time();
    }
    $start = [
      "year" => date("Y", $start),
      "month" => date("m", $start),
      "day" => date("d", $start),
    ];
    $end = [
      "year" => date("Y", $end),
      "month" => date("m", $end),
      "day" => date("d", $end),
    ];
    $yearRanges = $end['year'] - $start['year'];
    $monthRangs = $end['month'] - $start['month'];
  }
}
