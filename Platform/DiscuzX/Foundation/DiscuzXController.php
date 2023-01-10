<?php

namespace kernel\Platform\DiscuzX\Foundation;

use kernel\Foundation\Controller\AuthController;
use kernel\Foundation\Exception\Exception;

class DiscuzXController extends AuthController
{
  public $Formhash = false;
  final public function verifyFormhash()
  {
    if (self::$Formhash) {
      if (!defined("FORMHASH")) {
        define("FORMHASH", 1);
      }
      if (!$this->request->query->get("formhash") || $this->request->body->get("formhash") != \FORMHASH) {
        throw new Exception("非法访问", 403, 403, "formhash");
      }
    }
  }
}
