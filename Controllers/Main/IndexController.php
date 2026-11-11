<?php

namespace kernel\Controllers\Main;

use kernel\Foundation\Controller\Controller;
use kernel\Foundation\HTTP\Request;

class IndexController extends Controller
{
  public function __construct(Request $R)
  {

    parent::__construct($R);
  }
  public function data() {}
}
