<?php


namespace kernel\Foundation\HTTP\Request;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Mutator;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\Result;
use kernel\Foundation\Validation\Rules;
use kernel\Foundation\Validation\Rule;
use kernel\Foundation\Validation\Validator;

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
   * @var Mutator|array|null
   */
  protected $mutator = null;
  /**
   * 数据校验规则或者数据校验器
   *
   * @var Validator|array|null
   */
  protected $validator = null;
  /**
   * 实例化请求数据类
   *
   * @param Mutator|array|null $mutator 数据转换规则
   * @param Validator|array|null $validator 数据校验规则或者数据校验器
   */
  public function __construct($mutator = null, $validator = null)
  {
    $this->mutator = $mutator;
    $this->validator = $validator;
  }
  /**
   * 是否存在某个键
   *
   * 支持点号路径（如 user.profile.name），严格区分「键存在值为 null」与「键不存在」。
   *
   * @param string $key 键名，支持点号语法
   * @return boolean
   */
  public function has($key)
  {
    if (!is_array($this->data)) return false;

    return Arr::has($this->data, $key);
  }
  /**
   * 获取某个键的值
   *
   * 支持点号路径（如 user.profile.name）与 * 通配符（如 photos.*.url，
   * 命中时返回平铺结果数组）。
   *
   * @param string $key 键名，支持点号语法与通配符
   * @param mixed $default 键不存在时的默认值
   * @return mixed
   */
  public function get($key, $default = null)
  {
    if (!is_array($this->data)) return $default;

    return Arr::get($this->data, $key, $default);
  }
  /**
   * 获取数据
   *
   * 无参调用（$keys === null）即获取全部数据（不经转换）；
   * 传入键名则批量取部分，键名支持点号路径（如 user.profile.name，
   * 结果以路径本身为键）。「获取全部」统一走本方法无参形态，勿再新增 all()。
   *
   * @param string|string[]|null $keys 键名索引数组（传标量会被视作单个键）
   * @param boolean $completion 是否补齐缺失键为 null
   * @return array|null
   */
  public function some($keys = null, $completion = false)
  {
    if (!is_array($this->data)) return null;

    if ($keys !== null && !is_array($keys)) {
      $keys = [$keys];
    }

    $data = [];
    if ($keys === null) {
      $data = $this->data;
      if ($completion) {
        $convertedData = $this->convert($data);
        if ($convertedData !== false) {
          $data = $convertedData;
        }
      }
      return $data;
    }
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
   * 应用数据转换规则
   *
   * 无转换规则时原样返回；Mutator 实例直接使用，
   * 数组形式按（fields, completion=false, removeNotExistRuleKey=true）构造，
   * removeNotExistRuleKey=true 会剔除规则未覆盖的键。
   *
   * @param array $data 待转换数据
   * @return mixed 转换结果（转换失败返回 false）
   */
  protected function convert($data)
  {
    if (is_null($this->mutator)) return $data;

    if ($this->mutator instanceof Mutator) {
      return $this->mutator->data($data)->convert();
    }

    return (new Mutator($this->mutator, false, true))->data($data)->convert();
  }
  /**
   * 校验器结果
   *
   * @var Result
   */
  public $validatedResult = null;
  /**
   * 准备数据
   * 会先执行校验器再使用数据转换器转换数据
   * 数据转换器转换完后会把转换后的数据赋值到当前实例的data属性
   *
   * @return boolean 是否校验通过并完成转换
   */
  public function prepare()
  {
    $this->validatedResult = new Result(true);
    if (!empty($this->validator)) {
      if (is_array($this->validator)) {
        // 数组形式校验器：必须是「字段名 => Rule 实例」的关联数组（与 Rules 构造契约一致）。
        // 单个 Validator 实例应走下面的单实例分支，不能混在数组里。
        if (!Arr::isAssoc($this->validator)) {
          throw new Error("控制器的校验器数组必须为关联数组（字段名 => Rule 实例）");
        }
        foreach ($this->validator as $validatorItem) {
          if (!($validatorItem instanceof Rule)) {
            throw new Error("控制器的校验器数组的每个值必须是Rule实例");
          }
        }
        $Validator = new Validator(new Rules($this->validator), $this->data, $this->data);
        $this->validatedResult = $Validator->validate();
        if ($this->validatedResult->error) return false;
      } else {
        if (!($this->validator instanceof Validator || $this->validator instanceof Rule)) {
          throw new Error("控制器的校验器字段必须传入Validator实例或者Rule实例");
        }
        if ($this->validator instanceof Validator) {
          $this->validatedResult = $this->validator->data($this->data)->fullData($this->data)->validate();
        } else {
          $Validator = new Validator($this->validator, $this->data, $this->data);
          $this->validatedResult = $Validator->validate();
        }
        if ($this->validatedResult->error) return false;
      }
    }

    $convertedData = $this->convert($this->data);
    if ($convertedData !== false) {
      $this->data = $convertedData;
    }

    return true;
  }
  /**
   * 注入数据，与既有数据合并
   *
   * 通用能力：RequestParams 由 App::run()/Console 路由匹配完成后经 fill()
   * 注入路由参数；其它子类（query/body/header）也可按需调用。
   *
   * @param array $data 待合并的数据映射
   * @return void
   */
  public function fill(array $data)
  {
    $this->data = array_merge($this->data, $data);
  }
  /**
   * 移除指定键
   *
   * 与 fill() 对应，支持点号路径（如 user.profile.name）。键不存在时忽略，不报错。
   *
   * @param string $key 键名，支持点号语法
   * @return void
   */
  public function remove($key)
  {
    if (!is_array($this->data)) return;
    Arr::forget($this->data, $key);
  }
}
