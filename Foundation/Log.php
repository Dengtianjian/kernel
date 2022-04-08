<?php

namespace kernel\Foundation;

class Log
{
  static function record($content)
  {
    $year = date("Y");
    $month = date("m");
    $logDir = F_APP_ROOT . "/Logs/$year/$month";
    //* 如果年/月文件夹不存在就创建
    if (!is_dir($logDir)) {
      File::mkdir([$year, $month], F_APP_ROOT . "/Logs");
    }
    $logFileName = date("d") . ".yml";
    $logFilePath = $logDir . "/$logFileName";
    if (is_array($content)) $content = json_encode($content);
    $content = strval($content);
    $time = date("Y-m-d h:i:s");
    $content = <<<EOT
$time: $content\n
EOT;
    error_log($content, 3, $logFilePath);
  }
  static private function scandir($dir)
  {
    $dirs = scandir($dir);
    $dirs = array_values(array_filter($dirs, function ($item) {
      return !in_array($item, [".", ".."]);
    }));
    return $dirs;
  }
  static function read(int $day = null, int $month = null, int $year = null)
  {
    if ($year === null) {
      $year = date("Y");
    }
    $directoryPath = F_APP_ROOT . "/Logs/$year";
    if (!is_dir($directoryPath)) {
      return [];
    }
    if ($month === null && $day === null) {
      //* 读取年下的多少个月日志文件夹
      return self::scandir($directoryPath);
    }
    if ($month === null) {
      $month = date("m");
      if ($day === null) {
        $directoryPath .= "/$month";
        if (!is_dir($directoryPath)) {
          return [];
        }
        //* 读取月下有多少日志文件
        return self::scandir($directoryPath);
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
  static function readRange(int $start, int $end = null)
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
