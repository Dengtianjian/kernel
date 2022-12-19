<?php

namespace gstudio_kernel\Foundation;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use Error;
use gstudio_kernel\Foundation\Data\Arr;

class Serializer
{
  static $rules = [];
  private static $ruleName = "";
  static function getRule($name, $upperLevel = null)
  {
    if (!$upperLevel) $upperLevel = self::$rules;
    $names = explode(".", $name);
    $rule = $upperLevel[$names[0]];
    if (!$rule) return null;
    if (count($names) === 1) return $rule;
    $name = substr($name, strlen($names[0]) + 1);
    return self::getRule($name, $rule);
  }
  static function useRule($name)
  {
    self::$ruleName = $name;
    return self::class;
  }
  static function addRule($name, $rule = [], &$upperLevel = null)
  {
    $names = explode(".", $name);
    $firstName = $names[0];

    if (count($names) === 1) {
      if ($upperLevel === null) {
        $upperLevel = &self::$rules;
      }
      if (isset($upperLevel[$firstName])) {
        throw new \Error($firstName . " " . Lang::value("kernel/serializer/ruleExist"));
      }
      $upperLevel[$firstName] = $rule;

      return true;
    }

    $name = substr($name, strlen($names[0]) + 1);
    if ($upperLevel === null) {
      self::$rules[$firstName] = [];
      return self::addRule($name, $rule, self::$rules[$firstName]);
    } else {
      $upperLevel[$firstName] = [];
      return self::addRule($name, $rule, $upperLevel[$firstName]);
    }
  }
  static function serialization($RuleName, $data, $serializerName = "temp")
  {
    if ($data === null || count($data) === 0) return $data;
    if (!Arr::isAssoc($data)) {
      foreach ($data as &$dataItem) {
        if (array_key_exists("_serilizer", $dataItem)) {
          continue;
        }
        $dataItem = self::serialization($RuleName, $dataItem, $serializerName);
      }
      return $data;
    }
    if (array_key_exists("_serilizer", $data)) return $data;
    $rule = is_array($RuleName) ? $RuleName : self::getRule($RuleName);
    if (!$rule) {
      throw new Error($RuleName . " " . Lang::value("kernel/serializer/ruleNotExist"));
    }
    $dataKeys = array_keys($data);

    $ruleKeys = [];
    $fileter = array_filter(array_keys($rule), function ($key) use ($rule, &$ruleKeys) {
      if (is_numeric($key)) {
        array_push($ruleKeys, $rule[$key]);
        return false;
      }
      return true;
    });
    $ruleKeys = array_merge($ruleKeys, $fileter);
    $removeKeys = array_diff($dataKeys, $ruleKeys);
    foreach ($rule as $fieldName => $ruleItem) {
      if (is_numeric($fieldName)) {
        continue;
      }
      if (array_key_exists($fieldName, $data)) {
        if ($ruleItem === Serializer::class && self::$ruleName) {
          $data[$fieldName] = self::serialization(self::$ruleName, $data[$fieldName]);
          self::$ruleName = null;
        } else if ($ruleItem === "json") {
          if ($data[$fieldName] && is_string($data[$fieldName])) {
            $data[$fieldName] = json_decode($data[$fieldName], true);
          } else {
            $data[$fieldName] = [];
          }
        } else if (is_callable($ruleItem)) {
          $data[$fieldName] = $ruleItem($data[$fieldName], $data);
        }
      } else {
        if ($ruleItem === Serializer::class) {
          $data[$fieldName] = null;
        } else if ($ruleItem === "json") {
          if ($data[$fieldName]) {
            $data[$fieldName] = json_decode($data[$fieldName], true);
          } else {
            $data[$fieldName] = [];
          }
        } elseif (is_callable($ruleItem)) {
          $data[$fieldName] = $ruleItem($data);
        } else {
          $data[$fieldName] = $ruleItem;
        }
      }
    }
    foreach ($removeKeys as $keyItem) {
      unset($data[$keyItem]);
    }
    $data['_serilizer'] = is_array($RuleName) ? $serializerName : $RuleName;

    return $data;
  }
}
