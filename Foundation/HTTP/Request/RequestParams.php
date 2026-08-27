<?php

namespace kernel\Foundation\HTTP\Request;

/**
 * URI 参数（路由动态参数）集合
 *
 * 数据不在构造时填充，而是在路由匹配完成后由 App::run()/Console 经父类 fill()
 * 注入（Router 匹配参数的唯一通道）；has/get/some/prepare 等继承自 RequestData。
 */
class RequestParams extends RequestData
{
}
