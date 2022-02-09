<?php

namespace kernel\Extensions\Discuzx;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class Member
{
  function getUser()
  {
    return \getglobal(['member']);
  }
}
