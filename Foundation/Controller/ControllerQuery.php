<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Request\RequestQuery;
use kernel\Foundation\Output;

class ControllerQuery extends RequestQuery
{
  public function __construct(Request $request, $queryMutator = null, $queryValidator = null)
  {
    parent::__construct($queryMutator, $queryValidator);
    $this->data = $request->query->some();
    $this->handle();
  }
}
