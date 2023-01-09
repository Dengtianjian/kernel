<?php

namespace kernel\Foundation\Controller;

use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Request\RequestBody;
use kernel\Foundation\Output;

class ControllerBody extends RequestBody
{
  public function __construct(Request $request, $bodyDataConversion = null, $bodyValidator = null)
  {
    $this->data = $request->body->some();
    $this->data = $this->handle($bodyDataConversion, $bodyValidator);
  }
}
