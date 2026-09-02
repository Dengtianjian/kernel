<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Request\RequestQuery;

/**
 * 控制器 GET 参数处理器
 *
 * 继承 {@see RequestQuery}，作为框架在控制器上下文中处理 URL 查询参数（GET）的专用载体。
 *
 * 与直接使用 `RequestQuery` 的区别：
 * - `RequestQuery` 的父类构造会从原始 `$_GET` 直接填充数据；
 * - 本类显式以 `$request->query`（已由框架封装、可能经过统一预处理的请求查询组件）作为数据源，
 *   避免从 `$_GET` 重复填充后被覆盖，从而保证与 `Controller::query()` 读取到的数据一致。
 *
 * 构造时会同时应用子类声明的 `$queryMutator`（类型转换）与 `$queryValidator`（校验规则），
 * 校验结果写入 `validatedResult`，供 `Controller::before()` 拦截使用。
 */
class ControllerQuery extends RequestQuery
{
  /**
   * 构造 GET 参数处理器
   *
   * @param Request $request        当前请求对象（从中提取封装后的 query 数据）
   * @param mixed   $queryMutator   查询参数类型转换规则（同 RequestData 的 mutator）
   * @param mixed   $queryValidator 查询参数校验规则（同 RequestData 的 validator）
   */
  public function __construct(Request $request, $queryMutator = null, $queryValidator = null)
  {
    // 不调用 RequestQuery::__construct（其内部会从 $_GET 填充 data），
    // 这里显式以 $request->query 为数据源，避免从 $_GET 重复填充后被覆盖。
    $this->mutator = $queryMutator;
    $this->validator = $queryValidator;
    $this->data = $request->query->some();
    $this->prepare();
  }
}
