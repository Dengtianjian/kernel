<?php

namespace kernel\Foundation\Exception;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

use kernel\Foundation\Lang;

$SubmitCodes = [
  "LLLEGAL_SUBMISSION" => [403, "SUBMIT_403001", Lang::value("kernel/lllegal_submission")]
];

$AuthCodes = [
];

$RouteCodes = [
  "ROUTE_DOES_NOT_EXIST" => [404, "Route_404001", Lang::value("kernel/route_does_not_exits")],
  "METHOD_NOT_ALLOWED" => [400, "Route_400001", Lang::value("kernel/method_not_allowed")]
];

$MiddlwareCodes = [
  "MIDDLEWARE_EXECUTION_ERROR" => [
    500, "MIDDLEWARE_500001", Lang::value("kernel/middleware_execution_error")
  ]
];

$ViewCodes = [
  "VIEW_TEMPLATE_NOT_EXIST" => [
    500, "VIEW_500001", Lang::value("kernel/view_template_file_not_exist")
  ]
];

$ServerCodes = [
  "SERVER_ERROR" => [
    500, "SERVER_ERROR_500001", Lang::value("kernel/serverError")
  ]
];

$ErrorCodes = \array_merge($AuthCodes, $RouteCodes, $SubmitCodes, $ViewCodes, $MiddlwareCodes);
foreach ($ErrorCodes as $key => $value) {
  ErrorCode::add($key, $value[0], $value[1], $value[2]);
}
