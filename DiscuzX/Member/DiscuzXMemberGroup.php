<?php

namespace kernel\DiscuzX\Member;

use kernel\DiscuzX\Model\System\CommonUserGroupModel;

class DiscuzXMemberGroup
{
  public static function all()
  {
    return CommonUserGroupModel::get();
  }
}
