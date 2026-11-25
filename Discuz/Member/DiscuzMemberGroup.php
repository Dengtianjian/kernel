<?php

namespace kernel\Discuz\Member;

use kernel\Discuz\Models\System\CommonUserGroupModel;

class DiscuzMemberGroup
{
  public static function all()
  {
    return CommonUserGroupModel::get();
  }
}
