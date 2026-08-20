<?php

namespace kernel\Foundation;
use kernel\Foundation\FileSystem\Path;

use kernel\Foundation\FileSystem\FileHelper;


class Log
{
  /**
   * 最小记录级别（null 表示不限制，全部记录）
   * 可选值：debug / info / warning / error
   *
   * @var string|null
   */
  static private $minLevel = null;

  /**
   * 级别优先级（数值越大越严重）
   */
  static private $levels = ["debug" => 0, "info" => 1, "warning" => 2, "error" => 3];

  /**
   * 构造方法：检查并创建日志存储根目录
   *
   * 仅当日志存储根目录（Data/Logs）可确定时创建；不可确定时静默跳过。
   */
  function __construct()
  {
    $base = self::basePath();
    if ($base !== "" && !is_dir($base)) {
      @mkdir($base, 0755, true);
    }
  }

  /**
   * 生成日志文件路径
   *
   * @param string[] ...$paths 日志文件路径
   * @return string
   */
  static private function generateLogPath(...$paths)
  {
    return FileHelper::combinedFilePath(self::basePath(), ...$paths);
  }
  /**
   * 日志存储根目录（固定为 Data/Logs，不可修改）
   *
   * @return string
   */
  static private function basePath()
  {
    return Path::data() ? Path::data() . "/Logs" : "";
  }
  /**
   * 设置或读取最小记录级别
   *
   * 低于该级别的日志将被忽略（record 返回 false）。
   * 传 null（或不传参数）时读取当前级别；初始为 null 表示不限制。
   *
   * @param string|null $level 级别：debug / info / warning / error，null 时读取
   * @return string|null 当前最小级别
   */
  static function level($level = null)
  {
    if ($level === null) {
      return self::$minLevel;
    }
    self::$minLevel = (string)$level;
    return self::$minLevel;
  }
  /**
   * 记录日志
   *
   * 根据当前年/月创建目录，以日创建 .jsonl 文件，每行一条单行 JSON 日志：
   * {"time":"Y-m-d H:i:s","level":"info","content":...}
   *
   * @param mixed $content 记录内容：数组/对象自动转 JSON，Throwable 转 message+file+trace
   * @param string $level 日志级别：debug / info / warning / error，默认 info
   * @return bool 是否写入成功（被级别过滤时返回 false）
   */
  static function record($content, $level = "info")
  {
    //* 级别过滤：低于最小级别直接忽略
    if (self::$minLevel !== null && isset(self::$levels[$level])) {
      $minPriority = isset(self::$levels[self::$minLevel]) ? self::$levels[self::$minLevel] : 0;
      if (self::$levels[$level] < $minPriority) {
        return false;
      }
    }

    $logDir = self::generateLogPath(date("Y"), date("m"));
    //* 年/月目录不存在则创建
    if (!is_dir($logDir)) {
      @mkdir($logDir, 0755, true);
    }
    $logFilePath = self::generateLogPath(date("Y"), date("m"), date("d") . ".jsonl");

    //* Throwable 转结构化数据，避免丢失 message/trace
    if ($content instanceof \Throwable) {
      $content = [
        "message" => $content->getMessage(),
        "file" => $content->getFile() . ":" . $content->getLine(),
        "trace" => $content->getTraceAsString()
      ];
    }

    //* 非法 UTF-8 用替换符处理，保证 json_encode 不返回 false
    $json = json_encode([
      "time" => date("Y-m-d H:i:s"),
      "level" => $level,
      "content" => $content
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
      //* 兜底：无法 JSON 化的内容转字符串
      $json = json_encode([
        "time" => date("Y-m-d H:i:s"),
        "level" => $level,
        "content" => strval($content)
      ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    $result = file_put_contents($logFilePath, $json . "\n", FILE_APPEND | LOCK_EX);
    if ($result !== false) {
      @chmod($logFilePath, 0644);
    }

    return $result !== false;
  }
  /**
   * 记录 info 级别日志
   *
   * @param mixed $message 日志消息
   * @param array $context 附加上下文，会自动并入日志内容
   * @return bool
   */
  static function info($message, $context = [])
  {
    return self::record(self::buildContent($message, $context), "info");
  }
  /**
   * 记录 warning 级别日志
   *
   * @param mixed $message 日志消息
   * @param array $context 附加上下文，会自动并入日志内容
   * @return bool
   */
  static function warning($message, $context = [])
  {
    return self::record(self::buildContent($message, $context), "warning");
  }
  /**
   * 记录 error 级别日志
   *
   * @param mixed $message 日志消息
   * @param array $context 附加上下文，会自动并入日志内容
   * @return bool
   */
  static function error($message, $context = [])
  {
    return self::record(self::buildContent($message, $context), "error");
  }
  /**
   * 记录 debug 级别日志
   *
   * @param mixed $message 日志消息
   * @param array $context 附加上下文，会自动并入日志内容
   * @return bool
   */
  static function debug($message, $context = [])
  {
    return self::record(self::buildContent($message, $context), "debug");
  }
  /**
   * 组装消息与上下文
   *
   * @param mixed $message 日志消息
   * @param array $context 附加上下文
   * @return mixed 有上下文时返回 ["message" => ..., "context" => ...]，否则原样返回消息
   */
  static private function buildContent($message, $context)
  {
    return empty($context) ? $message : ["message" => $message, "context" => $context];
  }
  /**
   * 读取日志
   *
   * 参数组合决定返回结构：
   * - 三个参数均不传：当年月份目录列表
   * - 只传 $year：该年月份目录列表
   * - 传 $year + $month（不传 $day）：该月日志文件列表
   * - 全部传入：该日逐条解析后的日志（每项含 time/level/content）
   * 兼容读取旧版 .yml 文件（"时间: 内容" 行格式）。非法日期或未来日期返回空数组。
   *
   * @param int|null $day 日（1-31），null 表示不指定到日（返回该月日志文件列表）
   * @param int|null $month 月（1-12），null 表示不指定到月（返回该年月份目录列表）
   * @param int|null $year 年，null 为当年
   * @return array
   */
  static function read($day = null, $month = null, $year = null)
  {
    if ($year === null) {
      $year = date("Y");
    }
    $yearPath = self::generateLogPath((int)$year);
    if (!is_dir($yearPath)) {
      return [];
    }
    if ($month === null && $day === null) {
      //* 读取年下的月份目录列表
      return FileHelper::scandir($yearPath);
    }
    if ($month === null) {
      $month = (int)date("m");
    }
    $month = (int)$month;
    if ($month < 1 || $month > 12) {
      return [];
    }
    $monthPath = self::generateLogPath((int)$year, sprintf("%02d", $month));
    if (!is_dir($monthPath)) {
      return [];
    }
    if ($day === null) {
      //* 读取该月下的日志文件列表
      return FileHelper::scandir($monthPath);
    }
    $day = (int)$day;

    //* 边界校验：非法日期或未来日期直接返回空
    if ($day < 1 || $day > 31) {
      return [];
    }
    $targetTimestamp = strtotime(sprintf("%04d-%02d-%02d", $year, $month, $day));
    if ($targetTimestamp === false || $targetTimestamp > time()) {
      return [];
    }

    $logFile = self::generateLogPath((int)$year, sprintf("%02d", $month), sprintf("%02d", $day) . ".jsonl");
    if (!file_exists($logFile)) {
      //* 兼容旧版 .yml 文件
      $logFile = self::generateLogPath((int)$year, sprintf("%02d", $month), sprintf("%02d", $day) . ".yml");
      if (!file_exists($logFile)) {
        return [];
      }
    }
    return self::parseLogFile($logFile);
  }
  /**
   * 分页读取指定日期的日志内容
   *
   * 基于 read() 读取当日全部日志后按页码切片，返回包含列表与分页信息的结果数组。
   * 日期行为与 read() 一致：$day 为 null 时取当天，$month 为 null 时取当月，$year 为 null 时取当年。
   * 页码或每页条数小于 1 时按 1 处理；当日无日志时返回空的 list 与 totalPages=0。
   *
   * @param int $page 页码，从 1 开始，默认 1
   * @param int $pageSize 每页条数，默认 20
   * @param int|null $day 日（1-31），null 为当天
   * @param int|null $month 月（1-12），null 为当月
   * @param int|null $year 年，null 为当年
   * @return array 分页结果：list / total / page / pageSize / totalPages
   */
  static function page($page = 1, $pageSize = 20, $day = null, $month = null, $year = null)
  {
    if ($day === null) {
      $day = (int)date("d");
    }
    $logs = self::read($day, $month, $year);

    $page = max(1, (int)$page);
    $pageSize = max(1, (int)$pageSize);
    $total = count($logs);
    $totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 0;
    $list = $total > 0 ? array_slice($logs, ($page - 1) * $pageSize, $pageSize) : [];

    return [
      "list" => $list,
      "total" => $total,
      "page" => $page,
      "pageSize" => $pageSize,
      "totalPages" => $totalPages
    ];
  }
  /**
   * 解析日志文件（单行 JSON 优先，兼容旧版行格式）
   *
   * @param string $file 日志文件路径
   * @return array 每条日志为关联数组（time/level/content）
   */
  static private function parseLogFile($file)
  {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
      return [];
    }
    $logs = [];
    foreach ($lines as $line) {
      $decoded = json_decode($line, true);
      if (is_array($decoded) && isset($decoded["time"]) && isset($decoded["content"])) {
        $logs[] = $decoded;
        continue;
      }
      //* 兼容旧格式："2026-08-14 09:25:30: 内容"
      if (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}): ?(.*)$/s', $line, $matches)) {
        $logs[] = ["time" => $matches[1], "level" => "info", "content" => $matches[2]];
        continue;
      }
      $logs[] = ["content" => $line];
    }
    return $logs;
  }
  /**
   * 清理指定天数前的日志文件（含空目录回收）
   *
   * @param int $days 保留天数，默认 30
   * @return int 清理的文件数量
   */
  static function cleanup($days = 30)
  {
    $removed = 0;
    $cutoff = strtotime("-" . max(0, (int)$days) . " days");
    if ($cutoff === false) {
      return 0;
    }
    foreach (glob(self::generateLogPath("*", "*", "*")) ?: [] as $file) {
      if (!is_file($file)) {
        continue;
      }
      $monthDir = dirname($file);
      $year = basename(dirname($monthDir));
      $month = basename($monthDir);
      $day = basename($file, "." . pathinfo($file, PATHINFO_EXTENSION));
      $timestamp = strtotime(sprintf("%04d-%02d-%02d", $year, $month, $day));
      if ($timestamp !== false && $timestamp < $cutoff) {
        @unlink($file);
        $removed++;
      }
    }
    //* 回收空目录（月/年）
    foreach (glob(self::generateLogPath("*", "*"), GLOB_ONLYDIR) ?: [] as $dir) {
      if (count(glob($dir . "/*")) === 0) {
        @rmdir($dir);
      }
    }
    return $removed;
  }
}
