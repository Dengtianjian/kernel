<?php


namespace kernel\Foundation\HTTP\Request;

use kernel\Foundation\Data\DataConversion;
use kernel\Foundation\Exception\Exception;
use kernel\Foundation\Output;
use kernel\Foundation\Validate\ValidateArray;
use kernel\Foundation\Validate\ValidateRules;
use kernel\Foundation\Validate\Validator;

class RequestData
{
  /**
   * 数据
   *
   * @var array
   */
  protected $data = [];
  /**
   * 数据转换规则
   *
   * @var DataConversion|array|null
   */
  protected $dataConversion = null;
  /**
   * 是否存在某个键
   *
   * @param string $key 键名
   * @return boolean
   */
  public function has($key)
  {
    return isset($this->data[$key]);
  }
  /**
   * 获取某个键的值
   *
   * @param string $key 键名
   * @return string
   */
  public function get($key)
  {
    if (!$this->has($key)) return null;

    return $this->data[$key];
  }
  /**
   * 批量获取某些键的值
   *
   * @param string[] $keys 键名索引数组
   * @return array
   */
  public function some(
    $keys = null,
    $completion = false
  ) {
    $data = [];
    if ($keys === null) {
      $data = $this->data;
      if ($completion) {
        $data = DataConversion::quick($data, $this->dataConversion, true);
      }
      return $data;
    };
    foreach ($keys as $key) {
      if ($this->has($key)) {
        $data[$key] = $this->get($key);
      } else {
        $data[$key] = null;
      }
    }

    return $data;
  }
  /**
   * 处理数据
   * 会先执行校验器再使用数据转换器转换数据
   * 校验器执行途中有问题会直接抛出错误
   * 数据转换器转换完后会把转换后的数据赋值到当前实例的data属性
   *
   * @param DataConversion|array $DataConversion 数据转换器或者数转换规则
   * @param Validator|Validator[] $Validator 校验器或者校验器数组
   * @return mixed 数据
   */
  public function handle($DataConversion = null, $Validator = null)
  {
    if (!empty($Validator)) {
      $ValidatedResult = null;

      if (is_array($Validator)) {
        foreach ($Validator as $validatorItem) {
          if (!($validatorItem instanceof Validator || $validatorItem instanceof ValidateRules)) {
            throw new Exception("控制器的校验器字段必须传入Validator实例或者ValidateRules实例");
          }
        }
        $Validator = new Validator(new ValidateArray($Validator), $this->data, $this->data);
        $ValidatedResult = $Validator->validate();
      } else {
        if (!($Validator instanceof Validator || $Validator instanceof ValidateRules)) {
          throw new Exception("控制器的校验器字段必须传入Validator实例或者ValidateRules实例");
        }
        if ($Validator instanceof Validator) {
          $ValidatedResult = $Validator->data($this->data)->fullData($this->data)->validate();
        } else {
          $Validator = new Validator($Validator, $this->data, $this->data);
          $ValidatedResult = $Validator->validate();
        }
      }

      if ($ValidatedResult->error) {
        $ValidatedResult->throwError();
      }
    }

    if (!is_null($DataConversion)) {
      $this->dataConversion = $DataConversion;
      if ($DataConversion instanceof DataConversion) {
        $ConvertedData = $DataConversion->data($this->data)->convert();
        if ($ConvertedData !== false) {
          $this->data = $ConvertedData;
        }
      } else {
        $ConvertedData = DataConversion::quick($this->data, $DataConversion, false, true);
        if ($ConvertedData !== false) {
          $this->data = $ConvertedData;
        }
      }
    }

    return $this->data;
  }
}
