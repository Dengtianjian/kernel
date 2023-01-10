<?php

namespace kernel\Foundation\Data;

use kernel\Foundation\Exception\Exception;

class Serializer
{
  /**
   * 要调用的规则名称
   *
   * @var string
   */
  public $useRuleName = null;
  /**
   * 构建序列化实例
   * 如果是实例化，那么就仅仅是要调用哪个已经存在的规则
   *
   * @param string $RuleName 要调用的规则名称
   */
  public function __construct($RuleName)
  {
    $this->useRuleName = $RuleName;
  }

  /**
   * 全局序列化规则
   *
   * @var array
   */
  static private $Rules = [];
  /**
   * 获取序列化规则
   *
   * @param string|string[] $Names 规则名称，如果传入的是字符串，可用“ . ”分隔层级，例如user.age，那就是获取user下的age规则。如果传入的字符串数组，那么就是获取多层级下的规则，跟用" . "分隔一样，例如传入[article,title]，就是获取article下的title规则
   * @param array $upperLevel 上一层级的序列化规则，这是当前函数递归调用时使用到，无需传入
   * @return array 获取到的序列化规则
   */
  static function get($Names, $upperLevel = null)
  {
    if (is_null($upperLevel)) $upperLevel = self::$Rules;
    if (!is_array($Names)) {
      $Names = explode(".", $Names);
    }
    $Rule = $upperLevel[array_shift($Names)];
    if (is_null($Rule)) return null;
    if (count($Names) === 0) return $Rule;
    return self::get($Names, $Rule);
  }
  /**
   * 添加规则
   *
   * @param string $Name 第一级规则名称
   * @param array $Rule 序列化规则数组
   * @return bool
   */
  static function add($Name, $Rule)
  {
    $Rules[$Name] = $Rule;
    return true;
  }
  /**
   * 序列化
   *
   * @param string|array $RuleOrName 序列化规则或者要调用的序列化规则名称
   * @param array $Data 被序列化的数据
   * @param string $SerializerName 序列化名称，会往被序列化的数组添加_serlizer键，值就是当前传入的值，默认是temp
   * @return mixed 序列化后的数据
   */
  static function serialization($RuleOrName, $Data, $SerializerName = "temp")
  {
    if (is_null($Data) || !is_array($Data) || count($Data) === 0) return $Data;
    $Rule = is_array($RuleOrName) ? $RuleOrName : self::get($RuleOrName);
    if (is_null($Rule)) {
      throw new Exception("序列化规则不存在");
    }
    if (!Arr::isAssoc($Data)) {
      foreach ($Data as &$dataItem) {
        if (!is_array($dataItem)) continue;
        if (array_key_exists("_serilizer", $dataItem)) continue;
        $dataItem = self::serialization($Rule, $dataItem, $SerializerName);
      }
      return $Data;
    }

    if (array_key_exists("_serilizer", $Data)) return $Data;
    $DataKeys = array_keys($Data);
    $ruleKeys = [];
    //* 考虑到可能传入的是关联素组和索引数组混合，那索引数组的键是索引，而值才是要的的字段名称，通过array_filter函数把索引元素的值推送到ruleKeys里面
    $FilterKeys = array_filter(array_keys($Rule), function ($Key) use ($Rule, &$ruleKeys) {
      if (is_numeric($Key)) {
        array_push($ruleKeys, $Rule[$Key]);
        return false;
      }
      return true;
    });
    $ruleKeys = array_merge($ruleKeys, $FilterKeys);
    //* 获取两个数组的差以获取不需要返回的键
    $RemoveKeys = array_diff($DataKeys, $ruleKeys);
    foreach ($Rule as $FieldName => $RuleItem) {
      if (is_numeric($FieldName)) {
        //* 如果键是数值，那就是索引键值，值就是要返回的字段名称
        if (!isset($Data[$RuleItem])) {
          $Data[$RuleItem] = null;
        }
        continue;
      };
      if (isset($Data[$FieldName])) {
        if ($RuleItem instanceof Serializer) {
          $Data[$FieldName] = self::serialization($RuleItem->useRuleName, $Data[$FieldName]);
        } else if ($RuleItem instanceof DataConversion) {
          $Data[$FieldName] = $RuleItem->data($Data[$FieldName])->convert();
        } else if ($RuleItem === "json") {
          if (isset($Data[$FieldName]) && is_string($Data[$FieldName]) && !empty($Data[$FieldName])) {
            $Data[$FieldName] = json_decode($Data[$FieldName], true);
          } else {
            $Data[$FieldName] = [];
          }
        } else if ($RuleItem === "serialize") {
          if (isset($Data[$FieldName]) && is_string($Data[$FieldName]) && !empty($Data[$FieldName])) {
            $Data[$FieldName] = unserialize($Data[$FieldName]) ?: [];
          } else {
            $Data[$FieldName] = [];
          }
        } else if (is_callable($RuleItem)) {
          $Data[$FieldName] = $RuleItem($Data[$FieldName], $Data);
        } else if (is_array($RuleItem)) {
          $Data[$FieldName] = self::serialization($RuleItem, $Data[$FieldName]);
        } else if (is_string($RuleItem)) {
          $Data[$FieldName] = DataConversion::quick($Data[$FieldName], $RuleItem);
        }
      } else {
        $Data[$FieldName] = null;
      }
    }
    foreach ($RemoveKeys as $Key) {
      unset($Data[$Key]);
    }

    $Data['_serlizer'] = is_array($RuleOrName) ? $SerializerName : $RuleOrName;
    return $Data;
  }
}
