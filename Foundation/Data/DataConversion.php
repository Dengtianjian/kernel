<?php

namespace kernel\Foundation\Data;

use kernel\Foundation\Output;

class DataConversion
{
  /**
   * 转换的目标类型或者类型规则
   *
   * @var string|array
   */
  private $types = null;
  /**
   * 要转换的数据
   *
   * @var mixed
   */
  private $data = null;
  /**
   * 是否补全Data不存在的键
   *
   * @var boolean
   */
  private $completion = false;
  /**
   * 剔除不存在类型规则的键
   *
   * @var boolean
   */
  private $removeNotExistRuleKey = false;
  /**
   * 构建数据抓换类
   *
   * @param array|string $types 期望转换的目标类型，或者目标类型数组。例如传入string，那通过data传入的数据就会被转换为string，如果type为空，调用convert时也没传入类型规则或者目标类型，就会自动识别并转换类型。传入是数组的话就会和data的键值相匹配，把data对应规则的值转换，例如规则是[username=>int] 那传入的数据是[username=>"9910"] 调用convert就会返回[username=>9910] 9910被转换为了整数
   * @param boolean $completion 补全data不存在的键。假设传入类型数组是[username=>string,age=>int]，而传入需要转换的数据是[username=>"admin"]，当调用convert时就会返回[username=>"admin",age=>null]
   * @param boolean $removeNotExistRuleKey 剔除不存在类型规则的键。假设传入类型数组是[username=>string]，而传入需要转换的数据是[username=>"admin",age=>8]，当调用convert时就会返回[username=>"admin"]
   */
  public function __construct($types = null, $completion = false, $removeNotExistRuleKey = false)
  {
    $this->types = $types;
    $this->completion = $completion;
    $this->removeNotExistRuleKey = $removeNotExistRuleKey;
  }
  /**
   * 需要转换的数据
   *
   * @param mixed $data
   * @return DataConversion
   */
  public function data($data)
  {
    $this->data = $data;
    return $this;
  }
  /**
   * 转换为字符串类型
   *
   * @return DataConversion
   */
  public function string()
  {
    $this->types = "string";
    return $this;
  }
  /**
   * 转换为整数类型
   *
   * @return DataConversion
   */
  public function int()
  {
    $this->types = "integer";
    return $this;
  }
  /**
   * 转换为数组类型
   *
   * @return DataConversion
   */
  public function array()
  {
    $this->types = "array";
    return $this;
  }
  /**
   * 转换为对象类型。建议传入的数据为数组
   *
   * @return DataConversion
   */
  public function object()
  {
    $this->types = "object";
    return $this;
  }
  /**
   * 转换为浮点类型
   *
   * @return DataConversion
   */
  public function double()
  {
    $this->types = "double";
    return $this;
  }
  /**
   * 转换为布尔类型
   *
   * @return DataConversion
   */
  public function bool()
  {
    $this->types = "bool";
    return $this;
  }
  /**
   * 自动识别类型，并且转换
   * 字符串会调用addslashes方法
   * 数值会根据是否带小数点来转换为整数或者双精度浮点
   *
   * @param mixed $target
   * @return mixed 转换后的数据
   */
  private function auto($target)
  {
    if (is_null($target)) return $target;

    if (is_numeric($target)) {
      if (strpos(strval($target), ".") === false) {
        $target = intval($target);
      } else {
        $target = doubleval($target);
      }
    } else if (is_string($target)) {
      $target = addslashes($target);
    }

    return $target;
  }
  /**
   * 设置类型
   *
   * @param mixed $target 转换的目标变量
   * @param string $type 转换的数据类型，可传入：boolean、bool、integer、int、float、double、string、array、object、null、any，any的意思时自动识别并且转换
   * @return mixed 转换后台的数据
   */
  private function setType($target, $type)
  {
    if ($type === "any") {
      return $this->auto($target);
    }
    $setResult = settype($target, $type);
    if ($setResult) {
      if ($type === "string") {
        $target = addslashes($target);
      }
      if ($type === "any") {
        $target = $this->auto($target);
      }
      return $target;
    }
    return null;
  }
  /**
   * 转换数据为目标类型
   *
   * @param array|string $types 可传入数据类型或者键值对，键是对应data的键，值是需要转换的目标类型，值可传入DataConversion实例
   * @return false|mixed 如果转换失败将会返回false否则就会返回转成后的数据
   */
  public function convert($types = null)
  {
    if (is_null($this->data)) return false;
    if (is_null($types)) {
      if (is_null($this->types)) return false;
      $types = $this->types;
    }

    $Data = $this->removeNotExistRuleKey ? [] : $this->data;

    if (is_array($types)) {
      if (!is_array($this->data)) return false;

      if (Arr::isAssoc($this->data)) {
        foreach ($types as $key => $value) {
          if (is_numeric($key)) {
            $key = $value;
            $value = "any";
          }

          if (isset($this->data[$key])) {
            if ($value instanceof DataConversion) {
              if (is_array($this->data[$key]) && !Arr::isAssoc($this->data[$key])) {
                if (!isset($Data[$key])) {
                  $Data[$key] = [];
                }
                foreach ($this->data[$key] as $dataKey => $dataValue) {
                  $Data[$key][$dataKey] = $value->data($dataValue)->convert();
                }
              } else {
                $Data[$key] = $types[$key]->data($this->data[$key])->convert();
              }
            } else if (is_callable($value)) {
              $Data[$key] = $value($this->data[$key]);
            } else {
              if (is_array($this->data[$key])) {
                $Data[$key] = self::quick($this->data[$key], $value, $this->completion, $this->removeNotExistRuleKey);
              } else {
                $Data[$key] = $this->setType($this->data[$key], $value);
              }
            }
          } else {
            if ($this->completion) {
              $Data[$key] = null;
            }
          }
        }
      } else {
        foreach ($this->data as $value) {
          array_push($Data, self::quick($value, $types, $this->completion, $this->removeNotExistRuleKey));
        }
      }

      return $Data;
    } else {
      if (is_array($this->data) && !Arr::isAssoc($this->data)) {
        foreach ($this->data as $value) {
          array_push($Data, self::quick($value, $types, $this->completion, $this->removeNotExistRuleKey));
        }
        return $Data;
      }
      return $this->setType($this->data, $types);
    }

    return false;
  }
  /**
   * 快速调用
   *
   * @param mixed $target 被转换的目标
   * @param string|array $type 类型规则或者目标类型
   * @param boolean $completion 是否补全不存在的键
   * @param boolean $removeNotExistRuleKey 是否剔除规则不存在的键
   * @return mixed 转换后的数据
   */
  public static function quick($target, $type = null, $completion = false, $removeNotExistRuleKey = false)
  {
    $DC = new DataConversion($type, $completion, $removeNotExistRuleKey);
    if ($type) {
      $DC->data($target);
      return $DC->convert();
    } else {
      return $DC->auto($target);
    }
  }
}
