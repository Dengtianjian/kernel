<?php

namespace kernel\Platform\DiscuzX\Member;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Response;
use kernel\Model\DiscuzX\CommonUserGroupModel;

if (!defined("F_KERNEL")) {
  exit('Access Denied');
}

class DiscuzXMemberGroup
{
  public static function all()
  {
    $CUGM = new CommonUserGroupModel();
    return $CUGM->getAll();
  }
}
