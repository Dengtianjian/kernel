<?php

namespace kernel\Foundation\Exception;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Lang;

$SubmitCodes = [
  "LLLEGAL_SUBMISSION" => [403, "SUBMIT_403001", "该内容被隐藏了"]
];

$AuthCodes = [];

$RouteCodes = [
  "ROUTE_DOES_NOT_EXIST" => [404, "Route_404001", "资源不存在"],
  "METHOD_NOT_ALLOWED" => [405, "MethodNotAllowed", "不支持的请求方法"]
];

$MiddlwareCodes = [
  "MIDDLEWARE_EXECUTION_ERROR" => [
    500, "MIDDLEWARE_500001", "服务器错误"
  ]
];

$ViewCodes = [
  "VIEW_TEMPLATE_NOT_EXIST" => [
    500, "VIEW_500001", "页面不存在"
  ]
];

$ServerCodes = [
  "SERVER_ERROR" => [
    500, "SERVER_ERROR_500001", "服务器错误"
  ]
];

$ErrorCodes = \array_merge($AuthCodes, $RouteCodes, $SubmitCodes, $ViewCodes, $MiddlwareCodes);
foreach ($ErrorCodes as $key => $value) {
  ErrorCode::add($key, $value[0], $value[1], $value[2]);
}
