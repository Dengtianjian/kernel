<?php

namespace kernel\Foundation\Validation;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Numeric;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\Result;

class Validator
{
  /**
   * 从校验规则类实例取到的校验规则
   *
   * @var array
   */
  protected $rule = null;
  /**
   * 要校验的数据
   *
   * @var mixed
   */
  protected $data = null;
  /**
   * 全数据，有可能被校验的数据是数组里的某个元素，而这个变量用来存储被校验数据所属的数组
   *
   * @var mixed
   */
  protected $fullData = null;
  /**
   * 从校验规则类实例取到的校验错误信息
   *
   * @var array
   */
  protected $errorMessages = [];
  /**
   * 校验规则类实例
   *
   * @var Rule
   */
  protected $validateRule = null;
  /**
   * 当前校验的字段名（Rules 关联数组场景下由外部设置）
   *
   * @var string|null
   */
  protected $fieldName = null;

  /**
   * 构建校验器
   *
   * @param Rule $validateRule 校验规则实例
   * @param mixed $data 要校验的数据
   */
  public function __construct(Rule $validateRule, $data = null, $fullData = null)
  {
    if (!($validateRule instanceof Rule)) {
      throw new Error("实例化校验器第一个参数必须是校验规则类");
    }
    $this->validateRule = $validateRule;
    $this->rule = $validateRule->rule;
    $this->errorMessages = $validateRule->errorMessages;

    $this->data = $data;
    $this->fullData = $fullData;
  }
  /**
   * 返回参数错误
   *
   * @return Result
   */
  public function ReturnParamError()
  {
    $validatedResult = new Result(true);
    $validatedResult->error(400, "400:ValidateFailed:ParamError", $this->getErrorMessage(null));
    return $validatedResult;
  }
  /**
   * 设置要校验的数据
   *
   * @param mixed $data 被校验的数据
   * @return Validator
   */
  public function data($data)
  {
    $this->data = $data;
    return $this;
  }
  /**
   * 设置被校验的数据所属的数据集
   *
   * @param mixed $data 数据集
   * @return Validator
   */
  public function fullData($data)
  {
    $this->fullData = $data;
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
    return isset($this->errorMessages[$key]) ? $this->errorMessages[$key] : "参数错误";
  }

  /**
   * 应用条件规则（sometimes）
   *
   * 遍历 Rules 上的条件规则，执行回调（传入完整数据），
   * 若返回 true 则将条件规则合并到主规则列表。
   *
   * @param Rules $rules Rules 实例
   * @param mixed $data  完整数据（用于回调判断）
   */
  protected function applyConditionalRules(Rules $rules, $data)
  {
    foreach ($rules->getConditionalRules() as $item) {
      $shouldApply = call_user_func($item['callback'], $data);
      if ($shouldApply) {
        $rules->addRule($item['attribute'], $item['rule']);
      }
    }
  }

  /**
   * 通过通配符展开，将 photos.*.url → 逐一校验每个元素的 url 字段
   *
   * @param mixed $data        完整数据
   * @param string $fieldName  含 * 的字段名（如 photos.*.url）
   * @param Rule   $fieldRule  该字段的校验规则
   * @param mixed  $parentData 父级数据（用于子校验器的 fullData）
   * @return Result
   */
  protected function validateWildcardField($data, $fieldName, Rule $fieldRule, $parentData)
  {
    $segments = explode('.', $fieldName);
    $wildcardIdx = null;
    foreach ($segments as $i => $seg) {
      if ($seg === '*') {
        $wildcardIdx = $i;
        break;
      }
    }

    if ($wildcardIdx === null) {
      return new Result(true);
    }

    // 到达通配符前的路径
    $prefixSegments = array_slice($segments, 0, $wildcardIdx);
    $suffixSegments = array_slice($segments, $wildcardIdx + 1);
    $suffixKey = implode('.', $suffixSegments);

    // 导航到 * 的父级
    $cursor = $data;
    foreach ($prefixSegments as $seg) {
      if (!is_array($cursor) || !array_key_exists($seg, $cursor)) {
        return new Result(true); // 路径不存在，跳过
      }
      $cursor = $cursor[$seg];
    }

    if (!is_array($cursor)) {
      return new Result(true);
    }

    $validatedResult = new Result(true);
    foreach ($cursor as $i => $element) {
      if ($suffixKey !== '') {
        $value = Arr::get($element, $suffixKey);
      } else {
        $value = $element;
      }
      $subValidator = new Validator($fieldRule, $value, $parentData);
      $validatedResult = $subValidator->validate();
      if ($validatedResult->error) {
        break;
      }
    }

    return $validatedResult;
  }

