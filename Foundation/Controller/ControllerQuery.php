<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Request\RequestQuery;
use kernel\Foundation\Output;

class ControllerQuery extends RequestQuery
{
  public function __construct(Request $request, $queryDataConversion = null, $queryValidator = null)
  {
    parent::__construct($queryDataConversion, $queryValidator);
    $this->data = $request->query->some();
    $this->handle($queryDataConversion, $queryValidator);
  }
}
