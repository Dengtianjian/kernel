<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\Data\DataConversion;
use kernel\Foundation\Data\Serializer;
use kernel\Foundation\Data\Transform;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;

/**
 * 控制器基类
 *
 * 所有业务控制器的抽象基类，封装了完整的请求-响应处理流程。
 *
 * ## 生命周期
 * ```
 * __construct  →  boot  →  before  →  data (或自定义 handle)  →  after
 * ```
 * 1. **构造阶段** — 初始化 requestQuery/requestBody，执行参数校验与类型转换
 * 2. **boot**          — 子类初始化钩子（在 query/body 处理之后触发）
 * 3. **before**        — 校验拦截：query/body 校验失败则设 response 为错误并终止后续流程
 * 4. **data / handle** — 业务逻辑入口，子类必须覆盖
 * 5. **after**         — 响应后处理：执行 Transform 数据变换、Serializer 序列化
 *
 * ## 子类配置
 * 子类可通过覆盖以下属性控制请求/响应行为：
 * - `$requestQuerySerializes` / `$requestBodySerializes` — 对 GET/Body 参数做类型转换
 * - `$requestQueryValidator` / `$requestBodyValidator`   — 对 GET/Body 参数做校验
 * - `$responseSerializes` — 对响应数据做序列化输出
 * - `$allowedTransformers` — 允许客户端通过 _transform 参数调用的数据变换器
 *
 * ## data() 返回值约定
 * - `null` / `array` / `object` → 直接作为响应数据
 * - `Response` 实例（如 success/fail 返回） → 替换当前 response
 * - `ReturnList` 实例 → 自动包装为 ResponsePagination（分页响应）
 * - `ReturnResult` 实例 → 直接作为响应数据
 *
 * ## 快速响应
 * - `$this->success($data)` → 成功响应
 * - `$this->fail($statusCode, $code, $message)` → 错误响应
 */
class Controller
{
  // region 请求参数处理

  /**
   * GET 参数处理器
   *
   * 构造时自动提取 $request->query，执行序列化（类型转换）与校验。
   * 校验结果可通过 $requestQuery->validatedResult 检查。
   *
   * @var ControllerQuery
   */
  protected ControllerQuery $requestQuery;

  /**
   * GET 参数序列化规则
   *
   * 格式与 Serializer 规则一致，支持标量类型（"int"/"string"/"bool"/"float"）和嵌套结构。
   * 设为 null 表示不对 GET 参数做类型转换。
   *
   * @var array{string: string}|DataConversion|null
   */
  protected $requestQuerySerializes = null;

  /**
   * GET 参数校验规则
   *
   * 键为参数名，值为 ValidateRules 实例。设为 null 表示不校验 GET 参数。
   *
   * @var array{string: \kernel\Foundation\Validate\ValidateRules}|\kernel\Foundation\Validate\Validator|null
   */
  protected $requestQueryValidator = null;

  /**
   * Body 参数处理器
   *
   * 构造时自动提取 $request->body（POST/PUT/PATCH），执行序列化与校验。
   * 校验结果可通过 $requestBody->validatedResult 检查。
   *
   * @var ControllerBody
   */
  protected ControllerBody $requestBody;

  /**
   * Body 参数序列化规则
   *
   * 格式与 Serializer 规则一致。设为 null 表示不对 Body 参数做类型转换。
   *
   * @var array{string: string}|DataConversion|null
   */
  protected $requestBodySerializes = null;

  /**
   * Body 参数校验规则
   *
   * 键为参数名，值为 ValidateRules 实例。设为 null 表示不校验 Body 参数。
   *
   * @var array{string: \kernel\Foundation\Validate\ValidateRules}|\kernel\Foundation\Validate\Validator|null
   */
  protected $requestBodyValidator = null;

  // endregion

  // region 响应处理

  /**
   * 响应数据序列化规则
   *
   * 支持四种形式：
   * - `array` — 序列化规则数组，键为字段名，值为类型/嵌套规则
   * - `string` — Serializer 规则名称
   * - `DataConversion` 实例 — 通过 data()->convert() 管道处理
   * - `Serializer` 实例 — 使用其 useRuleName 指定的规则
   *
   * 设为 null 表示不序列化，原样输出。
   *
   * @var array{string: mixed}|string|DataConversion|Serializer|null
   */
  protected $responseSerializes = null;

  /**
   * 允许的数据变换器
   *
   * 客户端可通过 query/body 的 `_transform` 参数请求对响应数据做变换。
   * 仅此列表中声明的类名可被调用，防止未授权逻辑执行。
   *
   * @var string[]
   */
  protected array $allowedTransformers = [];

