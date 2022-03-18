<?php

namespace kernel\App\Main;

use kernel\Foundation\Config;
use kernel\Foundation\Controller;
use kernel\Foundation\Output;
use kernel\Foundation\Response;
use QL\QueryList;

class TestViewController extends Controller
{
  public function data()
  {
    if(Config::get("mode")==="development") {
     return Response::error(403,"403001");
    }
    $data = QueryList::get('http://cms.querylist.cc/bizhi/453.html')->find('img')->attrs('src');
    //打印结果
    print_r($data->all());
  }
}
