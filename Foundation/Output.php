<?php

namespace kernel\Foundation;


/**
 * 输出工具
 *
 * 提供调试输出（debug/backtrace）、打印（printContent）、
 * 格式化输出（format）与格式化字符串（string）。
 *
 * format() 输出 HTML <pre> 适合 HTTP 场景；CLI 场景应优先用
 * string() 取纯文本，避免 HTML 标签污染。isCli() 可感知运行环境。
 */
class Output
{
  /**
   * 调试输出并终止脚本
   *
   * @param mixed ...$data
   */
  public static function debug(...$data)
  {
    if ($data === []) {
      // 空参时仅输出一条提示，不再无条件 exit，避免误调终止脚本
      self::printContent("Output::debug() 无调试数据\n");
      return;
    }
    self::format(...$data);
    exit;
  }

  /**
   * 输出调用堆栈并终止脚本
   *
   * @param int $options debug_backtrace 选项
   * @param int $limit   返回的最大帧数，0 表示不限制
   */
  public static function backtrace($options = DEBUG_BACKTRACE_IGNORE_ARGS, $limit = 0)
  {
    $stack = debug_backtrace($options, $limit);
    self::debug($stack);
  }

  /**
   * 打印内容到标准输出
   *
   * @param mixed $outputString
   * @param mixed ...$value
   */
  public static function printContent($outputString, ...$value)
  {
    if (is_string($outputString)) {
      printf($outputString, ...$value);
    } else {
      print_r($outputString);
    }
  }

  /**
   * 将数据以 HTML <pre> 形式输出（兼容 HTTP 场景）
   *
   * @param mixed ...$data
   */
  public static function format(...$data)
  {
    echo self::wrapHtml(self::string(...$data));
  }

  /**
   * 将数据格式化为字符串（不含 HTML 标签，CLI/HTTP 通用）
   *
   * @param mixed ...$data
   * @return string
   */
  public static function string(...$data)
  {
    $parts = [];
    foreach ($data as $dataItem) {
      $parts[] = is_string($dataItem) || is_numeric($dataItem)
        ? (string) $dataItem
        : print_r($dataItem, true);
    }
    return implode("\n", $parts);
  }

  /**
   * 是否为 CLI 运行环境
   *
   * @return bool
   */
  public static function isCli()
  {
    return PHP_SAPI === "cli";
  }

  /**
   * 用 <pre> 包裹字符串（内部辅助）
   *
   * @param string $content
   * @return string
   */
  private static function wrapHtml($content)
  {
    return "<pre>" . $content . "</pre>";
  }
}
