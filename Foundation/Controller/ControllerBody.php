<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Request\RequestBody;

/**
 * 控制器 Body 参数处理器
 *
 * 继承 {@see RequestBody}，作为框架在控制器上下文中处理请求体（POST/PUT/PATCH）参数的专用载体。
 *
 * 与 {@see ControllerQuery} 的取舍不同：本类先调用父类 `RequestBody::__construct` 完成 mutator/validator
 * 的初始化，再以 `$request->body`（框架封装后的请求体组件）覆盖 `data`，确保数据类型转换与
 * `Controller::body()` 读取到的内容一致。
 *
 * 构造时应用子类声明的 `$bodyMutator`（类型转换）与 `$bodyValidator`（校验规则），
 * 校验结果写入 `validatedResult`，供 `Controller::before()` 拦截使用。
 */
class ControllerBody extends RequestBody
{
  /**
   * 构造 Body 参数处理器
   *
   * @param Request $request        当前请求对象（从中提取封装后的 body 数据）
   * @param mixed   $bodyMutator    请求体类型转换规则（同 RequestData 的 mutator）
   * @param mixed   $bodyValidator  请求体校验规则（同 RequestData 的 validator）
   */
  public function __construct(Request $request, $bodyMutator = null, $bodyValidator = null)
  {
    parent::__construct($bodyMutator, $bodyValidator);
    $this->data = $request->body->some();
    $this->prepare();
  }
}
