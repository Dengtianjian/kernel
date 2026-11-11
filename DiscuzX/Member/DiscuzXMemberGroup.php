<?php

namespace kernel\DiscuzX\Member;

use kernel\DiscuzX\Models\System\CommonUserGroupModel;

class DiscuzXMemberGroup
{
  public static function all()
  {
    return CommonUserGroupModel::get();
  }
}
