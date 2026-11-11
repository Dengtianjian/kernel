<?php

namespace kernel\Support;

/**
 * 语言字典
 *
 * 无状态的静态工具类：只维护「语言代码 → 语言名称」的映射，不依赖应用、不进容器、
 * 直接 `Language::getAll()` 调用。与 `Services/` 的区别就在这里——那边是被 Provider
 * 登记、经容器取用的有生命周期服务，这边则是纯静态支撑类。
 *
 * @package kernel\Support
 */
class Language
{
  /**
   * 语言代码 → 语言名称
   *
   * @var array<string, string>
   */
  private static $languages = [
    "zh" => "中文",
    "en" => "英语",
    "yue" => "粤语",
    "wyw" => "文言文",
    "jp" => "日语",
    "kor" => "韩语",
    "fra" => "法语",
    "spa" => "西班牙语",
    "th" => "泰语",
    "ara" => "阿拉伯语",
    "ru" => "俄语",
    "pt" => "葡萄牙语",
    "de" => "德语",
    "it" => "意大利语",
    "el" => "希腊语",
    "nl" => "荷兰语",
    "pl" => "波兰语",
    "bul" => "保加利亚语",
    "est" => "爱沙尼亚语",
    "dan" => "丹麦语",
    "fin" => "芬兰语",
    "cs" => "捷克语",
    "rom" => "罗马尼亚语",
    "slo" => "斯洛文尼亚语",
    "swe" => "瑞典语",
    'hu' => "匈牙利语",
    "cht" => "繁体中文",
    "vie" => "越南语",
  ];

  /**
   * 全部语言
   *
   * @return array<string, string> 语言代码 => 语言名称
   */
  static function getAll()
  {
    return self::$languages;
  }

  /**
   * 指定语言的名称
   *
   * @param string $langEng 语言代码（如 "zh"、"en"）
   * @return string|null 未收录的语言返回 null
   */
  static function getOne($langEng)
  {
    return self::$languages[$langEng] ?? null;
  }

  /**
   * 追加 / 覆盖一种语言
   *
   * 只改进程内的字典，不落库、不写文件。
   *
   * @param string $langEng 语言代码
   * @param string $langName 语言名称
   * @return void
   */
  static function add($langEng, $langName)
  {
    self::$languages[$langEng] = $langName;
  }
}
