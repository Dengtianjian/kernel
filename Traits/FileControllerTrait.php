<?php

namespace kernel\Traits;

trait FileControllerTrait
{
  public function __constcutor($R)
  {
    $this->query = [
      "signature" => "string",
      "sign-algorithm" => "string",
      "sign-time" => "string",
      "key-time" => "string",
      "header-list" => "string",
      "url-param-list" => "string"
    ];
    parent::__constcutor($R);
  }
}
