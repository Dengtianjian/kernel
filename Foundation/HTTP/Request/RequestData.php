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
   * 源数据
   *
   * @var array
   */
  protected $rawData = [];
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
   * @param bool $onlyRealExist 只获取实际存在没被补全的数据 实例化该类时会根据DataConversion规则补全一些不存在的参数，可以通过该参数来滤掉那些根据DataConversion规则补全的参数。例如post处理请求体数据时，假设有username、nickname字段，就算nickname没提交也会把它补全，值为null，直接写入数据库即可，因为是新增数据，没传置为空也无所谓，只要数据库有该字段即可，而patch请求处理请求体时，如果把没提交的nickname补全为null，那控制器就以为是把数据库的该字段置空，在update数据时就会把数据库的nickname字段置为空，导致数据被更改，但实际是没传nickname参数的，主要是区分插入数据和更新数据两个概念区别。
   * @return array
   */
  public function some($keys = null, $onlyRealExist = false)
  {
    $data = [];
    if ($onlyRealExist) {
      if ($keys === null) return $this->rawData;
      foreach ($keys as $key) {
        if ($this->rawData[$key]) {
          $data[$key] = $this->rawData[$key];
        } else {
          $data[$key] = null;
        }
      }

      return $data;
    } else {
      if ($keys === null) return $this->data;
      foreach ($keys as $key) {
        if ($this->has($key)) {
          $data[$key] = $this->get($key);
        } else {
          $data[$key] = null;
        }
      }

      return $data;
    }
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
      if ($DataConversion instanceof DataConversion) {
        $ConvertedData = $DataConversion->data($this->data)->convert();
        if ($ConvertedData !== false) {
          $this->data = $ConvertedData;
        }
      } else {
        $ConvertedData = DataConversion::quick($this->data, $DataConversion, true, true);
        if ($ConvertedData !== false) {
          $this->data = $ConvertedData;
        }
      }
      $this->rawData = DataConversion::quick($this->data, $DataConversion, false, true);
    }

    return $this->data;
  }
}
