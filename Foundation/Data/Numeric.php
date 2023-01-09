<?php

namespace kernel\Foundation\Data;

class Numeric
{
  /**
   * 转换目标变量为数值
   *
   * @param mixed $Target 目标变量
   * @return int|float
   */
  public static function val($Target)
  {
    if (is_null($Target)) return intval($Target);
    if (is_numeric($Target)) return $Target;
    return strpos($Target, ".") === false ? intval($Target) : doubleval($Target);;
  }
}
