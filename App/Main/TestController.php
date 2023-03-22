<?php

namespace kernel\App\Main;

use Exception;
use kernel\Foundation\Cache;
use kernel\Foundation\Controller\Controller;
use kernel\Foundation\Data\DataConversion;
use kernel\Foundation\Data\Serializer;
use kernel\Foundation\Exception\Exception as ExceptionException;
use kernel\Foundation\HTTP\Request;
use kernel\Foundation\HTTP\Response;
use kernel\Foundation\HTTP\Response\ResponseView;
use kernel\Foundation\Log;
use kernel\Foundation\Output;
use kernel\Foundation\ReturnResult\ReturnResult;
use kernel\Foundation\Validate\ValidateArray;
use kernel\Foundation\Validate\ValidateRules;
use kernel\Foundation\Validate\Validator;

class TestController extends Controller
{

  public function __construct(Request $R)
  {
    
    parent::__construct($R);
  }
  public function data(Request $R)
  {
    return 1;
  }
}
