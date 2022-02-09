<?php

namespace kernel\Foundation;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Controller
{
  public function __get($name)
  {
    return $this->$name;
  }
}
