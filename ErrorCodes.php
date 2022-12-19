<?php

namespace gstudio_kernel;

if (!defined("IN_DISCUZ")) {
  exit('Access Denied');
}

use gstudio_kernel\Foundation\Exception\ErrorCode;
use gstudio_kernel\Foundation\Lang;

$SubmitCodes = [
  "LLLEGAL_SUBMISSION" => [403, "SUBMIT_403001", Lang::value("kernel/lllegal_submission")]
];

$AuthCodes = [
  "NOT_AUTH" => [401, "Auth_401001", Lang::value("kernel/not_logged_in")],
  "AUTH_FAILED" => [401, "Auth_401002",  Lang::value("kernel/authentication_failed_need_to_log_in_again")],
  "AUTH_EXPIRED" => [401, "Auth_401003", Lang::value("kernel/login_has_expired_please_log_in_again")],
  "UNAUTHORIZED_ACCESS" => [401, "Auth_401004", Lang::value("kernel/unauthorized_access")],
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
$ErrorCodes = \array_merge($AuthCodes, $RouteCodes, $SubmitCodes, $ViewCodes, $MiddlwareCodes, $ServerCodes);
foreach ($ErrorCodes as $key => $value) {
  ErrorCode::add($key, $value[0], $value[1], $value[2]);
}
