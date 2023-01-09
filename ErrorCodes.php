<?php

namespace kernel\Foundation\Exception;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

$SubmitCodes = [
  ErrorCode::make("LLLEGAL_SUBMISSION", 403, "SUBMIT_403001", "该内容被隐藏了")
];

$AuthCodes = [];

$RouteCodes = [
  ErrorCode::make("ROUTE_DOES_NOT_EXIST", 404, "Route_404001", "资源不存在"),
  ErrorCode::make("METHOD_NOT_ALLOWED", 405, "MethodNotAllowed", "不支持的请求方法")
];

$MiddlwareCodes = [
  ErrorCode::make("MIDDLEWARE_EXECUTION_ERROR", 500, "MIDDLEWARE_500001", "页面不存在")
];

$ViewCodes = [
  ErrorCode::make("VIEW_TEMPLATE_NOT_EXIST", 500, "VIEW_500001", "页面不存在")
];

$ServerCodes = [
  ErrorCode::make("SERVER_ERROR", 500, "SERVER_ERROR", "服务器错误")
];

return \array_merge($AuthCodes, $RouteCodes, $SubmitCodes, $ViewCodes, $MiddlwareCodes);
