<?php


namespace kernel\Foundation\HTTP\Request;

use kernel\Foundation\Data\DataConversion;
use kernel\Foundation\ReturnResult\ReturnResult;
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
   * 数据校验规则或者数据校验器
   *
   * @var Validator|array|null
   */
  protected $validator = null;
  /**
   * 实例化请求数据类
   *
   * @param DataConversion|array|null $dataConversion 数据转换规则
   * @param Validator|array|null $validator 数据校验规则或者数据校验器
   */
  public function __construct($dataConversion = null, $validator = null)
  {
    $this->dataConversion = $dataConversion;
    $this->validator = $validator;
  }
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
   * @return mixed
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
      } else if ($completion) {
        $data[$key] = null;
      }
    }

    return $data;
  }
  /**
   * 校验器结果
   *
   * @var ReturnResult
   */
  public $validatedResult = null;
  /**
   * 处理数据
   * 会先执行校验器再使用数据转换器转换数据
   * 数据转换器转换完后会把转换后的数据赋值到当前实例的data属性
   *
   * @return void
   */
  public function handle()
  {
    $this->validatedResult = new ReturnResult(true);
    if (!empty($this->validator)) {
      if (is_array($this->validator)) {
        foreach ($this->validator as $validatorItem) {
          if (!($validatorItem instanceof Validator || $validatorItem instanceof ValidateRules)) {
            $this->validatedResult = new ReturnResult(null, 500, 500, "控制器的校验器字段必须传入Validator实例或者ValidateRules实例");
            return;
          }
        }
        $Validator = new Validator(new ValidateArray($this->validator), $this->data, $this->data);
        $this->validatedResult = $Validator->validate();
      } else {
        if (!($this->validator instanceof Validator || $this->validator instanceof ValidateRules)) {
          $this->validatedResult = new ReturnResult(null, 500, 500, "控制器的校验器字段必须传入Validator实例或者ValidateRules实例");
          return;
        }
        if ($this->validator instanceof Validator) {
          $this->validatedResult = $this->validator->data($this->data)->fullData($this->data)->validate();
        } else {
          $Validator = new Validator($this->validator, $this->data, $this->data);
          $this->validatedResult = $Validator->validate();
        }
        if ($this->validatedResult->error) return;
      }
    }

    if (!is_null($this->dataConversion)) {
      if ($this->dataConversion instanceof DataConversion) {
        $ConvertedData = $this->dataConversion->data($this->data)->convert();
        if ($ConvertedData !== false) {
          $this->data = $ConvertedData;
        }
      } else {
        $ConvertedData = DataConversion::quick($this->data, $this->dataConversion, false, true);
        if ($ConvertedData !== false) {
          $this->data = $ConvertedData;
        }
      }
    }
  }
}
