<?php

namespace kernel\Foundation\Validate;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Numeric;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Output;
use kernel\Foundation\ReturnResult;

class Validator
{
  /**
   * 从校验规则类实例取到的校验规则
   *
   * @var array
   */
  protected $Rule = null;
  /**
   * 要校验的数据
   *
   * @var mixed
   */
  protected $Data = null;
  /**
   * 从校验规则类实例取到的校验错误信息
   *
   * @var array
   */
  protected $ErrorMessages = [];
  /**
   * 校验规则类实例
   *
   * @var ValidateRules
   */
  protected $ValidateRule = null;

  /**
   * 构建校验器
   *
   * @param ValidateRules $ValidateRule 校验规则实例
   * @param mixed $data 要校验的数据
   */
  public function __construct(ValidateRules $ValidateRule, $data = null)
  {
    if (!($ValidateRule instanceof ValidateRules)) {
      throw new Exception("实例化校验器第一个参数必须是校验规则类");
    }
    $this->ValidateRule = $ValidateRule;
    $Reflector = new \ReflectionClass($ValidateRule);
    if ($Reflector->hasProperty("Rule")) {
      $this->Rule = $Reflector->getProperty("Rule")->getValue($ValidateRule);
    }
    if ($Reflector->hasProperty("ErrorMessages")) {
      $this->ErrorMessages = array_merge($this->ErrorMessages, $Reflector->getProperty("ErrorMessages")->getValue($ValidateRule));
    }

    $this->Data = $data;
  }
  /**
   * 返回参数错误
   *
   * @return ReturnResult
   */
  public function ReturnParamError()
  {
    $ValidatedResult = new ReturnResult(true);
    $ValidatedResult->error(400, "400:ValidateFailed:ParamError", $this->getErrorMessage(null));
    return $ValidatedResult;
  }
  /**
   * 设置要校验的数据
   *
   * @param mixed $data 被校验的数据
   * @return Validator
   */
  public function data($data)
  {
    $this->Data = $data;
    return $this;
  }
  /**
   * 获取校验失败错误信息
   *
   * @param string $key 错误信息键
   * @return string
   */
  public function getErrorMessage($key)
  {
    return isset($this->ErrorMessages[$key]) ? $this->ErrorMessages[$key] : "参数错误";
  }
  /**
   * 校验规则
   *
   * @param mixed $Target 校验的值
   * @param array $Rule 校验规则
   * @param ValidateRules $ValidateRule 校验规则实例
   * @return ReturnResult 校验结果
   */
  protected function check($Target, $Rule, $ValidateRule = null)
  {
    $ValidatedResult = new ReturnResult(true);
    if ($ValidateRule instanceof ValidateArray) {
      if (!is_array($Target)) {
        $ValidatedResult->error(400, "400:ValidatedFalied:Array", "参数错误");
        return $ValidatedResult;
      }
      if (Arr::isAssoc($Target)) {
        foreach ($ValidateRule->all() as $key => $FieldValidateRule) {
          $FieldValidator = new Validator($FieldValidateRule, isset($Target[$key]) ? $Target[$key] : null);
          $ValidatedResult = $FieldValidator->validate();
          if ($ValidatedResult->error) {
            break;
          }
        }
      } else {
        //* 索引数组遍历校验
        foreach ($Target as $key => $Value) {
          $ValidatedResult = $this->check($Value, $Rule);
          if ($ValidatedResult->error) {
            break;
          }
        }
      }

      return $ValidatedResult;
    }

    //* 必传检测
    if (isset($Rule['required'])) {
      $checkedPass = true;
      if (is_null($Target) || is_array($Target) && empty($Target)) {
        $checkedPass = false;
      } else if (!is_numeric($Target) && empty(trim($Target))) {
        $checkedPass = false;
      }
      if (!$checkedPass) {
        $ValidatedResult->error(400, "400:ValidateFailed:Required", $this->getErrorMessage("required"), null, [
          "value" => $Target,
          "empty" => empty($Target),
          "null" => is_null($Target)
        ]);
        return $ValidatedResult;
      }
    }

    //* 类型检测
    if (isset($Rule['type'])) {
      if (!is_array($Rule['type'])) {
        if ($Rule['type'] === "int") {
          $Rule['type'] = "integer";
        }
        if ($Rule['type'] === "bool") {
          $Rule['type'] = "boolean";
        }
      }
      if (is_array($Rule['type']) && !in_array(gettype($Target), $Rule['type']) || !is_array($Rule['type']) && gettype($Target) !== $Rule['type']) {
        $ValidatedResult->error(400, "400:ValidateFailed:Type", $this->getErrorMessage("type"), null, [
          "value" => $Target,
          "type" => gettype($Target),
          "exceptType" => $Rule['type']
        ]);
        return $ValidatedResult;
      }
    }

    //* 数值最小，小于指定数值时便会返回错误
    if (isset($Rule['min'])) {
      if (is_array($Target) || is_object($Target)) {
        return $this->ReturnParamError();
      }
      $TargetTemp = Numeric::val($Target);
      if ($TargetTemp <= $Rule['min']) {
        $ValidatedResult->error(400, "400:ValidateFailed:Minimun", $this->getErrorMessage("min"), null, [
          "value" => $TargetTemp,
          "min" => $Rule['min']
        ]);
        return $ValidatedResult;
      }
    }
    //* 数值最大，大于指定数值时便会返回错误
    if (isset($Rule['max'])) {
      if (is_array($Target) || is_object($Target)) {
        return $this->ReturnParamError();
      }
      $TargetTemp = Numeric::val($Target);
      if ($TargetTemp >= $Rule['max']) {
        $ValidatedResult->error(400, "400:ValidateFailed:Maximun", $this->getErrorMessage("max"), null, [
          "value" => $TargetTemp,
          "max" => $Rule['max']
        ]);
        return $ValidatedResult;
      }
    }
    //* 数值是否在指定范围
    if (isset($Rule['range'])) {
      if (is_array($Target) || is_object($Target)) {
        return $this->ReturnParamError();
      }
      $TargetTemp = Numeric::val($Target);
      if (!($TargetTemp >= $Rule['range']['min'] && $TargetTemp <= $Rule['range']['max'])) {
        $ValidatedResult->error(400, "400:ValidateFailed:Range", $this->getErrorMessage("range"), null, [
          "value" => $TargetTemp,
          "range" => $Rule['range']
        ]);
        return $ValidatedResult;
      }
    }
    //* 最小长度
    if (isset($Rule['minLength'])) {
      $targetLength = 0;
      if (is_array($Target)) {
        $targetLength = count($Target);
      } else {
        $targetLength = strlen($Target);
        if (function_exists("mb_strlen")) {
          $targetLength = mb_strlen($Target);
        }
      }

      if ($targetLength < $Rule['minLength']) {
        $ValidatedResult->error(400, "400:ValidateFailed:MinimunLength", $this->getErrorMessage("minLength"), null, [
          "value" => $Target,
          "length" => $targetLength,
          "minLength" => $Rule['minLength']
        ]);
        return $ValidatedResult;
      }
    }
    //* 最长长度
    if (isset($Rule['maxLength'])) {
      $targetLength = 0;
      if (is_array($Target)) {
        $targetLength = count($Target);
      } else {
        $targetLength = strlen($Target);
        if (function_exists("mb_strlen")) {
          $targetLength = mb_strlen($Target);
        }
      }

      if ($targetLength > $Rule['maxLength']) {
        $ValidatedResult->error(400, "400:ValidateFaile:MaximunLength", $this->getErrorMessage("maxLength"), null, [
          "value" => $Target,
          "length" => $targetLength,
          "maxLength" => $Rule['maxLength']
        ]);
        return $ValidatedResult;
      }
    }
    //* 长度在范围值里面
    if (isset($Rule['length'])) {
      if (is_array($Target)) {
        $targetLength = count($Target);
      } else {
        $targetLength = strlen($Target);
        if (function_exists("mb_strlen")) {
          $targetLength = mb_strlen($Target);
        }
      }

      if (!($targetLength > $Rule['length']['min'] && $targetLength < $Rule['length']['max'])) {
        $ValidatedResult->error(400, "400:ValidateFailed:Length", $this->getErrorMessage("length"), null, [
          "value" => $Target,
          "length" => $targetLength,
          "exceptLength" => $Rule['length']
        ]);
        return $ValidatedResult;
      }
    }
    //* 枚举
    if (isset($Rule['enum'])) {
      if (is_array($Target) || is_object($Target)) {
        return $this->ReturnParamError();
      }
      if (!in_array($Target, $Rule['enum'])) {
        $ValidatedResult->error(400, "400:ValidateFailed:Enum", $this->getErrorMessage("enum"), null, [
          "value" => $Target,
          "list" => $Rule['enum']
        ]);
        return $ValidatedResult;
      }
    }
    //* 是否等于指定值
    if (isset($Rule['equal'])) {
      if ($Target !== $Rule['equal']) {
        $ValidatedResult->error(400, "400:ValidateFailed:Equal", $this->getErrorMessage("equal"), null, [
          "value" => $Target,
          "expect" => $Rule['equal']
        ]);
        return $ValidatedResult;
      }
    }
    //* 字符串检测 包含某个字符串或者字符串是否包含字符串数组里面的元素
    //* 数组检测 数组包含某个值或者数组包含规则数组里每个值
    if (isset($Rule['includes'])) {
      $checkedPass = true;
      if (is_array($Target)) {
        if (is_array($Rule['includes'])) {
          foreach ($Rule['includes'] as $value) {
            if (!in_array($value, $Target)) {
              $checkedPass = false;
              break;
            }
          }
        } else if (!in_array($Rule['includes'], $Target)) {
          $checkedPass = false;
        }
      } else {
        if (is_string($Target) || is_numeric($Target)) {
          $TargetTemp = strval($Target);
          if (is_array($Rule['includes'])) {
            foreach ($Rule['includes'] as $value) {
              if (is_array($value) || strpos($TargetTemp, $value) === false) {
                $checkedPass = false;
                break;
              }
            }
          } else {
            if (strpos($Target, $Rule['includes']) === false) {
              $checkedPass = false;
            }
          }
        } else {
          $checkedPass = false;
        }
      }
      if (!$checkedPass) {
        $ValidatedResult->error(400, "400:ValidateFailed:Includes", $this->getErrorMessage("includes"), null, [
          "value" => $Target,
          "include" => $Rule['includes']
        ]);
        return $ValidatedResult;
      }
    }
    //* 检测数组是否包含指定键，或者指定的键数组是否 都 存在目标数组中
    if (isset($Rule['hasKeys'])) {
      if (!is_array($Target)) {
        return $this->ReturnParamError();
      }
      $checkedPass = true;
      if (is_array($Rule['hasKeys'])) {
        foreach ($Rule['hasKeys'] as $key => $value) {
          if (!array_key_exists($value, $Target)) {
            $checkedPass = false;
            break;
          }
        }
      } else if (!array_key_exists($Rule['hasKeys'], $Target)) {
        $checkedPass = false;
      }
      if (!$checkedPass) {
        $ValidatedResult->error(400, "400:ValidateFailed:HasKeys", $this->getErrorMessage("hasKeys"), null, [
          "value" => $Target,
          "keys" => $Rule['hasKeys']
        ]);
        return $ValidatedResult;
      }
    }
    //* 正则检测，如果是数组，会把数组的每个元素都用正则校验一次
    if (isset($Rule['pattern'])) {
      if (is_array($Target) || is_object($Target)) {
        return $this->ReturnParamError();
      } else if (!preg_match($Rule['pattern'], $Target)) {
        $ValidatedResult->error(400, "400:ValidateFailed:Pattern", $this->getErrorMessage("pattern"), null, [
          "value" => $Target,
          "pattern" => $Rule['pattern']
        ]);
        return $ValidatedResult;
      }
    }
    //* 自定义校验
    if (isset($Rule['CustomValidate'])) {
      $ValidatedResult = $Rule['CustomValidate']($Target, $Rule);
    }
    //* 使用了别的校验规则
    if (isset($Rule['use']) && is_array($Rule['use']) && count($Rule['use'])) {
      foreach ($Rule['use'] as $useValidateItem) {
        $useValidator = new Validator($useValidateItem, $Target);
        $ValidatedResult = $useValidator->validate();
        if ($ValidatedResult->error) {
          break;
        }
      }
    }

    return $ValidatedResult;
  }
  /**
   * 校验
   *
   * @return ReturnResult
   */
  public function validate()
  {
    $ValidatedResult = $this->check($this->Data, $this->Rule, $this->ValidateRule);

    if ($ValidatedResult->error) {
      $ValidatedResult->addData(false, true);
    } else {
      $ValidatedResult->addData(true, true);
    }

    return $ValidatedResult;
  }
}