  // endregion

  // region 核心对象

  /**
   * 当前请求对象
   *
   * 包含原始 query、body、路由 params 等所有请求数据。
   *
   * @var Request
   */
  protected Request $request;

  /**
   * 响应对象
   *
   * 生命周期内可被替换：
   * - 构造时初始化为空的 ControllerResponse
   * - before() 中若校验失败替换为错误 Response
   * - data() 中通过 success()/fail() 可替换
   * - App 层在 data() 返回 ReturnList 时替换为 ResponsePagination
   *
   * @var Response
   */
  public Response $response;

  // endregion

  /**
   * 构造控制器实例
   *
   * 执行顺序：
   * 1. 注入 Request 并初始化空 Response
   * 2. 用子类配置的序列化/校验规则构造 requestQuery（处理 GET 参数）
   * 3. 用子类配置的序列化/校验规则构造 requestBody（处理 Body 参数）
   * 4. 调用 boot() 钩子
   */
  function __construct(Request $request)
  {
    $this->request = $request;
    $this->response = new ControllerResponse(null);

    $this->requestQuery = new ControllerQuery($request, $this->requestQuerySerializes, $this->requestQueryValidator);
    $this->requestBody = new ControllerBody($request, $this->requestBodySerializes, $this->requestBodyValidator);

    $this->boot();
  }

  /**
   * 子类初始化钩子
   *
   * 在 requestQuery/requestBody 构造完成后调用。
   * 适合做依赖注入、属性初始化等无需依赖 query/body 校验结果的操作。
   */
  protected function boot(): void
  {
  }

  /**
   * 获取路由参数（URL 中的占位符，如 /post/{id} 中的 id）
   *
   * @param string|null $key    参数名，为 null 时返回全部路由参数
   * @param mixed       $default 参数不存在时的默认值
   */
  protected function params(?string $key = null, mixed $default = null): mixed
  {
    if ($key === null) {
      return $this->request->params->some();
    }
    $value = $this->request->params->get($key);
    return $value !== null ? $value : $default;
  }

  /**
   * 获取 Body 参数（POST/PUT/PATCH 请求体）
   *
   * 返回的是经过类型转换和校验后的数据，受 $requestBodySerializes/$requestBodyValidator 影响。
   *
   * @param string|null $key    参数名，为 null 时返回全部 Body 参数
   * @param mixed       $default 参数不存在时的默认值
   */
  protected function body(?string $key = null, mixed $default = null): mixed
  {
    $data = $this->requestBody->some();
    if ($key === null) {
      return $data;
    }
    return $data[$key] ?? $default;
  }

  /**
   * 获取 Query 参数（URL ? 后的 GET 参数）
   *
   * 返回的是经过类型转换和校验后的数据，受 $requestQuerySerializes/$requestQueryValidator 影响。
   *
   * @param string|null $key    参数名，为 null 时返回全部 Query 参数
   * @param mixed       $default 参数不存在时的默认值
   */
  protected function query(?string $key = null, mixed $default = null): mixed
  {
    $data = $this->requestQuery->some();
    if ($key === null) {
      return $data;
    }
    return $data[$key] ?? $default;
  }

  /**
   * 获取原始 Body 参数（未经类型转换和校验）
   *
   * @param string|null $key    参数名，为 null 时返回全部数据
   * @param mixed       $default 参数不存在时的默认值
   */
  protected function rawBody(?string $key = null, mixed $default = null): mixed
  {
    if ($key === null) {
      return $this->request->body->some();
    }
    $value = $this->request->body->get($key);
    return $value !== null ? $value : $default;
  }

  /**
   * 获取原始 Query 参数（未经类型转换和校验）
   *
   * @param string|null $key    参数名，为 null 时返回全部数据
   * @param mixed       $default 参数不存在时的默认值
   */
  protected function rawQuery(?string $key = null, mixed $default = null): mixed
  {
    if ($key === null) {
      return $this->request->query->some();
    }
    $value = $this->request->query->get($key);
    return $value !== null ? $value : $default;
  }

  /**
   * 构建成功响应
   *
   * @param mixed          $data       响应数据
   * @param int            $statusCode HTTP 状态码
   * @param int|string     $code       业务状态码
   * @param string         $message    响应消息
   */
  protected function success(mixed $data = null, int $statusCode = 200, int|string $code = 200, string $message = "ok"): Response
  {
    return $this->response->success($data, $statusCode, $code, $message);
  }

