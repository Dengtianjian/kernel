<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Request\RequestBody;

class ControllerBody extends RequestBody
{
  public function __construct(Request $request, $bodyMutator = null, $bodyValidator = null)
  {
    parent::__construct($bodyMutator, $bodyValidator);
    $this->data = $request->body->some();
    $this->handle();
  }
}
