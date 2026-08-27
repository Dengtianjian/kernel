<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Request\RequestQuery;
use kernel\Foundation\Output;

class ControllerQuery extends RequestQuery
{
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