  /**
   * 构建错误响应
   *
   * @param int            $statusCode HTTP 状态码
   * @param int|string     $code       业务错误码
   * @param string         $message    错误消息
   * @param mixed          $details    错误详情
   * @param mixed          $data       附加数据
   */
  protected function fail(int $statusCode = 500, int|string $code = "500:ServerError", string $message = "error", mixed $details = [], mixed $data = []): Response
  {
    return $this->response->error($statusCode, $code, $message, $details, $data);
  }

  /**
   * 前置拦截（final，子类不可覆盖）
   *
   * 检查 requestQuery 和 requestBody 的校验结果。
   * 任一校验失败时，将 response 替换为错误响应，阻止后续 data() 执行。
   */
  final function before(): void
  {
    if ($this->requestQuery->validatedResult->error) {
      $this->response = $this->requestQuery->validatedResult;
      return;
    }
    if ($this->requestBody->validatedResult->error) {
      $this->response = $this->requestBody->validatedResult;
    }
  }

  /**
   * 业务逻辑入口
   *
   * 子类必须覆盖此方法（或通过路由指定自定义 handle 方法）。
   *
   * ## 返回值约定
   * | 返回类型       | 行为                                        |
   * |---------------|---------------------------------------------|
   * | `null`        | 不做任何处理                                |
   * | `array/object`| 作为响应数据写入 response                   |
   * | `Response`    | 直接替换当前 response（success/fail 即此方式）|
   * | `ReturnList`  | 自动包装为 ResponsePagination（分页列表）    |
   * | `ReturnResult`| getData() 作为响应数据                      |
   *
   * 路由参数通过 `$this->params()` 获取，或直接作为方法参数（需在路由配置中声明）。
   */
  public function data()
  {
    return null;
  }

  /**
   * 响应后处理（final，子类不可覆盖）
   *
   * 在 data() 成功返回后触发，负责：
   * 1. 调用 Transform 数据变换（需配置 $allowedTransformers，由客户端 _transform 参数触发）
   * 2. 调用 Serializer 序列化响应数据（需配置 $responseSerializes）
   *
   * 若 response 已经是错误状态，跳过所有后处理。
   */
  final function after(): void
  {
    if ($this->response->error) return;

    if (!empty($this->allowedTransformers)) {
      $this->transform();
    }
    if ($this->responseSerializes !== null) {
      $this->serialization();
    }
  }

  /**
   * 响应数据序列化
   *
   * 根据 $responseSerializes 的类型执行不同的序列化逻辑：
   * - DataConversion 实例 → 调用其 data()->convert() 管道
   * - Serializer 实例      → 使用其 useRuleName 规则
   * - 数组 / 字符串         → 作为序列化规则名传入 Serializer::serialization()
   */
  private function serialization(): void
  {
    $ClassNamespace = explode("\\", get_class($this));
    $ClassName = $ClassNamespace[count($ClassNamespace) - 1];
    $ClassName = str_replace("Controller", "", $ClassName);
    $ClassName = lcfirst($ClassName);
    if ($this->responseSerializes instanceof DataConversion) {
      $this->response->addData($this->responseSerializes->data($this->response->getData())->convert(), true);
    } else if ($this->responseSerializes instanceof Serializer) {
      $this->response->addData(Serializer::serialization($this->responseSerializes->useRuleName, $this->response->getData()), true);
    } else if (is_array($this->responseSerializes)) {
      $this->response->addData(Serializer::serialization($this->responseSerializes, $this->response->getData(), $ClassName), true);
    } else if (is_string($this->responseSerializes)) {
      $this->response->addData(Serializer::serialization($this->responseSerializes, $this->response->getData(), $ClassName), true);
    }
  }

  /**
   * 数据变换
   *
   * 客户端通过 query/body 中的 `_transform` 参数请求对响应数据做变换。
   * 仅允许 $allowedTransformers 中声明的变换器，防止客户端调用未授权逻辑。
   */
  private function transform(): void
  {
    if ($this->response->error) return;

    $rawTransform = $this->request->query->get("_transform");
    if ($rawTransform === null) {
      $rawTransform = $this->request->body->get("_transform");
    }
    if (empty($rawTransform)) return;

    $transforms = Transform::parse($rawTransform);
    if (empty($transforms)) return;

    $data = Transform::apply($transforms, $this->allowedTransformers, $this, $this->response->getData());

    $this->response->addData($data, true);
  }
}
