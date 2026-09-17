<?php


namespace kernel\Foundation\HTTP\Request;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Data\Mutator;
use kernel\Foundation\Exception\Error;
use kernel\Foundation\Result;
use kernel\Foundation\Validation\Rules;
use kernel\Foundation\Validation\Rule;
use kernel\Foundation\Validation\Validator;

/**
 * 请求数据包
 *
 * 承载单一请求来源（如路由参数、查询串、请求体、请求头）的原始数据，
 * 并提供「校验 → 转换 → 读取/写入/合并/删除」能力：
 * - 校验：通过 $validator 约束字段（Rule 实例 / Rules 关联数组 / Validator 实例）；
 * - 转换：通过 $mutator 对数据做映射、类型转换、字段裁剪；
 * - 读写：get()/some()/has() 支持点号路径与通配符，set()/fill()/remove() 支持写入与合并。
 *
 * 典型流程：fill() 注入数据 → prepare() 先校验再转换（转换结果原地写回 $data）→ 业务侧 get()/some() 取值。
 */
class RequestData
{
  /**
   * 原始数据 / 当前数据
   *
   * 由 fill() 注入而来；prepare() 完成校验与转换后，会被原地替换为「转换后的数据」。
   *
   * @var array
   */
  protected $data = [];
  /**
   * 数据转换规则
   *
   * 可为：
   * - null：不做转换，原样返回；
   * - Mutator 实例：直接使用其转换逻辑；
   * - 数组：按（fields, completion=false, removeNotExistRuleKey=true）交给 Mutator 处理，
   *   即「仅保留规则覆盖的字段，其余键被剔除」。
   *
   * @var Mutator|array|null
   */
  protected $mutator = null;
  /**
   * 数据校验规则 / 校验器
   *
   * 可为：
   * - null：不校验；
   * - 关联数组：字段名 => Rule 实例（与 Rules 构造契约一致），prepare() 会包成 Validator 校验；
   * - Validator 实例：直接对当前数据校验；
   * - 单个 Rule 实例：包成单字段 Validator 校验。
   *
   * @var Validator|Rule|array|null
   */
  protected $validator = null;
  /**
   * 实例化请求数据类
   *
   * @param Mutator|array|null $mutator  数据转换规则（null/Mutator 实例/字段映射数组）
   * @param Validator|Rule|array|null $validator 数据校验规则或校验器（null/Rule 实例/关联数组/Validator 实例）
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
   * 写入单个键值（覆盖式）
   *
   * 不做点号路径展开：键名整体作为一级键写入（即 $key 含 "." 时按字面量存储，不会被拆成嵌套）。
   * 返回当前实例以支持链式调用。
   *
   * @param string $key 键名（按字面量存储，不解析点号路径）
   * @param mixed $value 值
   * @return static
   */
  public function set($key, $value)
  {
    $this->data[$key] = $value;

    return $this;
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
   * 最近一次 prepare() 的校验结果
   *
   * 仅在校验未通过（error=true）时业务侧需要读取；prepare() 开头即初始化为「通过」，
   * 无校验器时仍为通过态。注意默认值为 null（早于 prepare() 调用前尚未赋值）。
   *
   * @var Result|null
   */
  public $validatedResult = null;
  /**
   * 准备数据：先校验，再转换，最后把转换结果写回 $data
   *
   * 执行顺序：
   * 1. 初始化 validatedResult 为「通过」；
   * 2. 若设置了 $validator，按类型（关联数组 / Validator 实例 / 单个 Rule 实例）执行校验，
   *    未通过则把错误写入 validatedResult 并返回 false；
   * 3. 通过后用 $mutator 转换数据（无转换规则则跳过），转换成功写回 $data。
   *
   * @return boolean 校验通过且（若有转换）转换成功时为 true；否则 false
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
