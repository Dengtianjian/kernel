<?php

namespace kernel\Foundation;

use Exception as GlobalException;
use gstudio_kernel\Foundation\ReturnResult\ReturnList;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\Router;
use kernel\Foundation\Config;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Exception\ErrorCode;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\HTTP\Response\ResponsePagination;

/**
 * KERNEL标识符
 */
if (!defined("F_KERNEL")) {
  define("F_KERNEL", true);
}

class Cron
{
  static private $Tasks = [];
  static private $TimeTasks = [];
  /**
   * 添加一个定时任务
   *
   * @param clouser|Controller $task 控制器或者闭包
   * @param string $time 指定时间才执行，如果没传入该值，每分钟都会执行一次任务  
   * 格式为：y-m-d h:i => 年-月-日 时：分，每个时间值只需要传入数值即可，**小于10的不用补零**  
   * 会逐一省略日期和时间元素去匹配添加的定时任务，匹配中了就会执行任务  
   * 有时候可能只传入一个数字，那么这个数值不知道是月还是日，或者是时、分，那么在每个数值前面加入对应的英文字母即可，月前面加入m，日加d，时加h，不加只传一个数值或者加入i就是分，**该规则在传入的字符串里包含单个字符才匹配中**  
   * 例子：  
   * 10-24 每年的10月24日 0时0分执行  
   * m10 每年的10月1日 0时0分执行  
   * 10-24 11 每年的10月24日 11时0分执行  
   * 12:00 每天的12点执行  
   * 38 每小时的38分执行  
   * h13 每天的13点执行
   * @return void
   */
  static function install($task, $time = null)
  {
    if (is_null($time)) {
      array_push(self::$Tasks, $task);
    } else {
      if (preg_match("/y\d+/", $time)) {
        $time = (intval(str_replace("y", "", $time))) . "-1-1 0:0";
      }
      if (preg_match("/m\d+/", $time)) {
        $time = (intval(str_replace("m", "", $time))) . "-1 0:0";
      }
      if (preg_match("/d\d+/", $time)) {
        $time = (intval(str_replace("d", "", $time))) . " 0:0";
      }
      if (preg_match("/h\d+/", $time)) {
        $time = (intval(str_replace("h", "", $time))) . ":0";
      }
      if (preg_match("/i\d+/", $time)) {
        $time = str_replace("i", "", $time);
      }

      if (!array_key_exists($time, self::$TimeTasks)) {
        self::$TimeTasks[$time] = [];
      }
      array_push(self::$TimeTasks[$time], $task);
    }
  }
  /**
   * 获取无指定时间的任务列表
   *
   * @return array
   */
  function list()
  {
    $date = strtotime(date("Y-m-d H:i:s"));
    $Year = intval(date('Y', $date));
    $Month = intval(date("m", $date));
    $Day = intval(date("d", $date));
    $Hour = intval(date("H", $date));
    $Minute = intval(date("i", $date));

    $Cons = [];

    $MatchRules = [
      $Minute,

      "{$Year}-{$Month}-{$Day} {$Hour}:{$Minute}",
      "{$Month}-{$Day} {$Hour}:{$Minute}",
      "{$Day} {$Hour}:{$Minute}",
      "{$Hour}:{$Minute}",
    ];
    if ($Minute === 0) {
      array_push($MatchRules,  "{$Hour}:0");
      array_push($MatchRules,  "{$Day} {$Hour}:0");
      array_push($MatchRules,  "{$Month}-{$Day} {$Hour}:0");
      array_push($MatchRules,  "{$Year}-{$Month}-{$Day} {$Hour}:0");
      array_push($MatchRules, "{$Year}-{$Month}-{$Day} {$Hour}");
    }
    if ($Hour === 0 && $Minute === 0) {
      array_push($MatchRules, "{$Day} 0:0");
      array_push($MatchRules, "{$Year}-{$Month}-{$Day} 0:0");
      array_push($MatchRules, "{$Year}-{$Month}-{$Day}");
    }
    if ($Day === 1 && $Hour === 0 && $Minute === 0) {
      array_push($MatchRules,  "{$Month}-1 0:0",);
      array_push($MatchRules, "{$Year}-{$Month}-1 0:0");
      array_push($MatchRules, "{$Year}-{$Month}");
    }
    if ($Month === 1 && $Day === 1 && $Hour === 0 && $Minute === 0) {
      array_push($MatchRules, "{$Year}-1-1 0:0");
      array_push($MatchRules, "{$Year}");
    }

    foreach ($MatchRules as $Time) {
      if (array_key_exists($Time, self::$TimeTasks)) {
        array_push($Cons, ...self::$TimeTasks[$Time]);
      }
    }

    return array_merge(self::$Tasks, $Cons);
  }
}