  /**
   * 校验规则
   *
   * @param mixed $target 校验的值
   * @param array $rule 校验规则
   * @param Rule $validateRule 校验规则实例
   * @return Result 校验结果
   */
  protected function check($target, $rule, $validateRule = null, $data = null)
  {
    $validatedResult = new Result(true);
    if ($validateRule instanceof Rules) {
      if (!is_array($target)) {
        $validatedResult->error(400, "400:ValidateFailed:Array", "参数错误");
        return $validatedResult;
      }

      // 关联数组 → 按字段规则逐一校验
      if (Arr::isAssoc($target)) {
        foreach ($validateRule->all() as $fieldName => $fieldValidateRule) {
          $fieldRule = $fieldValidateRule->rule;

          // 通配符字段：展开后逐个校验
          if (str_contains($fieldName, '*')) {
            $validatedResult = $this->validateWildcardField($target, $fieldName, $fieldValidateRule, $target);
            if ($validatedResult->error) {
              break;
            }
            continue;
          }

          // prohibited：字段不能存在于输入数据中
          if (isset($fieldRule['prohibited'])) {
            if (Arr::has($target, $fieldName)) {
              $fieldValidator = new Validator($fieldValidateRule, null, $target);
              $validatedResult = $fieldValidator->ReturnParamError();
              break;
            }
            continue;
          }

          // 点号语法字段：通过 Arr::get 获取深层值
          $fieldValue = Arr::get($target, $fieldName);
          $fieldValidator = new Validator($fieldValidateRule, $fieldValue, $target);
          $fieldValidator->setFieldName($fieldName);
          $validatedResult = $fieldValidator->validate();
          if ($validatedResult->error) {
            break;
          }
        }
      } else {
        // 索引数组 → 遍历每个元素递归校验
        foreach ($target as $key => $value) {
          $validatedResult = $this->check($value, $rule, null, $target);
          if ($validatedResult->error) {
            break;
          }
        }
      }

      return $validatedResult;
    }

    //* nullable：允许值为 null。值为 null 时跳过后续所有校验，直接通过
      if (isset($rule['nullable']) && is_null($target)) {
        return $validatedResult;
      }
      //* present：允许值为空字符串。值为空字符串时跳过后续所有校验，直接通过
      if (isset($rule['present']) && $target === "") {
        return $validatedResult;
      }
    //* 必传检测
      if (isset($rule['required'])) {
        $checkedPass = true;
        if (is_null($target)) {
          $checkedPass = false;
        } else if (is_array($target)) {
          $checkedPass = !empty($target);
        } else if (!is_numeric($target) && strlen(trim((string)$target)) === 0) {
          $checkedPass = false;
        }
        if (!$checkedPass) {
          $validatedResult->error(400, "400:ValidateFailed:Required", $this->getErrorMessage("required"), [
            "value" => $target,
            "empty" => empty($target),
            "null" => is_null($target)
          ]);
          return $validatedResult;
        }
      }
      //* required_if：当另一字段的值在指定列表中时，本字段必填
      if (isset($rule['requiredIf'])) {
        $anotherValue = Arr::get($data, $rule['requiredIf']['field']);
        $shouldBeRequired = in_array($anotherValue, $rule['requiredIf']['values'], true);
        if ($shouldBeRequired) {
          $checkedPass = true;
          if (is_null($target)) {
            $checkedPass = false;
          } else if (is_array($target)) {
            $checkedPass = !empty($target);
          } else if (!is_numeric($target) && strlen(trim((string)$target)) === 0) {
            $checkedPass = false;
          }
          if (!$checkedPass) {
            $validatedResult->error(400, "400:ValidateFailed:RequiredIf", $this->getErrorMessage("requiredIf"), [
              "value"       => $target,
              "anotherField" => $rule['requiredIf']['field'],
              "anotherValue" => $anotherValue,
              "expectValues" => $rule['requiredIf']['values'],
            ]);
            return $validatedResult;
          }
        }
      }
      //* required_unless：除非另一字段的值在指定列表中，否则本字段必填
      if (isset($rule['requiredUnless'])) {
        $anotherValue = Arr::get($data, $rule['requiredUnless']['field']);
        $shouldSkipRequired = in_array($anotherValue, $rule['requiredUnless']['values'], true);
        if (!$shouldSkipRequired) {
          $checkedPass = true;
          if (is_null($target)) {
            $checkedPass = false;
          } else if (is_array($target)) {
            $checkedPass = !empty($target);
          } else if (!is_numeric($target) && strlen(trim((string)$target)) === 0) {
            $checkedPass = false;
          }
          if (!$checkedPass) {
            $validatedResult->error(400, "400:ValidateFailed:RequiredUnless", $this->getErrorMessage("requiredUnless"), [
              "value"        => $target,
              "anotherField" => $rule['requiredUnless']['field'],
              "anotherValue"  => $anotherValue,
              "expectValues"  => $rule['requiredUnless']['values'],
            ]);
            return $validatedResult;
          }
        }
      }
      //* 类型检测
      if (isset($rule['type'])) {
        if (is_array($rule['type']) && !in_array(gettype($target), $rule['type']) || !is_array($rule['type']) && gettype($target) !== $rule['type']) {
          $validatedResult->error(400, "400:ValidateFailed:Type", $this->getErrorMessage("type"), [
            "value" => $target,
            "type" => gettype($target),
            "exceptType" => $rule['type']
          ]);
          return $validatedResult;
        }
      }
      //* 数值最小，小于指定数值时便会返回错误
      if (isset($rule['min'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        }
        $targetTemp = Numeric::val($target);
        if ($targetTemp < $rule['min']) {
          $validatedResult->error(400, "400:ValidateFailed:Minimun", $this->getErrorMessage("min"), [
            "value" => $targetTemp,
            "min" => $rule['min']
          ]);
          return $validatedResult;
        }
      }
      //* 数值最大，大于指定数值时便会返回错误
      if (isset($rule['max'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        }
        $targetTemp = Numeric::val($target);
        if ($targetTemp > $rule['max']) {
          $validatedResult->error(400, "400:ValidateFailed:Maximun", $this->getErrorMessage("max"), [
            "value" => $targetTemp,
            "max" => $rule['max']
          ]);
          return $validatedResult;
        }
      }
      //* 数值是否在指定范围
      if (isset($rule['range'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        }
        $targetTemp = Numeric::val($target);
        if (!($targetTemp >= $rule['range']['min'] && $targetTemp <= $rule['range']['max'])) {
          $validatedResult->error(400, "400:ValidateFailed:Range", $this->getErrorMessage("range"), [
            "value" => $targetTemp,
            "range" => $rule['range']
          ]);
          return $validatedResult;
        }
      }
      //* 最小长度
      if (isset($rule['minLength'])) {
        $targetLength = $this->getTargetLength($target);

        if ($targetLength < $rule['minLength']) {
          $validatedResult->error(400, "400:ValidateFailed:MinimumLength", $this->getErrorMessage("minLength"), [
            "value" => $target,
            "length" => $targetLength,
            "minLength" => $rule['minLength']
          ]);
          return $validatedResult;
        }
      }
      //* 最长长度
      if (isset($rule['maxLength'])) {
        $targetLength = $this->getTargetLength($target);

        if ($targetLength > $rule['maxLength']) {
          $validatedResult->error(400, "400:ValidateFailed:MaximumLength", $this->getErrorMessage("maxLength"), [
            "value" => $target,
            "length" => $targetLength,
            "maxLength" => $rule['maxLength']
          ]);
          return $validatedResult;
        }
      }
      //* 长度在范围值里面
      if (isset($rule['length'])) {
        $targetLength = $this->getTargetLength($target);

        if (!($targetLength >= $rule['length']['min'] && $targetLength <= $rule['length']['max'])) {
          $validatedResult->error(400, "400:ValidateFailed:Length", $this->getErrorMessage("length"), [
            "value" => $target,
            "length" => $targetLength,
            "exceptLength" => $rule['length']
          ]);
          return $validatedResult;
        }
      }
      //* 是否等于指定值
      if (isset($rule['equal'])) {
        if ($target !== $rule['equal']) {
          $validatedResult->error(400, "400:ValidateFailed:Equal", $this->getErrorMessage("equal"), [
            "value" => $target,
            "expect" => $rule['equal']
          ]);
          return $validatedResult;
        }
      }
      //* 字符串检测 包含某个字符串或者字符串是否包含字符串数组里面的元素
      //* 数组检测 数组包含某个值或者数组包含规则数组里每个值
      if (isset($rule['includes'])) {
        $checkedPass = true;
        if (is_array($target)) {
          if (is_array($rule['includes'])) {
            foreach ($rule['includes'] as $value) {
              if (!in_array($value, $target)) {
                $checkedPass = false;
                break;
              }
            }
          } else if (!in_array($rule['includes'], $target)) {
            $checkedPass = false;
          }
        } else {
          if (is_string($target) || is_numeric($target)) {
            $targetTemp = strval($target);
            if (is_array($rule['includes'])) {
              foreach ($rule['includes'] as $value) {
                if (is_array($value) || strpos($targetTemp, $value) === false) {
                  $checkedPass = false;
                  break;
                }
              }
            } else {
              if (strpos($target, $rule['includes']) === false) {
                $checkedPass = false;
              }
            }
          } else {
            $checkedPass = false;
          }
        }
        if (!$checkedPass) {
          $validatedResult->error(400, "400:ValidateFailed:Includes", $this->getErrorMessage("includes"), [
            "value" => $target,
            "include" => $rule['includes']
          ]);
          return $validatedResult;
        }
      }
      //* 检测数组是否包含指定键，或者指定的键数组是否 都 存在目标数组中
      if (isset($rule['hasKeys'])) {
        if (!is_array($target)) {
          return $this->ReturnParamError();
        }
        $checkedPass = true;
        if (is_array($rule['hasKeys'])) {
          foreach ($rule['hasKeys'] as $key => $value) {
            if (!array_key_exists($value, $target)) {
              $checkedPass = false;
              break;
            }
          }
        } else if (!array_key_exists($rule['hasKeys'], $target)) {
          $checkedPass = false;
        }
        if (!$checkedPass) {
          $validatedResult->error(400, "400:ValidateFailed:HasKeys", $this->getErrorMessage("hasKeys"), [
            "value" => $target,
            "keys" => $rule['hasKeys']
          ]);
          return $validatedResult;
        }
      }
      //* 正则检测，如果是数组，会把数组的每个元素都用正则校验一次
      if (isset($rule['pattern'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        } else if (!preg_match($rule['pattern'], $target)) {
          $validatedResult->error(400, "400:ValidateFailed:Pattern", $this->getErrorMessage("pattern"), [
            "value" => $target,
            "pattern" => $rule['pattern']
          ]);
          return $validatedResult;
        }
      }
      //* 邮箱校验
      if (isset($rule['email'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        } else if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
          $validatedResult->error(400, "400:ValidateFailed:Email", $this->getErrorMessage("email"), [
            "value" => $target,
          ]);
          return $validatedResult;
        }
      }
      //* URL 校验
      if (isset($rule['url'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        } else if (!filter_var($target, FILTER_VALIDATE_URL)) {
          $validatedResult->error(400, "400:ValidateFailed:Url", $this->getErrorMessage("url"), [
            "value" => $target,
          ]);
          return $validatedResult;
        }
      }
      //* IP 地址校验（支持 IPv4 和 IPv6）
      if (isset($rule['ip'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        } else if (!filter_var($target, FILTER_VALIDATE_IP)) {
          $validatedResult->error(400, "400:ValidateFailed:Ip", $this->getErrorMessage("ip"), [
            "value" => $target,
          ]);
          return $validatedResult;
        }
      }
      //* 日期字符串校验
      if (isset($rule['date'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        }
        $parsed = date_parse((string)$target);
        if ($parsed === false || !is_array($parsed) || $parsed['error_count'] > 0 || ($parsed['year'] === false && $parsed['month'] === false && $parsed['day'] === false)) {
          $validatedResult->error(400, "400:ValidateFailed:Date", $this->getErrorMessage("date"), [
            "value" => $target,
          ]);
          return $validatedResult;
        }
      }
      //* 日期格式校验
      if (isset($rule['dateFormat'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        }
        $dateObj = \DateTime::createFromFormat($rule['dateFormat'], $target);
        if (!$dateObj || $dateObj->format($rule['dateFormat']) !== $target) {
          $validatedResult->error(400, "400:ValidateFailed:DateFormat", $this->getErrorMessage("dateFormat"), [
            "value"  => $target,
            "format" => $rule['dateFormat'],
          ]);
          return $validatedResult;
        }
      }
      //* 值是否在给定列表中
      if (isset($rule['in'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        } else if (!in_array($target, $rule['in'], true)) {
          $validatedResult->error(400, "400:ValidateFailed:In", $this->getErrorMessage("in"), [
            "value" => $target,
            "list"  => $rule['in'],
          ]);
          return $validatedResult;
        }
      }
      //* 值是否不在给定列表中
      if (isset($rule['notIn'])) {
        if (is_array($target) || is_object($target)) {
          return $this->ReturnParamError();
        } else if (in_array($target, $rule['notIn'], true)) {
          $validatedResult->error(400, "400:ValidateFailed:NotIn", $this->getErrorMessage("notIn"), [
            "value" => $target,
            "list"  => $rule['notIn'],
          ]);
          return $validatedResult;
        }
      }
      //* confirmed：与 字段名_confirmation 的值一致
      if (isset($rule['confirmed'])) {
        $confirmedField = $this->fieldName !== null ? $this->fieldName . '_confirmation' : null;
        $confirmedValue = ($confirmedField !== null && is_array($data)) ? Arr::get($data, $confirmedField) : null;
        if ($target !== $confirmedValue) {
          $validatedResult->error(400, "400:ValidateFailed:Confirmed", $this->getErrorMessage("confirmed"), [
            "value"           => $target,
            "confirmedValue"  => $confirmedValue,
          ]);
          return $validatedResult;
        }
      }
      //* same：与另一个字段的值相同
      if (isset($rule['same'])) {
        $otherValue = Arr::get($data, $rule['same']);
        if ($target !== $otherValue) {
          $validatedResult->error(400, "400:ValidateFailed:Same", $this->getErrorMessage("same"), [
            "value"      => $target,
            "otherField" => $rule['same'],
            "otherValue" => $otherValue,
          ]);
          return $validatedResult;
        }
      }
      //* different：与另一个字段的值不同
      if (isset($rule['different'])) {
        $otherValue = Arr::get($data, $rule['different']);
        if ($target === $otherValue) {
          $validatedResult->error(400, "400:ValidateFailed:Different", $this->getErrorMessage("different"), [
            "value"      => $target,
            "otherField" => $rule['different'],
            "otherValue" => $otherValue,
          ]);
          return $validatedResult;
        }
      }

    //* 自定义校验
    if (isset($rule['CustomValidate'])) {
      $validatedResult = $rule['CustomValidate']($target, $rule, $data);
      if ($validatedResult instanceof Result && $validatedResult->error) {
        return $validatedResult;
      }
    }
    //* 使用了别的校验规则
    if (isset($rule['use']) && is_array($rule['use']) && count($rule['use'])) {
      foreach ($rule['use'] as $useValidateItem) {
        $useValidator = new Validator($useValidateItem, $target);
        if ($this->fieldName !== null) {
          $useValidator->setFieldName($this->fieldName);
        }
        $validatedResult = $useValidator->validate();
        if ($validatedResult->error) {
          break;
        }
      }
    }

    return $validatedResult;
  }

  /**
   * 设置当前校验的字段名（供 Rules 关联数组场景使用）
   *
   * @param string $name 字段名称
   * @return $this
   */
  public function setFieldName($name)
  {
    $this->fieldName = $name;
    return $this;
  }

  /**
   * 获取目标值的长度（字符串取字符数，数组取元素个数）
   *
   * @param mixed $target
   * @return int
   */
  private function getTargetLength($target)
  {
    if (is_array($target)) {
      return count($target);
    }
    return function_exists("mb_strlen") ? mb_strlen($target) : strlen($target);
  }

  /**
   * 校验
   *
   * 在校验开始前处理条件规则（sometimes），随后执行 check。
   *
   * @return Result
   */
  public function validate()
  {
    // 步骤 A：处理条件规则（sometimes）
    // 将条件规则的完整数据传递给回调，满足条件则合并到主规则列表
    if ($this->validateRule instanceof Rules) {
      $fullData = $this->fullData ?? $this->data;
      $this->applyConditionalRules($this->validateRule, $fullData);
    }

    // 步骤 B + C：点号语法取值 + 通配符展开 + 逐条执行校验
    $validatedResult = $this->check($this->data, $this->rule, $this->validateRule, $this->fullData);

    if ($validatedResult->error) {
      $validatedResult->addData(false, true);
    } else {
      $validatedResult->addData(true, true);
    }

    return $validatedResult;
  }
}
