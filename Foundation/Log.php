<?php

namespace kernel\Foundation;

use kernel\Foundation\File\FileHelper;

if (!defined('F_KERNEL')) {
  exit('Access Denied');
}

class Log
{
  /**
   * 生成日志文件路径
   *
   * @param string[] ...$path 日志文件路径
   * @return bool
   */
  static private function genLogPath(...$path)
  {
    return FileHelper::combinedFilePath(F_APP_DATA, "Logs", ...$path);
  }
  /**
   * 记录日志
   * 会根据当前年，月创建文件夹，以日来创建文件记录内容
   *
   * @param mixed $content 记录内容
   * @return void
   */
  static function record($content)
  {
    $year = date("Y");
    $month = date("m");
    $logDir = self::genLogPath($year, $month);
    //* 如果年/月文件夹不存在就创建
    if (!is_dir($logDir)) {
      mkdir($logDir, 0757, true);
      if (is_dir($logDir)) {
        chown($logDir, 'www');
        chmod($logDir, 0757);
      }
    }
    $logFileName = date("d") . ".yml";
    $logFilePath = FileHelper::combinedFilePath($logDir, $logFileName);
    if (is_array($content)) $content = json_encode($content, JSON_UNESCAPED_UNICODE);
    $content = strval($content);
    $time = date("Y-m-d h:i:s");
    $content = <<<EOT
$time: $content
EOT;
    if (file_exists($logFilePath)) {
      $content = "\n" . $content;
    }
    error_log($content, 3, $logFilePath);
  }
  /**
   * 读取指定日期下的日志文件
   *
   * @param int $day 日
   * @param int $month 月
   * @param int $year 年
   * @return string[]
   */
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
      return FileHelper::scandir($directoryPath);
    }
    if ($month === null) {
      $month = date("m");
      if ($day === null) {
        $directoryPath = self::genLogPath($year, $month);
        if (!is_dir($directoryPath)) {
          return [];
        }
        //* 读取月下有多少日志文件
        return FileHelper::scandir($directoryPath);
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
    if (function_exists("yaml_parse")) {
      return \yaml_parse(file_get_contents($directoryPath));
    }
    return explode("\n", file_get_contents($directoryPath));
  }
}
