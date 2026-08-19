<?php

namespace kernel\Platform\DiscuzX\Member;

use kernel\Foundation\Data\Arr;
use kernel\Foundation\Response;
use kernel\Platform\DiscuzX\Model\CommonUserGroupModel;


class DiscuzXMemberGroup
{
  public static function all()
  {
    $CUGM = new CommonUserGroupModel();
    return $CUGM->getAll();
  }
}
