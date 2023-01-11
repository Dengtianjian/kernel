<?php

namespace gstudio_kernel\Foundation;

if (!defined('IN_DISCUZ')) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Data\Arr;

//* 规则参考：https://github.com/yiminghe/async-validator


class Validator
{
  private $errors = [
    "required" => "%s %s"
  ];
  private $rules = [];
  private $data = [];
  private $reportErrorDirectly = true;
  private $errorType = "";
  private $errorId = "";
  private $errorParams = [];
  function __construct($rules, $data)
  {
    $this->rules = $rules;
    $this->data = $data;
    $this->errors['required'] = Lang::value("kernel/validator/pleaseInput") . " %s %s";
  }
  private function check($rules, $data)
  {
    $result = true;
    foreach ($rules as $fieldName => $ruleItem) {
      $checkData = $data[$fieldName];

      //* 如果规则和数据是数组的话就需要递归校验
      if (is_array($checkData) && is_array($ruleItem)) {
        $result = $this->check($ruleItem, $checkData);
        break;
      }

      //* 如果规则是个函数就直接执行
      if (is_callable($ruleItem)) {
        if (!call_user_func($ruleItem, $fieldName, $checkData)) {
          $result = $ruleItem;
          $this->errorType = "callable error";
          $this->errorId = "callableError";
          $this->errorParams = [];
          break;
        }
      }

      //* 如果校验的数据不存在以及规则不是一个数组就跳过这条字段
      //* 默认所有字段都是非必填的
      if (!isset($checkData) && !is_array($ruleItem)) {
        continue;
      }
      //* 没数据并且规则还是数组 但是该字段是必填的
      if (!isset($checkData) && is_array($ruleItem)) {
        if ($ruleItem['required'] && Arr::isAssoc($ruleItem)) {
          $result = $ruleItem;
          $this->errorType = "field required";
          break;
        }
      }
      //* 如果规则是个 索引数组，说明该数组的元素都是类型，进行多种 或者 | 类型校验，只要命中其中一个类型即可
      if (is_array($ruleItem) && !Arr::isAssoc($ruleItem)) {
        if (!in_array(gettype($checkData), $ruleItem)) {
          $result = $ruleItem;
          $this->errorType = "type error";
          break;
        }
      }

      if (!isset($checkData)) {
        continue;
      }

      //* 如果规则是字符串类型，就直接校验字段的类型
      if (is_string($ruleItem)) {
        if (gettype($checkData) !== $ruleItem) {
          $result = $ruleItem;
          $this->errorType = "type error";
          break;
        }
      }

      //* 剩下的就是规则是数组情况下
      //* 类型校验
      if ($ruleItem['type']) {
        //* 多种类型 或 校验。
        if (is_array($ruleItem['type'])) {
          if (!in_array(gettype($checkData), $ruleItem['type'])) {
            $result = $ruleItem;
            $this->errorType = "type error";
            break;
          }
        } else {
          if (gettype($checkData) !== $ruleItem['type']) {
            $result = $ruleItem;
            $this->errorType = "type error";
            break;
          }
        }
      }
      //* 长度校验
      if ($ruleItem['length']) {
        if (function_exists("mb_strlen")) {
          if (mb_strlen($checkData) !== $ruleItem['length']) {
            $result = $ruleItem;
            $this->errorType = "length error";
            break;
          }
        } else {
          if (strlen($checkData) !== $ruleItem['length']) {
            $result = $ruleItem;
            $this->errorType = "length error";
            break;
          }
        }
      }
      //* 最短长度校验
      if ($ruleItem['minLength']) {
        if (function_exists("mb_strlen")) {
          if (mb_strlen($checkData) < $ruleItem['minLength']) {
            $result = $ruleItem;
            $this->errorType = "minLength error";
            break;
          }
        } else {
          if (strlen($checkData) < $ruleItem['minLength']) {
            $result = $ruleItem;
            $this->errorType = "minLength error";
            break;
          }
        }
      }
      //* 最长长度校验
      if ($ruleItem['maxLength']) {
        if (function_exists("mb_strlen")) {
          if (mb_strlen($checkData) > $ruleItem['maxLength']) {
            $result = $ruleItem;
            $this->errorType = "maxLength error";
            break;
          }
        } else {
          if (strlen($checkData) > $ruleItem['maxLength']) {
            $result = $ruleItem;
            $this->errorType = "maxLength error";
            break;
          }
        }
      }
      //* 正则校验
      if ($ruleItem['pattern'] && preg_match($ruleItem['pattern'], $checkData) == false) {
        $result = $ruleItem;
        $this->errorType = "pattern error";
        break;
      }
      //* 自定义校验
      if ($ruleItem['check']) {
        unset($ruleItem['check']);
        if (!call_user_func($ruleItem['check'], $ruleItem)) {
          $result = $ruleItem;
          $this->errorType = "callable error";
          break;
        }
      }
    }
    return $result;
  }
  public function output($yes = true)
  {
    $this->reportErrorDirectly = $yes;
    return $this;
  }
  public function validate()
  {
    $checkResult = $this->check($this->rules, $this->data);
    if (gettype($checkResult) === "boolean" && $checkResult == true) {
      return true;
    }

    $message = Lang::value("kernel/validator/verifyFailed");
    if (isset($checkResult['message'])) {
      $message = $checkResult['message'];
    } else {
      if (isset($this->errors[$this->errorId])) {
        $message = sprintf($this->errors[$this->errorId], ...$this->errorParams);
      }
    }

    $result = [
      "result" => false,
      "error" => $this->errorType,
      "rule" => $checkResult,
      "message" => $message
    ];
    if ($this->reportErrorDirectly) {
      $responseStatusCode = isset($checkResult) ? $checkResult['code'] : 400;
      Response::error(400, $responseStatusCode, $result['message'], [], $result);
    }

    return $result;
  }
}
